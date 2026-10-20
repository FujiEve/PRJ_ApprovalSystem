<?php

namespace App\Services;

use App\Repositories\MasterRepository;
use Illuminate\Support\Facades\Cache;

class MasterService
{
    public const CACHE_KEY = 'masters.initial';

    // マスタは更新APIが無くほぼ変化しないため、DB往復を省くよう短時間キャッシュする
    public const CACHE_TTL_SECONDS = 600;

    public function __construct(
        private readonly MasterRepository $masterRepository
    ) {
    }

    /**
     * 画面初期表示に必要なマスタデータ一式を取得
     */
    public function getInitialMasters(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn () => $this->masterRepository->getInitialMasters()
        );
    }
}

