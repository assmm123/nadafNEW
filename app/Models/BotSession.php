<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotSession extends Model
{
    protected $fillable = [
        'chat_id',
        'state',
        'context',
        'last_seen_at',
    ];

    protected $casts = [
        'context' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public static function touchSession(int $chatId): self
    {
        return static::updateOrCreate(
            ['chat_id' => $chatId],
            ['last_seen_at' => now()]
        );
    }

    public static function setState(int $chatId, string $state, array $context = []): void
    {
        static::updateOrCreate(
            ['chat_id' => $chatId],
            ['state' => $state, 'context' => $context ?: null, 'last_seen_at' => now()]
        );
    }

    public static function current(int $chatId): self
    {
        return static::touchSession($chatId);
    }
}
