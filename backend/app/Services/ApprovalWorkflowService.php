<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\ApprovalComment;
use App\Models\ApprovalStatusMaster;
use App\Repositories\ApprovalRepository;
use App\Repositories\MasterRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApprovalWorkflowService
{
    public function __construct(
        private readonly ApprovalRepository $approvalRepository,
        private readonly MasterRepository $masterRepository
    ) {
    }

    /**
     * 稟議一覧の検索・フィルタ取得
     */
    public function listApprovals(array $filters, ?int $currentUserId = null): Collection
    {
        return $this->approvalRepository->search($filters, $currentUserId);
    }

    /**
     * 稟議詳細の取得（申請者本人または担当者の閲覧権限チェックを含む）
     */
    public function getApprovalDetail(int $approvalId, ?int $currentUserId = null): Approval
    {
        $approval = $this->approvalRepository->findDetailById($approvalId);

        if (!$approval) {
            throw new HttpException(404, '指定された稟議が見つかりません。');
        }

        $isApplicant = $currentUserId !== null && $approval->applicant_user_id === $currentUserId;
        $isAssignedApprover = $currentUserId !== null
            && $approval->status_id !== ApprovalStatusMaster::STATUS_DRAFT
            && $approval->assignee_user_id === $currentUserId;

        if (!$isApplicant && !$isAssignedApprover) {
            throw new HttpException(403, '申請者本人または承認担当者のみ閲覧可能です。');
        }

        return $approval;
    }

    /**
     * 稟議の新規作成（下書き保存または新規申請）
     */
    public function createApproval(array $data): Approval
    {
        return DB::transaction(function () use ($data) {
            $applicantId = (int) $data['applicant_user_id'];
            $assigneeId = (int) ($data['assignee_user_id'] ?? 0);
            $statusId = (int) ($data['status_id'] ?? ApprovalStatusMaster::STATUS_DRAFT);

            $assigneeName = $this->resolveAssigneeName($assigneeId, $data['assignee_name'] ?? null);

            $approval = $this->approvalRepository->create([
                'applicant_user_id' => $applicantId,
                'assignee_user_id' => $assigneeId,
                'assignee_name' => $assigneeName,
                'status_id' => $statusId,
                'subject' => trim((string) $data['subject']),
                'amount' => (int) ($data['amount'] ?? 0),
                'detail' => (string) ($data['detail'] ?? ''),
                'reason' => (string) ($data['reason'] ?? ''),
                'is_deleted' => false,
            ]);

            if (!empty($data['files']) && is_array($data['files'])) {
                $this->approvalRepository->syncFiles($approval->id, $data['files']);
            }

            $operationDetail = $statusId === ApprovalStatusMaster::STATUS_PENDING
                ? '稟議を新規申請しました'
                : '下書きを作成しました';

            $this->approvalRepository->createHistory($approval->id, $applicantId, $operationDetail);

            return $this->approvalRepository->loadDetailRelations($approval);
        });
    }

    /**
     * 下書き(3)または差し戻し(2)状態の稟議の編集・本申請/再申請
     */
    public function updateApproval(int $approvalId, array $data, ?int $actorUserId = null): Approval
    {
        return DB::transaction(function () use ($approvalId, $data, $actorUserId) {
            $approval = $this->approvalRepository->findByIdForUpdate($approvalId);

            if (!$approval) {
                throw new HttpException(404, '指定された稟議が見つかりません。');
            }

            if ($approval->status_id === ApprovalStatusMaster::STATUS_APPROVED) {
                throw new HttpException(400, '承認済みの稟議は編集できません。');
            }

            if ($approval->status_id === ApprovalStatusMaster::STATUS_PENDING) {
                throw new HttpException(400, '申請中の稟議は直接編集できません。一度「取り下げ」を実行して下書きに戻してから編集してください。');
            }

            if (!in_array($approval->status_id, [ApprovalStatusMaster::STATUS_DRAFT, ApprovalStatusMaster::STATUS_REMANDED], true)) {
                throw new HttpException(400, '現在のステータスでは稟議を編集できません。');
            }

            if ($actorUserId !== null && $approval->applicant_user_id !== $actorUserId) {
                throw new HttpException(403, '起案者本人のみ編集・再申請が可能です。');
            }

            $previousStatus = $approval->status_id;
            $nextStatus = (int) ($data['status_id'] ?? $previousStatus);

            if (!in_array($nextStatus, [
                ApprovalStatusMaster::STATUS_PENDING,
                ApprovalStatusMaster::STATUS_REMANDED,
                ApprovalStatusMaster::STATUS_DRAFT,
            ], true)) {
                throw new HttpException(400, '不正な遷移先ステータスです。');
            }

            $assigneeId = (int) ($data['assignee_user_id'] ?? $approval->assignee_user_id);
            $assigneeName = $this->resolveAssigneeName($assigneeId, $data['assignee_name'] ?? null);

            $this->approvalRepository->update($approval, [
                'assignee_user_id' => $assigneeId,
                'assignee_name' => $assigneeName,
                'status_id' => $nextStatus,
                'subject' => trim((string) $data['subject']),
                'amount' => (int) ($data['amount'] ?? 0),
                'detail' => (string) ($data['detail'] ?? ''),
                'reason' => (string) ($data['reason'] ?? ''),
            ]);

            if (array_key_exists('files', $data) && is_array($data['files'])) {
                $this->approvalRepository->syncFiles($approval->id, $data['files']);
            }

            if ($previousStatus === ApprovalStatusMaster::STATUS_REMANDED && $nextStatus === ApprovalStatusMaster::STATUS_PENDING) {
                $operationDetail = '差し戻し案件を修正し、再申請しました';
            } elseif ($previousStatus === ApprovalStatusMaster::STATUS_DRAFT && $nextStatus === ApprovalStatusMaster::STATUS_PENDING) {
                $operationDetail = '下書きから本申請しました';
            } else {
                $operationDetail = '稟議内容を更新（下書き保存）しました';
            }

            $this->approvalRepository->createHistory(
                $approval->id,
                $actorUserId ?? $approval->applicant_user_id,
                $operationDetail
            );

            return $this->approvalRepository->loadDetailRelations($approval);
        });
    }

    /**
     * 申請中(0)の稟議を取り下げて下書き(3)へ戻す
     */
    public function withdrawApproval(int $approvalId, ?int $actorUserId = null): Approval
    {
        return DB::transaction(function () use ($approvalId, $actorUserId) {
            $approval = $this->approvalRepository->findByIdForUpdate($approvalId);

            if (!$approval) {
                throw new HttpException(404, '指定された稟議が見つかりません。');
            }

            if ($approval->status_id === ApprovalStatusMaster::STATUS_APPROVED) {
                throw new HttpException(400, '承認済みの稟議は取り下げできません。');
            }

            if ($approval->status_id !== ApprovalStatusMaster::STATUS_PENDING) {
                throw new HttpException(400, '申請中の稟議のみ取り下げ可能です。');
            }

            if ($actorUserId !== null && $approval->applicant_user_id !== $actorUserId) {
                throw new HttpException(403, '起案者本人のみ取り下げが可能です。');
            }

            $this->approvalRepository->update($approval, [
                'status_id' => ApprovalStatusMaster::STATUS_DRAFT,
            ]);

            $this->approvalRepository->createHistory(
                $approval->id,
                $actorUserId ?? $approval->applicant_user_id,
                '申請を取り下げ（下書きへ変更）しました'
            );

            return $this->approvalRepository->loadDetailRelations($approval);
        });
    }

    /**
     * 申請中(0)の稟議を承認済み(1)へ変更
     */
    public function approveApproval(int $approvalId, int $actorUserId, ?string $comment = null): Approval
    {
        return DB::transaction(function () use ($approvalId, $actorUserId, $comment) {
            $this->ensureApproverAuthority($actorUserId);

            $approval = $this->approvalRepository->findByIdForUpdate($approvalId);

            if (!$approval) {
                throw new HttpException(404, '指定された稟議が見つかりません。');
            }

            if ($approval->status_id !== ApprovalStatusMaster::STATUS_PENDING) {
                throw new HttpException(400, '申請中の稟議のみ承認可能です。');
            }

            $this->approvalRepository->update($approval, [
                'status_id' => ApprovalStatusMaster::STATUS_APPROVED,
            ]);

            $trimmedComment = $comment !== null ? trim($comment) : '';
            if ($trimmedComment !== '') {
                $this->approvalRepository->createComment(
                    $approval->id,
                    $actorUserId,
                    $trimmedComment,
                    ApprovalComment::STATUS_READ
                );
            }

            $this->approvalRepository->createHistory(
                $approval->id,
                $actorUserId,
                '稟議を承認しました'
            );

            return $this->approvalRepository->loadDetailRelations($approval);
        });
    }

    /**
     * 申請中(0)の稟議を差し戻し(2)へ変更（コメント必須）
     */
    public function remandApproval(int $approvalId, int $actorUserId, string $comment): Approval
    {
        return DB::transaction(function () use ($approvalId, $actorUserId, $comment) {
            $this->ensureApproverAuthority($actorUserId);

            $trimmedComment = trim($comment);
            if ($trimmedComment === '') {
                throw new HttpException(422, '差し戻し時は差し戻し理由コメントの入力が必須です。');
            }

            $approval = $this->approvalRepository->findByIdForUpdate($approvalId);

            if (!$approval) {
                throw new HttpException(404, '指定された稟議が見つかりません。');
            }

            if ($approval->status_id !== ApprovalStatusMaster::STATUS_PENDING) {
                throw new HttpException(400, '申請中の稟議のみ差し戻し可能です。');
            }

            $this->approvalRepository->update($approval, [
                'status_id' => ApprovalStatusMaster::STATUS_REMANDED,
            ]);

            $this->approvalRepository->createComment(
                $approval->id,
                $actorUserId,
                $trimmedComment,
                ApprovalComment::STATUS_UNREAD
            );

            $this->approvalRepository->createHistory(
                $approval->id,
                $actorUserId,
                '稟議を差し戻しました'
            );

            return $this->approvalRepository->loadDetailRelations($approval);
        });
    }

    /**
     * 稟議に対する通常コメントの追加
     */
    public function addComment(int $approvalId, int $userId, string $content): ApprovalComment
    {
        return DB::transaction(function () use ($approvalId, $userId, $content) {
            $approval = $this->approvalRepository->findById($approvalId);

            if (!$approval) {
                throw new HttpException(404, '指定された稟議が見つかりません。');
            }

            $comment = $this->approvalRepository->createComment(
                $approval->id,
                $userId,
                trim($content),
                ApprovalComment::STATUS_UNREAD
            );

            $this->approvalRepository->createHistory(
                $approval->id,
                $userId,
                'コメントを投稿しました'
            );

            return $comment;
        });
    }

    /**
     * コメントの既読・対応ステータス更新 (0:未読 / 1:既読 / 2:対応済み)
     */
    public function updateCommentStatus(int $approvalId, int $commentId, int $status, int $actorUserId): ApprovalComment
    {
        if (!in_array($status, [ApprovalComment::STATUS_READ, ApprovalComment::STATUS_RESOLVED], true)) {
            throw new HttpException(422, '不正なコメントステータスです。');
        }

        $comment = $this->approvalRepository->findCommentById($approvalId, $commentId);

        if (!$comment) {
            throw new HttpException(404, '指定されたコメントが見つかりません。');
        }

        $approval = $this->approvalRepository->findById($approvalId);
        if (!$approval) {
            throw new HttpException(404, '指定された稟議が見つかりません。');
        }

        if ($approval->applicant_user_id !== $actorUserId) {
            throw new HttpException(403, 'コメント状態を変更できるのは起案者本人のみです。');
        }

        $allowedNextStatus = match ($comment->status) {
            ApprovalComment::STATUS_UNREAD => ApprovalComment::STATUS_READ,
            ApprovalComment::STATUS_READ => ApprovalComment::STATUS_RESOLVED,
            default => null,
        };

        if ($status !== $allowedNextStatus) {
            throw new HttpException(422, 'コメント状態は未読から既読、既読から対応済みの順にのみ変更できます。');
        }

        return $this->approvalRepository->updateCommentStatus($comment, $status);
    }

    private function ensureApproverAuthority(int $userId): void
    {
        $user = $this->masterRepository->findActiveUserById($userId);
        if (!$user || !in_array($user->authority, ['approver', 'admin'], true)) {
            throw new HttpException(403, '承認・差し戻し操作は承認者権限を持つユーザのみ実行可能です。');
        }
    }

    private function resolveAssigneeName(int $assigneeId, ?string $inputName): string
    {
        $trimmed = $inputName !== null ? trim($inputName) : '';

        if ($assigneeId > 0) {
            if ($trimmed !== '') {
                return $trimmed;
            }
            $user = $this->masterRepository->findActiveUserById($assigneeId);
            return $user ? $user->name : '未定';
        }

        return $trimmed !== '' ? $trimmed : '未定';
    }
}

