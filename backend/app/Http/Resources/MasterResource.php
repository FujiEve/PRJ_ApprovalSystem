<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 画面初期表示用マスタ一式。resource は ['users', 'departments', 'stores', 'statuses'] のコレクション配列。
 */
class MasterResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'users' => UserResource::collection($this->resource['users']),
            'departments' => DepartmentResource::collection($this->resource['departments']),
            'stores' => StoreResource::collection($this->resource['stores']),
            'statuses' => ApprovalStatusResource::collection($this->resource['statuses']),
        ];
    }
}
