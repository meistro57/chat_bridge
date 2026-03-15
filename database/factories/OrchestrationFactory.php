<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Orchestration>
 */
class OrchestrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'goal' => $this->faker->paragraph(),
            'is_scheduled' => false,
            'cron_expression' => null,
            'timezone' => 'UTC',
            'status' => 'idle',
            'last_run_at' => null,
            'next_run_at' => null,
            'metadata' => null,
        ];
    }

    /**
     * Indicate the orchestration is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_scheduled' => true,
            'cron_expression' => '0 * * * *',
            'next_run_at' => now()->addHour(),
        ]);
    }

    /**
     * Indicate the orchestration is currently running.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'running',
        ]);
    }
}
