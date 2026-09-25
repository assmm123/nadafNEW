<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotPendingEdit extends Model
{
    protected $fillable = [
        'variant_id',
        'field',
        'new_value',
        'chat_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
