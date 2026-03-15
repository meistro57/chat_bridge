<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'persona_id',
        'role',
        'content',
        'tokens_used',
        'embedding',
        'embedding_status',
        'embedding_attempts',
        'embedding_last_error',
        'embedding_skip_reason',
        'embedding_last_attempt_at',
        'embedding_next_retry_at',
    ];

    protected $casts = [
        'embedding' => 'array',
        'embedding_last_attempt_at' => 'datetime',
        'embedding_next_retry_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }
}
