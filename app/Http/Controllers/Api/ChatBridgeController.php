<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatBridgeRespondRequest;
use App\Neuron\Agents\ChatBridgeAgent;
use App\Services\ChatBridge\HistoryStore;
use NeuronAI\Chat\Messages\UserMessage;

class ChatBridgeController extends Controller
{
    public function __construct(
        protected HistoryStore $historyStore
    ) {}

    public function respond(ChatBridgeRespondRequest $request)
    {
        $threadId = $request->input('bridge_thread_id');
        $userMessage = $request->input('message');
        $persona = $request->input('persona');

        // 1. Get/Create Thread
        $thread = $this->historyStore->getOrCreateThread($threadId);

        // 2. Persist User Message
        $this->historyStore->appendMessage($thread, 'user', $userMessage, $request->input('metadata'));

        // 3. Prepare Agent
        $agent = app(ChatBridgeAgent::class);
        $agent->setPersona($persona);

        // 4. Load History (Agent usually processes history internally if passed, 
        // OR we manually feed it. Neuron Agent usually has run($messages) or interactions.
        // We will fetch history and prep it.)
        $history = $this->historyStore->fetchRecentMessages($thread);
        
        // Note: fetchRecentMessages includes the just-saved user message? 
        // Yes, if we saved it first. 
        // HOWEVER, Neuron Agent::run usually takes the NEW message.
        // Let's see: $response = $agent->chat($message, $history);
        // Or if $history includes the message, maybe $agent->chat(null, $history)?
        // Let's assume standard: chat(string $input, array $previousMessages = [])
        
        // We filter out the last user message from history to avoid duplication if we pass it as prompt
        // OR we pass the full history including the last message and pass empty prompt?
        // Let's try passing the user message explicitly and history WITHOUT it.
        
        // Remove the last message from history if it matches current input (it should)
        array_pop($history); 

        $response = $agent->chat(new UserMessage($userMessage), $history);
        
        $assistantContent = (string) $response->getContent();

        // 5. Persist Assistant Message
        $this->historyStore->appendMessage($thread, 'assistant', $assistantContent);

        return response()->json([
            'bridge_thread_id' => $threadId,
            'assistant_message' => $assistantContent,
            'thread_db_id' => $thread->id,
            // 'usage' => ... // if available
        ]);
    }
}
