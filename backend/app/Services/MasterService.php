<?php

namespace App\Services;

use App\Repositories\MasterRepository;

class MasterService
{
    public function __construct(
        private readonly MasterRepository $masterRepository
    ) {
    }

    /**
     * 画面初期表示に必要なマスタデータ一式を取得
     */
    public function getInitialMasters(): array
    {
        return [
            'users' => $this->masterRepository->getActiveUsers(),
            'departments' => $this->masterRepository->getActiveDepartments(),
            'stores' => $this->masterRepository->getActiveStores(),
            'statuses' => $this->masterRepository->getAllStatuses(),
        ];
    }
}

