<?php

namespace App\Repositories;

use App\Models\Approval;
use App\Models\ApprovalComment;
use App\Models\ApprovalFile;
use App\Models\ApprovalHistory;
use App\Models\ApprovalStatusMaster;
use App\Models\Department;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Concerns\HydratesJoinedRows;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ApprovalRepository
{
    use HydratesJoinedRows;

    public const DETAIL_RELATIONS = [
        'applicant.department',
        'applicant.store',
        'assignee.department',
        'assignee.store',
        'status',
        'histories.user.department',
        'histories.user.store',
        'comments.user.department',
        'comments.user.store',
        'files',
    ];

    /**
     * 条件・フィルタを指定して稟議一覧を取得
     *
     * 起案者・担当者・所属・店舗・ステータス・件数を1回のJOINクエリで取得し、
     * Eloquent のシリアライズ結果と同じ形の配列に整形して返す。
     */
    public function search(array $filters, ?int $currentUserId = null): Collection
    {
        if ($currentUserId === null) {
            return new Collection();
        }

        $query = DB::table('approvals as a')
            ->leftJoin('users as ap', 'ap.id', '=', 'a.applicant_user_id')
            ->leftJoin('departments as apd', 'apd.id', '=', 'ap.department_id')
            ->leftJoin('stores as aps', 'aps.id', '=', 'ap.store_id')
            ->leftJoin('users as asg', 'asg.id', '=', 'a.assignee_user_id')
            ->leftJoin('departments as asgd', 'asgd.id', '=', 'asg.department_id')
            ->leftJoin('stores as asgs', 'asgs.id', '=', 'asg.store_id')
            ->leftJoin('approval_statuses_master as st', 'st.id', '=', 'a.status_id')
            ->select([
                'a.id', 'a.applicant_user_id', 'a.assignee_user_id', 'a.assignee_name', 'a.status_id',
                'a.subject', 'a.amount', 'a.detail', 'a.reason', 'a.created_at', 'a.updated_at', 'a.is_deleted',
            ])
            ->selectSub(
                DB::table('approval_comments as c')
                    ->selectRaw('count(*)')
                    ->whereColumn('c.approval_id', 'a.id')
                    ->where('c.is_deleted', false),
                'comments_count'
            )
            ->selectSub(
                DB::table('approval_files as f')
                    ->selectRaw('count(*)')
                    ->whereColumn('f.approval_id', 'a.id')
                    ->where('f.is_deleted', false),
                'files_count'
            )
            ->addSelect($this->joinedColumns('ap', 'ap_', self::USER_COLUMNS))
            ->addSelect($this->joinedColumns('apd', 'apd_', self::DEPARTMENT_COLUMNS))
            ->addSelect($this->joinedColumns('aps', 'aps_', self::STORE_COLUMNS))
            ->addSelect($this->joinedColumns('asg', 'asg_', self::USER_COLUMNS))
            ->addSelect($this->joinedColumns('asgd', 'asgd_', self::DEPARTMENT_COLUMNS))
            ->addSelect($this->joinedColumns('asgs', 'asgs_', self::STORE_COLUMNS))
            ->addSelect($this->joinedColumns('st', 'st_', ['id', 'name', 'memo']))
            ->where('a.is_deleted', false);

        // 起案者本人と、下書き以外の担当者だけが閲覧できる
        $query->where(function ($q) use ($currentUserId) {
            $q->where('a.applicant_user_id', $currentUserId)
                ->orWhere(function ($assigneeQuery) use ($currentUserId) {
                    $assigneeQuery->where('a.assignee_user_id', $currentUserId)
                        ->where('a.status_id', '!=', ApprovalStatusMaster::STATUS_DRAFT);
                });
        });

        if (isset($filters['status_id']) && $filters['status_id'] !== '') {
            $query->where('a.status_id', (int) $filters['status_id']);
        }

        if (!empty($filters['applicant_user_id'])) {
            $query->where('a.applicant_user_id', (int) $filters['applicant_user_id']);
        }

        if (isset($filters['assignee_user_id']) && $filters['assignee_user_id'] !== '') {
            $query->where('a.assignee_user_id', (int) $filters['assignee_user_id']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('ap.department_id', (int) $filters['department_id']);
        }

        if (!empty($filters['store_id'])) {
            $query->where('ap.store_id', (int) $filters['store_id']);
        }

        if (!empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword) {
                    $q->where('a.subject', 'like', "%{$keyword}%")
                        ->orWhere('a.detail', 'like', "%{$keyword}%")
                        ->orWhere('a.reason', 'like', "%{$keyword}%")
                        ->orWhere('a.assignee_name', 'like', "%{$keyword}%");
                });
            }
        }

        $rows = $query->orderBy('a.created_at', 'desc')
            ->orderBy('a.id', 'desc')
            ->get();

        $approvals = $rows->map(function ($object) {
            $row = (array) $object;

            $applicant = $this->hydrateFromRow(User::class, $row, 'ap_', self::USER_COLUMNS);
            $applicant?->setRelation('department', $this->hydrateFromRow(Department::class, $row, 'apd_', self::DEPARTMENT_COLUMNS));
            $applicant?->setRelation('store', $this->hydrateFromRow(Store::class, $row, 'aps_', self::STORE_COLUMNS));

            $assignee = $this->hydrateFromRow(User::class, $row, 'asg_', self::USER_COLUMNS);
            $assignee?->setRelation('department', $this->hydrateFromRow(Department::class, $row, 'asgd_', self::DEPARTMENT_COLUMNS));
            $assignee?->setRelation('store', $this->hydrateFromRow(Store::class, $row, 'asgs_', self::STORE_COLUMNS));

            $approval = (new Approval())->newFromBuilder([
                'id' => $row['id'],
                'applicant_user_id' => $row['applicant_user_id'],
                'assignee_user_id' => $row['assignee_user_id'],
                'assignee_name' => $row['assignee_name'],
                'status_id' => $row['status_id'],
                'subject' => $row['subject'],
                'amount' => $row['amount'],
                'detail' => $row['detail'],
                'reason' => $row['reason'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'is_deleted' => $row['is_deleted'],
                'comments_count' => $row['comments_count'],
                'files_count' => $row['files_count'],
            ]);
            $approval->setRelation('applicant', $applicant);
            $approval->setRelation('assignee', $assignee);
            $approval->setRelation('status', $this->hydrateFromRow(ApprovalStatusMaster::class, $row, 'st_', ['id', 'name', 'memo']));

            return $approval;
        });

        return new Collection($approvals->all());
    }

    private const USER_COLUMNS = [
        'id', 'department_id', 'store_id', 'name', 'email', 'position', 'authority',
        'created_at', 'updated_at', 'is_deleted',
    ];

    private const DEPARTMENT_COLUMNS = ['id', 'name', 'created_at', 'updated_at', 'is_deleted'];

    private const STORE_COLUMNS = [
        'id', 'name', 'prefecture_id', 'postal_code', 'address_detail', 'created_at', 'updated_at', 'is_deleted',
    ];

    /**
     * JOINしたテーブルの列を「接頭辞_列名」の別名で選択する
     */
    private function joinedColumns(string $table, string $prefix, array $columns): array
    {
        return array_map(fn (string $column) => "{$table}.{$column} as {$prefix}{$column}", $columns);
    }

    /**
     * 詳細表示用にリレーション一式を含めて稟議を取得
     */
    public function findDetailById(int $id): ?Approval
    {
        return Approval::with(self::DETAIL_RELATIONS)
            ->where('id', $id)
            ->where('is_deleted', false)
            ->first();
    }

    /**
     * 更新用に悲観ロック（SELECT ... FOR UPDATE）付きで稟議を取得
     */
    public function findByIdForUpdate(int $id): ?Approval
    {
        return Approval::where('id', $id)
            ->where('is_deleted', false)
            ->lockForUpdate()
            ->first();
    }

    /**
     * IDで有効な稟議を取得
     */
    public function findById(int $id): ?Approval
    {
        return Approval::where('id', $id)
            ->where('is_deleted', false)
            ->first();
    }

    /**
     * 稟議レコードの新規作成
     */
    public function create(array $attributes): Approval
    {
        return Approval::create($attributes);
    }

    /**
     * 稟議レコードの更新
     */
    public function update(Approval $approval, array $attributes): Approval
    {
        $approval->update($attributes);
        return $approval;
    }

    /**
     * 稟議モデルの最新状態と詳細リレーションを再読み込み
     */
    public function loadDetailRelations(Approval $approval): Approval
    {
        return $approval->fresh(self::DETAIL_RELATIONS);
    }

    /**
     * 添付ファイル一覧の同期（既存を論理削除して新規登録）
     */
    public function syncFiles(int $approvalId, array $uploads, ?array $retainedFileIds = null): array
    {
        $deletedPaths = [];
        if ($retainedFileIds !== null) {
            $retainedIds = array_map('intval', $retainedFileIds);
            $currentFiles = ApprovalFile::where('approval_id', $approvalId)
                ->where('is_deleted', false)
                ->get();

            foreach ($currentFiles as $currentFile) {
                if (!in_array($currentFile->id, $retainedIds, true)) {
                    $currentFile->update(['is_deleted' => true]);
                    $deletedPaths[] = $currentFile->file_url;
                }
            }
        }

        $storedPaths = [];
        try {
            foreach ($uploads as $upload) {
                if (!$upload instanceof UploadedFile) {
                    throw new RuntimeException('添付資料のアップロード形式が不正です。');
                }

                $path = $upload->store("approvals/{$approvalId}", 'local');
                if ($path === false) {
                    throw new RuntimeException('添付資料を保存できませんでした。');
                }

                $storedPaths[] = $path;
                ApprovalFile::create([
                    'approval_id' => $approvalId,
                    'file_name' => $upload->getClientOriginalName(),
                    'file_url' => $path,
                    'is_deleted' => false,
                ]);
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }

        return ['stored_paths' => $storedPaths, 'deleted_paths' => $deletedPaths];
    }

    public function findFileById(int $approvalId, int $fileId): ?ApprovalFile
    {
        return ApprovalFile::where('approval_id', $approvalId)
            ->where('id', $fileId)
            ->where('is_deleted', false)
            ->first();
    }

    /**
     * 更新履歴（監査ログ）の登録
     */
    public function createHistory(int $approvalId, ?int $userId, string $operationDetail): ApprovalHistory
    {
        return ApprovalHistory::create([
            'approval_id' => $approvalId,
            'user_id' => $userId,
            'operation_detail' => $operationDetail,
            'is_deleted' => false,
        ]);
    }

    /**
     * コメントの登録
     */
    public function createComment(int $approvalId, int $userId, string $content, int $status): ApprovalComment
    {
        $comment = ApprovalComment::create([
            'approval_id' => $approvalId,
            'user_id' => $userId,
            'content' => $content,
            'status' => $status,
            'is_deleted' => false,
        ]);

        return $comment->load(['user.department', 'user.store']);
    }

    /**
     * 稟議IDとコメントIDで有効なコメントを取得
     */
    public function findCommentById(int $approvalId, int $commentId): ?ApprovalComment
    {
        return ApprovalComment::where('id', $commentId)
            ->where('approval_id', $approvalId)
            ->where('is_deleted', false)
            ->first();
    }

    /**
     * コメントステータスの更新
     */
    public function updateCommentStatus(ApprovalComment $comment, int $status): ApprovalComment
    {
        $comment->update(['status' => $status]);
        return $comment->load(['user.department', 'user.store']);
    }
}

