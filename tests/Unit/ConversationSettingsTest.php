<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_openai_settings_force_default_temperature(): void
    {
        $personaA = Persona::factory()->create(['temperature' => 0.3]);
        $personaB = Persona::factory()->create(['temperature' => 1.7]);

        $conversation = Conversation::factory()->create([
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'provider_a' => 'openai',
            'provider_b' => 'deepseek',
            'temp_a' => 0.3,
            'temp_b' => 1.7,
        ]);

        $conversation->load(['personaA', 'personaB']);

        $settingsA = $conversation->settingsForPersona($personaA);
        $settingsB = $conversation->settingsForPersona($personaB);

        $this->assertSame('openai', $settingsA['provider']);
        $this->assertSame(1.0, $settingsA['temperature']);

        $this->assertSame('deepseek', $settingsB['provider']);
        $this->assertSame(1.7, $settingsB['temperature']);
    }
}
