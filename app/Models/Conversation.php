<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
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
    ];

    protected $casts = [
        'metadata' => 'json',
        'temp_a' => 'float',
        'temp_b' => 'float',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function personaA()
    {
        return $this->belongsTo(Persona::class, 'persona_a_id');
    }

    public function personaB()
    {
        return $this->belongsTo(Persona::class, 'persona_b_id');
    }
}
