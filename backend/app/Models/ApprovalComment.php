<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalComment extends Model
{
    protected $table = 'approval_comments';

    public const STATUS_UNREAD = 0;   // 未読
    public const STATUS_READ = 1;     // 既読
    public const STATUS_RESOLVED = 2; // 対応済み

    protected $fillable = [
        'approval_id',
        'user_id',
        'content',
        'status',
        'is_deleted',
    ];

    protected $casts = [
        'id' => 'integer',
        'approval_id' => 'integer',
        'user_id' => 'integer',
        'status' => 'integer',
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

