<?php

namespace Tests\Feature;

use App\Neuron\Agents\ChatBridgeAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use NeuronAI\Chat\Messages\Message; // Import Message
use NeuronAI\Chat\Messages\UserMessage;
use Tests\TestCase;

class ChatBridgeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_respond_endpoint_creates_thread_and_messages()
    {
        // Setup Auth
        putenv('CHAT_BRIDGE_TOKEN=secret123');

        // Mock Agent
        $this->mock(ChatBridgeAgent::class, function (MockInterface $mock) {
            $mock->shouldReceive('setPersona')->once()->andReturnSelf();

            // Mock the chat() method to return a response object with getContent()
            $mockResponse = Mockery::mock(Message::class); // Mock Message specifically
            $mockResponse->shouldReceive('getContent')->once()->andReturn('Hello from Agent');

            $mock->shouldReceive('chat')
                ->once()
                ->with(Mockery::type(UserMessage::class), Mockery::type('array'))
                ->andReturn($mockResponse);
        });

        // Make Request
        $response = $this->postJson('/api/chat-bridge/respond', [
            'bridge_thread_id' => 'thread-123',
            'message' => 'Hello World',
            'persona' => 'Be kind',
        ], [
            'X-CHAT-BRIDGE-TOKEN' => 'secret123',
        ]);

        // Assertions
        $response->assertStatus(200);
        $response->assertJson([
            'bridge_thread_id' => 'thread-123',
            'assistant_message' => 'Hello from Agent',
        ]);

        $this->assertDatabaseHas('chat_bridge_threads', [
            'bridge_thread_id' => 'thread-123',
        ]);

        $this->assertDatabaseHas('chat_bridge_messages', [
            'content' => 'Hello World',
            'role' => 'user',
        ]);

        $this->assertDatabaseHas('chat_bridge_messages', [
            'content' => 'Hello from Agent',
            'role' => 'assistant',
        ]);

        // Clean environment
        putenv('CHAT_BRIDGE_TOKEN');
    }

    public function test_respond_endpoint_unauthorized_without_token()
    {
        putenv('CHAT_BRIDGE_TOKEN=secret123');

        $response = $this->postJson('/api/chat-bridge/respond', [
            'bridge_thread_id' => 'abc',
            'message' => 'hi',
        ]);

        $response->assertStatus(401);
    }
}
