<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonaTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_personas(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/personas');
        $response->assertStatus(200);
    }

    public function test_can_create_persona(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/personas', [
            'name' => 'Test Persona',
            'system_prompt' => 'Be helpful',
            'temperature' => 0.7,
        ]);

        $response->assertRedirect('/personas');
        $this->assertDatabaseHas('personas', ['name' => 'Test Persona']);
    }

    public function test_can_update_persona(): void
    {
        $user = User::factory()->create();
        $persona = Persona::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->put("/personas/{$persona->id}", [
            'name' => 'Updated Persona',
            'system_prompt' => 'Be precise and concise.',
            'temperature' => 1.1,
            'notes' => 'Internal note',
        ]);

        $response->assertRedirect('/personas');
        $this->assertDatabaseHas('personas', [
            'id' => $persona->id,
            'name' => 'Updated Persona',
            'notes' => 'Internal note',
        ]);
    }
}
