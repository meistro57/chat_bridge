<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'user_id',
        'persona_a_id',
        'persona_b_id',
        'provider_a',
        'provider_b',
        'model_a',
        'model_b',
        'temp_a',
        'temp_b',
        'starter_message',
        'status',
        'metadata',
        'max_rounds',
        'stop_word_detection',
        'stop_words',
        'stop_word_threshold',
        'discord_webhook_url',
        'discord_thread_id',
        'discord_streaming_enabled',
    ];

    protected $casts = [
        'metadata' => 'json',
        'temp_a' => 'float',
        'temp_b' => 'float',
        'max_rounds' => 'integer',
        'stop_word_detection' => 'boolean',
        'stop_words' => 'json',
        'stop_word_threshold' => 'float',
        'discord_streaming_enabled' => 'boolean',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function personaA(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_a_id');
    }

    public function personaB(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_b_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{provider:string, model:?string, temperature:float}
     */
    public function settingsForPersona(Persona $persona): array
    {
        $usePersonaA = $this->personaA && $persona->is($this->personaA);
        $provider = $usePersonaA ? $this->provider_a : $this->provider_b;
        $model = $usePersonaA ? $this->model_a : $this->model_b;

        return [
            'provider' => $provider ?: config('ai.default', 'openai'),
            'model' => $model ?: null,
            'temperature' => 1.0,
        ];
    }
}
