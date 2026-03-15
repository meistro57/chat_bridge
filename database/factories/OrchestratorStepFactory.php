<?php

namespace Database\Factories;

use App\Models\Orchestration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrchestratorStep>
 */
class OrchestratorStepFactory extends Factory
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
            'step_number' => $this->faker->numberBetween(1, 10),
            'label' => $this->faker->words(3, true),
            'template_id' => null,
            'persona_a_id' => null,
            'persona_b_id' => null,
            'provider_a' => null,
            'model_a' => null,
            'provider_b' => null,
            'model_b' => null,
            'input_source' => 'static',
            'input_value' => $this->faker->sentence(),
            'input_variable_name' => null,
            'output_action' => 'log',
            'output_variable_name' => null,
            'output_webhook_url' => null,
            'condition' => null,
            'pause_before_run' => false,
        ];
    }

    /**
     * Indicate the step passes output to the next step.
     */
    public function passingOutput(): static
    {
        return $this->state(fn (array $attributes) => [
            'output_action' => 'pass_to_next',
        ]);
    }
}
