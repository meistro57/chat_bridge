<?php

namespace Tests\Feature;

use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonaTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_personas(): void
    {
        $response = $this->get('/personas');
        $response->assertStatus(200);
    }

    public function test_can_create_persona(): void
    {
        $this->withoutMiddleware();
        $response = $this->post('/personas', [
            'name' => 'Test Persona',
            'provider' => 'openai',
            'system_prompt' => 'Be helpful',
            'temperature' => 0.7,
        ]);

        $response->assertRedirect('/personas');
        $this->assertDatabaseHas('personas', ['name' => 'Test Persona']);
    }
}
