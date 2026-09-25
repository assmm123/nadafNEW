<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatLog extends Model
{
    protected $fillable = [
        'session_id',
        'visitor_name',
        'message',
        'matched_answer',
        'was_helpful',
        'trigger',
    ];
}
