<?php

namespace App\Repositories;

use App\Models\ApprovalStatusMaster;
use App\Models\Department;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Concerns\HydratesJoinedRows;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MasterRepository
{
    use HydratesJoinedRows;

    private const USER_COLUMNS = [
        'id', 'department_id', 'store_id', 'name', 'email', 'position', 'authority',
        'created_at', 'updated_at', 'is_deleted',
    ];

    private const DEPARTMENT_COLUMNS = ['id', 'name', 'created_at', 'updated_at', 'is_deleted'];

    private const STORE_COLUMNS = [
        'id', 'name', 'prefecture_id', 'postal_code', 'address_detail', 'created_at', 'updated_at', 'is_deleted',
    ];

    private const STATUS_COLUMNS = ['id', 'name', 'memo'];

    /**
     * 画面初期表示用のマスタ一式を1回のクエリ(UNION ALL)で取得する。
     * 操作対象・担当者選択用のユーザは有効なもの(予約レコード id=0 を除く)のみ。
     */
    public function getInitialMasters(): array
    {
        $rows = DB::select(<<<'SQL'
SELECT 'user' AS kind, id, name, CAST(NULL AS VARCHAR(255)) AS memo, CAST(NULL AS INTEGER) AS prefecture_id,
       CAST(NULL AS VARCHAR(255)) AS postal_code, CAST(NULL AS VARCHAR(255)) AS address_detail,
       department_id, store_id, email, "position" AS job_position, authority,
       created_at, updated_at, is_deleted
FROM users WHERE is_deleted = ? AND id > 0
UNION ALL
SELECT 'department', id, name, CAST(NULL AS VARCHAR(255)), CAST(NULL AS INTEGER),
       CAST(NULL AS VARCHAR(255)), CAST(NULL AS VARCHAR(255)),
       CAST(NULL AS BIGINT), CAST(NULL AS BIGINT), CAST(NULL AS VARCHAR(255)), CAST(NULL AS VARCHAR(255)), CAST(NULL AS VARCHAR(255)),
       created_at, updated_at, is_deleted
FROM departments
UNION ALL
SELECT 'store', id, name, CAST(NULL AS VARCHAR(255)), prefecture_id,
       postal_code, address_detail,
       CAST(NULL AS BIGINT), CAST(NULL AS BIGINT), CAST(NULL AS VARCHAR(255)), CAST(NULL AS VARCHAR(255)), CAST(NULL AS VARCHAR(255)),
       created_at, updated_at, is_deleted
FROM stores
UNION ALL
SELECT 'status', id, name, memo, CAST(NULL AS INTEGER),
       CAST(NULL AS VARCHAR(255)), CAST(NULL AS VARCHAR(255)),
       CAST(NULL AS BIGINT), CAST(NULL AS BIGINT), CAST(NULL AS VARCHAR(255)), CAST(NULL AS VARCHAR(255)), CAST(NULL AS VARCHAR(255)),
       CAST(NULL AS TIMESTAMP), CAST(NULL AS TIMESTAMP), CAST(NULL AS BOOLEAN)
FROM approval_statuses_master
ORDER BY 1, 2
SQL, [false]);

        $userRows = [];
        $departments = [];
        $stores = [];
        $statuses = [];

        foreach ($rows as $object) {
            $row = (array) $object;
            $row['position'] = $row['job_position'];

            match ($row['kind']) {
                'user' => $userRows[] = $row,
                'department' => $departments[(int) $row['id']] = $this->hydrateFromRow(Department::class, $row, '', self::DEPARTMENT_COLUMNS),
                'store' => $stores[(int) $row['id']] = $this->hydrateFromRow(Store::class, $row, '', self::STORE_COLUMNS),
                'status' => $statuses[] = $this->hydrateFromRow(ApprovalStatusMaster::class, $row, '', self::STATUS_COLUMNS),
            };
        }

        $users = array_map(function (array $row) use ($departments, $stores) {
            $user = $this->hydrateFromRow(User::class, $row, '', self::USER_COLUMNS);
            $user->setRelation('department', $departments[(int) $row['department_id']] ?? null);
            $user->setRelation('store', $stores[(int) $row['store_id']] ?? null);

            return $user;
        }, $userRows);

        return [
            'users' => new Collection($users),
            'departments' => (new Collection($departments))->where('is_deleted', false)->values(),
            'stores' => (new Collection($stores))->where('is_deleted', false)->values(),
            'statuses' => new Collection($statuses),
        ];
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
}
