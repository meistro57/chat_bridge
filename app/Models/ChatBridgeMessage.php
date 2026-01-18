<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatBridgeMessage extends Model
{
    protected $table = 'chat_bridge_messages';

    protected $fillable = [
        'thread_id',
        'role',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatBridgeThread::class, 'thread_id');
    }
}
