<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'id',
        'department_id',
        'store_id',
        'name',
        'email',
        'password_hash',
        'position',
        'authority',
        'is_deleted',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'id' => 'integer',
        'department_id' => 'integer',
        'store_id' => 'integer',
        'is_deleted' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    public function appliedApprovals(): HasMany
    {
        return $this->hasMany(Approval::class, 'applicant_user_id', 'id');
    }

    public function assignedApprovals(): HasMany
    {
        return $this->hasMany(Approval::class, 'assignee_user_id', 'id');
    }
}

