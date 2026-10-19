<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApprovalRequest;
use App\Http\Requests\UpdateApprovalRequest;
use App\Models\ApprovalStatusMaster;
use App\Services\ApprovalWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalWorkflowService $workflowService
    ) {
    }

    /**
     * GET /api/approvals
     * 稟議一覧取得（フィルタ・検索・下書き閲覧制御対応）
     */
    public function index(Request $request): JsonResponse
    {
        $currentUserId = $this->resolveCurrentUserId($request);
        $filters = $request->only([
            'status_id',
            'applicant_user_id',
            'assignee_user_id',
            'department_id',
            'store_id',
            'keyword',
        ]);

        $approvals = $this->workflowService->listApprovals($filters, $currentUserId);

        return response()->json([
            'data' => $approvals,
        ]);
    }

    /**
     * POST /api/approvals
     * 稟議の新規作成（下書き保存または新規申請）
     */
    public function store(StoreApprovalRequest $request): JsonResponse
    {
        $approval = $this->workflowService->createApproval($request->validated());

        return response()->json([
            'message' => (int) $approval->status_id === ApprovalStatusMaster::STATUS_PENDING
                ? '稟議を申請しました。'
                : '下書きを保存しました。',
            'data' => $approval,
        ], 201);
    }

    /**
     * GET /api/approvals/{id}
     * 稟議詳細取得（コメント・更新履歴・添付ファイルを含む）
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $currentUserId = $this->resolveCurrentUserId($request);
        $approval = $this->workflowService->getApprovalDetail($id, $currentUserId);

        return response()->json([
            'data' => $approval,
        ]);
    }

    /**
     * PUT /api/approvals/{id}
     * 下書き(3)または差し戻し(2)状態の稟議内容の編集・本申請/再申請
     */
    public function update(UpdateApprovalRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();
        $actorUserId = isset($validated['user_id'])
            ? (int) $validated['user_id']
            : $this->resolveCurrentUserId($request);

        $approval = $this->workflowService->updateApproval($id, $validated, $actorUserId);

        return response()->json([
            'message' => (int) $approval->status_id === ApprovalStatusMaster::STATUS_PENDING
                ? '稟議を申請しました。'
                : '稟議内容を保存しました。',
            'data' => $approval,
        ]);
    }

    private function resolveCurrentUserId(Request $request): ?int
    {
        $headerId = $request->header('X-Current-User-Id');
        if ($headerId !== null && $headerId !== '' && is_numeric($headerId)) {
            return (int) $headerId;
        }
        if ($request->filled('current_user_id') && is_numeric($request->query('current_user_id'))) {
            return (int) $request->query('current_user_id');
        }
        return null;
    }
}

