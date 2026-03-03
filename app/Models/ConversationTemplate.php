<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'category',
        'starter_message',
        'max_rounds',
        'persona_a_id',
        'persona_b_id',
        'is_public',
        'rag_enabled',
        'rag_source_limit',
        'rag_score_threshold',
        'rag_system_prompt',
        'rag_files',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'max_rounds' => 'integer',
            'rag_enabled' => 'boolean',
            'rag_source_limit' => 'integer',
            'rag_score_threshold' => 'float',
            'rag_files' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function personaA(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_a_id');
    }

    public function personaB(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_b_id');
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopePrivate(Builder $query): Builder
    {
        return $query->where('is_public', false);
    }

    public function scopeByCategory(Builder $query, ?string $category): Builder
    {
        if (! $category) {
            return $query;
        }

        return $query->where('category', $category);
    }
}
