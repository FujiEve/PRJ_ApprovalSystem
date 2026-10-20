<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Approval */
class ApprovalListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'applicant_user_id' => $this->applicant_user_id,
            'assignee_user_id' => $this->assignee_user_id,
            'assignee_name' => $this->assignee_name,
            'status_id' => $this->status_id,
            'subject' => $this->subject,
            'amount' => $this->amount,
            'detail' => $this->detail,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_deleted' => $this->is_deleted,
            'comments_count' => (int) $this->comments_count,
            'files_count' => (int) $this->files_count,
            'applicant' => new UserResource($this->whenLoaded('applicant')),
            'assignee' => new UserResource($this->whenLoaded('assignee')),
            'status' => new ApprovalStatusResource($this->whenLoaded('status')),
        ];
    }
}
