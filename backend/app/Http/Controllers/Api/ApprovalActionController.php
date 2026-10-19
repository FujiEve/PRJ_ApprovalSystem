<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveApprovalRequest;
use App\Http\Requests\RemandApprovalRequest;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalActionController extends Controller
{
    public function __construct(
        private readonly ApprovalWorkflowService $workflowService
    ) {
    }

    /**
     * POST /api/approvals/{id}/withdraw
     * 申請中(0)の稟議を取り下げ、下書き(3)へ戻す
     */
    public function withdraw(Request $request, int $id): JsonResponse
    {
        $actorUserId = null;
        if ($request->filled('user_id')) {
            $actorUserId = (int) $request->input('user_id');
        } elseif ($request->header('X-Current-User-Id') !== null && $request->header('X-Current-User-Id') !== '') {
            $actorUserId = (int) $request->header('X-Current-User-Id');
        }

        $approval = $this->workflowService->withdrawApproval($id, $actorUserId);

        return response()->json([
            'message' => '申請を取り下げ、下書きに戻しました。',
            'data' => $approval,
        ]);
    }

    /**
     * POST /api/approvals/{id}/approve
     * 申請中(0)の稟議を承認済み(1)へ変更
     */
    public function approve(ApproveApprovalRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $approval = $this->workflowService->approveApproval(
            $id,
            (int) $validated['user_id'],
            $validated['comment'] ?? null
        );

        return response()->json([
            'message' => '稟議を承認しました。',
            'data' => $approval,
        ]);
    }

    /**
     * POST /api/approvals/{id}/remand
     * 申請中(0)の稟議を差し戻し(2)へ変更（コメント必須）
     */
    public function remand(RemandApprovalRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $approval = $this->workflowService->remandApproval(
            $id,
            (int) $validated['user_id'],
            (string) $validated['comment']
        );

        return response()->json([
            'message' => '稟議を差し戻しました。',
            'data' => $approval,
        ]);
    }
}

