<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotMessageLog extends Model
{
    protected $fillable = [
        'update_id',
        'chat_id',
        'username',
        'text',
        'reply',
    ];
}
