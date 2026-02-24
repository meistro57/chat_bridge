<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiKey>
 */
class ApiKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'gemini',
            'key' => 'AIzaSy'.fake()->regexify('[A-Za-z0-9_\-]{33}'),
            'label' => fake()->word(),
            'is_active' => true,
            'is_validated' => false,
            'last_validated_at' => null,
            'validation_error' => null,
        ];
    }
}
