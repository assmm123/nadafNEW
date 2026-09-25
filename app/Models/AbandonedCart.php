<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbandonedCart extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'identifier',
        'items',
        'total_usd',
        'first_seen_at',
        'last_activity_at',
        'notified',
    ];

    protected $casts = [
        'items' => 'array',
        'total_usd' => 'decimal:2',
        'first_seen_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'notified' => 'boolean',
    ];
}
