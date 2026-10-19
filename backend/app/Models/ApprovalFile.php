<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalFile extends Model
{
    protected $table = 'approval_files';

    protected $fillable = [
        'approval_id',
        'file_name',
        'file_url',
        'is_deleted',
    ];

    protected $casts = [
        'id' => 'integer',
        'approval_id' => 'integer',
        'is_deleted' => 'boolean',
    ];

    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class, 'approval_id', 'id');
    }
}

