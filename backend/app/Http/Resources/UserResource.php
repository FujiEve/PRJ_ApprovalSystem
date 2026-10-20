<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department_id' => $this->department_id,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'email' => $this->email,
            'position' => $this->position,
            'authority' => $this->authority,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_deleted' => $this->is_deleted,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'store' => new StoreResource($this->whenLoaded('store')),
        ];
    }
}
