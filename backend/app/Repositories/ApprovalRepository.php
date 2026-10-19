<?php

namespace App\Repositories;

use App\Models\Approval;
use App\Models\ApprovalComment;
use App\Models\ApprovalFile;
use App\Models\ApprovalHistory;
use App\Models\ApprovalStatusMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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
        $query = Approval::with(self::LIST_RELATIONS)
            ->withCount(['comments', 'files'])
            ->where('is_deleted', false);

        // 下書き(status_id = 3)は起案者本人のみ閲覧可能
        if ($currentUserId !== null) {
            $query->where(function (Builder $q) use ($currentUserId) {
                $q->where('status_id', '!=', ApprovalStatusMaster::STATUS_DRAFT)
                    ->orWhere('applicant_user_id', $currentUserId);
            });
        }

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
    public function syncFiles(int $approvalId, array $files): void
    {
        ApprovalFile::where('approval_id', $approvalId)->update(['is_deleted' => true]);

        foreach ($files as $file) {
            $fileName = isset($file['file_name']) ? trim((string) $file['file_name']) : '';
            if ($fileName === '') {
                continue;
            }
            $fileUrl = isset($file['file_url']) && trim((string) $file['file_url']) !== ''
                ? trim((string) $file['file_url'])
                : 'https://example.local/files/' . rawurlencode($fileName);

            ApprovalFile::create([
                'approval_id' => $approvalId,
                'file_name' => $fileName,
                'file_url' => $fileUrl,
                'is_deleted' => false,
            ]);
        }
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

