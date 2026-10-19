<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalCommentController extends Controller
{
    public function __construct(
        private readonly ApprovalWorkflowService $workflowService
    ) {
    }

    /**
     * POST /api/approvals/{id}/comments
     * 稟議に対する通常コメントの追加
     */
    public function store(StoreCommentRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $comment = $this->workflowService->addComment(
            $id,
            (int) $validated['user_id'],
            (string) $validated['content']
        );

        return response()->json([
            'message' => 'コメントを投稿しました。',
            'data' => $comment,
        ], 201);
    }

    /**
     * PATCH /api/approvals/{id}/comments/{commentId}/status
     * コメントの既読・対応ステータス更新 (0:未読 / 1:既読 / 2:対応済み)
     */
    public function updateStatus(Request $request, int $id, int $commentId): JsonResponse
    {
        $status = (int) $request->input('status', 0);

        $comment = $this->workflowService->updateCommentStatus($id, $commentId, $status);

        return response()->json([
            'message' => 'コメントのステータスを更新しました。',
            'data' => $comment,
        ]);
    }
}

