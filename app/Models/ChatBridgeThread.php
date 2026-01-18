<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatBridgeThread extends Model
{
    protected $table = 'chat_bridge_threads';

    protected $fillable = [
        'bridge_thread_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatBridgeMessage::class, 'thread_id');
    }
}
