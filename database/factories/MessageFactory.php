<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'persona_id' => Persona::factory(),
            'role' => 'assistant',
            'content' => $this->faker->paragraph(),
            'tokens_used' => $this->faker->numberBetween(50, 400),
        ];
    }
}
