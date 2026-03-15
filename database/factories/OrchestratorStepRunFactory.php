<?php

namespace Database\Factories;

use App\Models\OrchestratorRun;
use App\Models\OrchestratorStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrchestratorStepRun>
 */
class OrchestratorStepRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'run_id' => OrchestratorRun::factory(),
            'step_id' => OrchestratorStep::factory(),
            'conversation_id' => null,
            'status' => 'pending',
            'output_summary' => null,
            'condition_passed' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate the step run has completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'output_summary' => $this->faker->paragraph(),
            'condition_passed' => true,
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate the step run was skipped.
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'skipped',
            'condition_passed' => false,
        ]);
    }
}
