<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Approval extends Model
{
    protected $table = 'approvals';

    protected $fillable = [
        'applicant_user_id',
        'assignee_user_id',
        'assignee_name',
        'status_id',
        'subject',
        'amount',
        'detail',
        'reason',
        'is_deleted',
    ];

    protected $casts = [
        'id' => 'integer',
        'applicant_user_id' => 'integer',
        'assignee_user_id' => 'integer',
        'status_id' => 'integer',
        'amount' => 'integer',
        'is_deleted' => 'boolean',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id', 'id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id', 'id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ApprovalStatusMaster::class, 'status_id', 'id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ApprovalHistory::class, 'approval_id', 'id')
            ->where('is_deleted', false)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ApprovalFile::class, 'approval_id', 'id')
            ->where('is_deleted', false)
            ->orderBy('id', 'asc');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ApprovalComment::class, 'approval_id', 'id')
            ->where('is_deleted', false)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc');
    }
}

