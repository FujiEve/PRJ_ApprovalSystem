<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalStatusMaster extends Model
{
    protected $table = 'approval_statuses_master';

    public $incrementing = false;
    public $timestamps = false;

    public const STATUS_PENDING = 0;  // 申請中
    public const STATUS_APPROVED = 1; // 承認済み
    public const STATUS_REMANDED = 2; // 差し戻し
    public const STATUS_DRAFT = 3;    // 下書き

    protected $fillable = [
        'id',
        'name',
        'memo',
    ];

    protected $casts = [
        'id' => 'integer',
    ];

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'status_id', 'id');
    }
}

