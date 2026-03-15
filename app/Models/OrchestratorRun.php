<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrchestratorRun extends Model
{
    /** @use HasFactory<\Database\Factories\OrchestratorRunFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'orchestration_id',
        'user_id',
        'status',
        'triggered_by',
        'variables',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function orchestration(): BelongsTo
    {
        return $this->belongsTo(Orchestration::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stepRuns(): HasMany
    {
        return $this->hasMany(OrchestratorStepRun::class, 'run_id');
    }
}
