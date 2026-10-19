<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    protected $table = 'stores';

    protected $fillable = [
        'name',
        'prefecture_id',
        'postal_code',
        'address_detail',
        'is_deleted',
    ];

    protected $casts = [
        'id' => 'integer',
        'prefecture_id' => 'integer',
        'is_deleted' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'store_id', 'id');
    }
}

