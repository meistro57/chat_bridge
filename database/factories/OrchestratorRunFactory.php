<?php

namespace Database\Factories;

use App\Models\Orchestration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrchestratorRun>
 */
class OrchestratorRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'orchestration_id' => Orchestration::factory(),
            'user_id' => User::factory(),
            'status' => 'queued',
            'triggered_by' => 'manual',
            'variables' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate the run is currently running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    /**
     * Indicate the run has completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate the run has failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
            'error_message' => 'Something went wrong.',
        ]);
    }
}
