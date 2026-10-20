<?php

namespace App\Repositories;

use App\Models\Approval;
use App\Models\ApprovalComment;
use App\Models\ApprovalFile;
use App\Models\ApprovalHistory;
use App\Models\ApprovalStatusMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ApprovalRepository
{
    public const LIST_RELATIONS = [
        'applicant.department',
        'applicant.store',
        'assignee.department',
        'assignee.store',
        'status',
    ];

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
     */
    public function search(array $filters, ?int $currentUserId = null): Collection
    {
        if ($currentUserId === null) {
            return new Collection();
        }

        $query = Approval::with(self::LIST_RELATIONS)
            ->withCount(['comments', 'files'])
            ->where('is_deleted', false);

        // 起案者本人と、下書き以外の担当者だけが閲覧できる
        $query->where(function (Builder $q) use ($currentUserId) {
            $q->where('applicant_user_id', $currentUserId)
                ->orWhere(function (Builder $assigneeQuery) use ($currentUserId) {
                    $assigneeQuery->where('assignee_user_id', $currentUserId)
                        ->where('status_id', '!=', ApprovalStatusMaster::STATUS_DRAFT);
                });
            });

        if (isset($filters['status_id']) && $filters['status_id'] !== '') {
            $query->where('status_id', (int) $filters['status_id']);
        }

        if (!empty($filters['applicant_user_id'])) {
            $query->where('applicant_user_id', (int) $filters['applicant_user_id']);
        }

        if (isset($filters['assignee_user_id']) && $filters['assignee_user_id'] !== '') {
            $query->where('assignee_user_id', (int) $filters['assignee_user_id']);
        }

        if (!empty($filters['department_id'])) {
            $departmentId = (int) $filters['department_id'];
            $query->whereHas('applicant', function (Builder $q) use ($departmentId) {
                $q->where('department_id', $departmentId);
            });
        }

        if (!empty($filters['store_id'])) {
            $storeId = (int) $filters['store_id'];
            $query->whereHas('applicant', function (Builder $q) use ($storeId) {
                $q->where('store_id', $storeId);
            });
        }

        if (!empty($filters['keyword'])) {
            $keyword = trim((string) $filters['keyword']);
            if ($keyword !== '') {
                $query->where(function (Builder $q) use ($keyword) {
                    $q->where('subject', 'like', "%{$keyword}%")
                        ->orWhere('detail', 'like', "%{$keyword}%")
                        ->orWhere('reason', 'like', "%{$keyword}%")
                        ->orWhere('assignee_name', 'like', "%{$keyword}%");
                });
            }
        }

        return $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
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

