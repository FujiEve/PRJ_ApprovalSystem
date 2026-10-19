<?php

namespace App\Repositories;

use App\Models\ApprovalStatusMaster;
use App\Models\Department;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class MasterRepository
{
    /**
     * 操作対象・担当者選択用の有効ユーザ一覧（予約レコード id=0 を除く）を取得
     */
    public function getActiveUsers(): Collection
    {
        return User::with(['department', 'store'])
            ->where('is_deleted', false)
            ->where('id', '>', 0)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * IDで有効なユーザを取得
     */
    public function findActiveUserById(int $userId): ?User
    {
        return User::where('id', $userId)
            ->where('is_deleted', false)
            ->first();
    }

    /**
     * 有効な部署マスタ一覧を取得
     */
    public function getActiveDepartments(): Collection
    {
        return Department::where('is_deleted', false)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * 有効な店舗マスタ一覧を取得
     */
    public function getActiveStores(): Collection
    {
        return Store::where('is_deleted', false)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * 稟議ステータスマスタ一覧を取得
     */
    public function getAllStatuses(): Collection
    {
        return ApprovalStatusMaster::orderBy('id', 'asc')->get();
    }
}

