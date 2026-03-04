<?php

namespace Database\Factories;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ConversationTemplate>
 */
class ConversationTemplateFactory extends Factory
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
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['Debate', 'Brainstorm', 'Interview', 'Story']),
            'starter_message' => $this->faker->paragraph(),
            'max_rounds' => $this->faker->numberBetween(6, 14),
            'persona_a_id' => Persona::factory(),
            'persona_b_id' => Persona::factory(),
            'is_public' => false,
        ];
    }

    public function publicTemplate(): static
    {
        return $this->state(fn () => [
            'is_public' => true,
            'user_id' => null,
        ]);
    }
}
