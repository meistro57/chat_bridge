<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrchestratorStep extends Model
{
    /** @use HasFactory<\Database\Factories\OrchestratorStepFactory> */
    use HasFactory, HasUuids;

    protected $table = 'orchestration_steps';

    protected $fillable = [
        'orchestration_id',
        'step_number',
        'label',
        'template_id',
        'persona_a_id',
        'persona_b_id',
        'provider_a',
        'model_a',
        'provider_b',
        'model_b',
        'input_source',
        'input_value',
        'input_variable_name',
        'output_action',
        'output_variable_name',
        'output_webhook_url',
        'condition',
        'pause_before_run',
    ];

    protected $casts = [
        'condition' => 'array',
        'pause_before_run' => 'boolean',
        'step_number' => 'integer',
    ];

    public function orchestration(): BelongsTo
    {
        return $this->belongsTo(Orchestration::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ConversationTemplate::class, 'template_id');
    }

    public function personaA(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_a_id');
    }

    public function personaB(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_b_id');
    }
}
