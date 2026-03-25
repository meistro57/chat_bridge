<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Orchestration extends Model
{
    /** @use HasFactory<\Database\Factories\OrchestrationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'goal',
        'is_scheduled',
        'cron_expression',
        'timezone',
        'status',
        'last_run_at',
        'next_run_at',
        'metadata',
    ];

    protected $casts = [
        'is_scheduled' => 'boolean',
        'metadata' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(OrchestratorStep::class)->orderBy('step_number');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(OrchestratorRun::class);
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(OrchestratorRun::class);
    }
}
