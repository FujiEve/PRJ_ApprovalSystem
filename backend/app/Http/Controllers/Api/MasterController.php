<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MasterService;
use Illuminate\Http\JsonResponse;

class MasterController extends Controller
{
    public function __construct(
        private readonly MasterService $masterService
    ) {
    }

    /**
     * GET /api/masters
     * 初期表示用のユーザ一覧、部署一覧、店舗一覧、ステータス一覧を取得
     */
    public function index(): JsonResponse
    {
        return response()->json($this->masterService->getInitialMasters());
    }
}

