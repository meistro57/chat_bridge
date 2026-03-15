<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrchestratorStepRun extends Model
{
    /** @use HasFactory<\Database\Factories\OrchestratorStepRunFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'run_id',
        'step_id',
        'conversation_id',
        'status',
        'output_summary',
        'condition_passed',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'condition_passed' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(OrchestratorRun::class, 'run_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(OrchestratorStep::class, 'step_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
