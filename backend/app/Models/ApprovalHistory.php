<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalHistory extends Model
{
    protected $table = 'approval_histories';

    protected $fillable = [
        'approval_id',
        'user_id',
        'operation_detail',
        'is_deleted',
    ];

    protected $casts = [
        'id' => 'integer',
        'approval_id' => 'integer',
        'user_id' => 'integer',
        'is_deleted' => 'boolean',
    ];

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class, 'approval_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}

