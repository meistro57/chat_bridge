<?php

namespace Tests\Unit;

use App\Models\Conversation;
use App\Models\Persona;
use App\Models\User;
use App\Services\AI\TranscriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscriptServiceTest extends TestCase
{
    use RefreshDatabase;

    private TranscriptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = new TranscriptService;
    }

    public function test_transcript_includes_rag_section_with_attached_filenames(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create(['name' => 'Alpha']);
        $personaB = Persona::factory()->create(['name' => 'Beta']);

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'metadata' => [
                'rag' => [
                    'enabled' => true,
                    'source_limit' => 6,
                    'score_threshold' => 0.3,
                    'files' => [
                        'session-rag/1/abc/research-notes-uuid.txt',
                        'template-rag/1/42/context-uuid.pdf',
                    ],
                ],
            ],
        ]);

        $conversation->messages()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'Start the conversation.',
        ]);

        $path = $this->service->generate($conversation);
        $content = Storage::disk('local')->get($path);

        $this->assertStringContainsString('## RAG Configuration', $content);
        $this->assertStringContainsString('Cross-Chat Memory**: Enabled', $content);
        $this->assertStringContainsString('`research-notes-uuid.txt`', $content);
        $this->assertStringContainsString('`context-uuid.pdf`', $content);
    }

    public function test_transcript_rag_section_shows_none_when_no_files_attached(): void
    {
        $user = User::factory()->create();
        $personaA = Persona::factory()->create();
        $personaB = Persona::factory()->create();

        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'persona_a_id' => $personaA->id,
            'persona_b_id' => $personaB->id,
            'metadata' => [
                'rag' => [
                    'enabled' => false,
                    'source_limit' => 6,
                    'score_threshold' => 0.3,
                    'files' => [],
                ],
            ],
        ]);

        $conversation->messages()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'Start.',
        ]);

        $path = $this->service->generate($conversation);
        $content = Storage::disk('local')->get($path);

        $this->assertStringContainsString('## RAG Configuration', $content);
        $this->assertStringContainsString('Cross-Chat Memory**: Disabled', $content);
        $this->assertStringContainsString('Attached Files**: None', $content);
    }
}
