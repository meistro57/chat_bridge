<?php

namespace Database\Factories;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
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
            'persona_a_id' => Persona::factory(),
            'persona_b_id' => Persona::factory(),
            'provider_a' => 'openai',
            'provider_b' => 'openai',
            'model_a' => 'gpt-4o-mini',
            'model_b' => 'gpt-4o-mini',
            'temp_a' => 0.7,
            'temp_b' => 0.7,
            'starter_message' => $this->faker->sentence(),
            'status' => 'active',
            'max_rounds' => 10,
            'stop_word_detection' => false,
            'stop_words' => [],
            'stop_word_threshold' => 0.8,
        ];
    }

    /**
     * Indicate that the conversation is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
