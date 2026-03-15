<?php

namespace App\Services\Orchestrator;

use App\Models\Orchestration;
use App\Models\OrchestratorStep;
use App\Models\User;
use App\Services\AI\AIManager;
use App\Services\AI\Data\MessageData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OrchestratorWizardService
{
    public function __construct(
        protected AIManager $ai
    ) {}

    /**
     * Send a wizard message and get the AI reply.
     * Returns a reply string, whether the draft is complete, and the draft JSON if done.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, done: bool, orchestration_draft: array<string, mixed>|null}
     */
    public function chat(User $user, array $history, string $userMessage): array
    {
        $systemPrompt = $this->buildSystemPrompt($user);

        $messages = collect([new MessageData('system', $systemPrompt)]);

        foreach ($history as $turn) {
            $messages->push(new MessageData($turn['role'], $turn['content']));
        }

        $messages->push(new MessageData('user', $userMessage));

        try {
            $driver = $this->ai->createAnthropicDriver();
            $response = $driver->chat($messages);
            $reply = $response->content;
        } catch (\Throwable $e) {
            Log::warning('OrchestratorWizard AI call failed, falling back to default driver', [
                'error' => $e->getMessage(),
            ]);

            $driver = $this->ai->driverForProvider(config('ai.default', 'openai'));
            $response = $driver->chat($messages);
            $reply = $response->content;
        }

        $draft = $this->extractDraft($reply);

        return [
            'reply' => $reply,
            'done' => $draft !== null,
            'orchestration_draft' => $draft,
        ];
    }

    /**
     * Materialize a wizard draft into DB records.
     *
     * @param  array<string, mixed>  $draft
     */
    public function materialize(User $user, array $draft): Orchestration
    {
        $orchestration = Orchestration::create([
            'user_id' => $user->id,
            'name' => $draft['name'] ?? 'Untitled Orchestration',
            'description' => $draft['description'] ?? null,
            'goal' => $draft['goal'] ?? null,
            'is_scheduled' => $draft['is_scheduled'] ?? false,
            'cron_expression' => $draft['cron_expression'] ?? null,
            'timezone' => $draft['timezone'] ?? 'UTC',
            'status' => 'idle',
        ]);

        foreach ($draft['steps'] ?? [] as $index => $stepData) {
            OrchestratorStep::create([
                'orchestration_id' => $orchestration->id,
                'step_number' => $stepData['step_number'] ?? ($index + 1),
                'label' => $stepData['label'] ?? null,
                'template_id' => $stepData['template_id'] ?? null,
                'persona_a_id' => $stepData['persona_a_id'] ?? null,
                'persona_b_id' => $stepData['persona_b_id'] ?? null,
                'provider_a' => $stepData['provider_a'] ?? null,
                'model_a' => $stepData['model_a'] ?? null,
                'provider_b' => $stepData['provider_b'] ?? null,
                'model_b' => $stepData['model_b'] ?? null,
                'input_source' => $stepData['input_source'] ?? 'static',
                'input_value' => $stepData['input_value'] ?? null,
                'input_variable_name' => $stepData['input_variable_name'] ?? null,
                'output_action' => $stepData['output_action'] ?? 'log',
                'output_variable_name' => $stepData['output_variable_name'] ?? null,
                'output_webhook_url' => $stepData['output_webhook_url'] ?? null,
                'condition' => $stepData['condition'] ?? null,
                'pause_before_run' => $stepData['pause_before_run'] ?? false,
            ]);
        }

        return $orchestration->load('steps');
    }

    /**
     * Build the dynamic system prompt for the wizard.
     */
    protected function buildSystemPrompt(User $user): string
    {
        $personas = $user->personas()->pluck('name', 'id')
            ->map(fn ($name, $id) => "{$name} (ID: {$id})")
            ->values()
            ->implode(', ');

        $templates = \App\Models\ConversationTemplate::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)->orWhere('is_public', true);
            })
            ->pluck('name', 'id')
            ->map(fn ($name, $id) => "{$name} (ID: {$id})")
            ->values()
            ->implode(', ');

        return <<<PROMPT
You are an AI orchestration assistant for ChatBridge. Help the user design a sequence of AI conversation tasks.

Available personas: {$personas}
Available templates: {$templates}
Available providers: openai, anthropic, gemini, openrouter, deepseek, ollama

Ask clarifying questions one at a time until you understand:
1. The overall goal
2. Each step (what it does, which template or new template to use, which personas/providers)
3. Input/output wiring between steps
4. Whether to schedule and how often

When you have enough information, respond with JSON inside <orchestration> tags:
<orchestration>
{
  "name": "...",
  "description": "...",
  "goal": "...",
  "is_scheduled": false,
  "cron_expression": null,
  "timezone": "UTC",
  "steps": [
    {
      "step_number": 1,
      "label": "...",
      "template_id": null,
      "persona_a_id": null,
      "persona_b_id": null,
      "provider_a": null,
      "model_a": null,
      "provider_b": null,
      "model_b": null,
      "input_source": "static",
      "input_value": "...",
      "input_variable_name": null,
      "output_action": "pass_to_next",
      "output_variable_name": null,
      "output_webhook_url": null,
      "condition": null,
      "pause_before_run": false
    }
  ]
}
</orchestration>
PROMPT;
    }

    /**
     * Extract the orchestration draft JSON from an AI reply.
     *
     * @return array<string, mixed>|null
     */
    protected function extractDraft(string $reply): ?array
    {
        if (! preg_match('/<orchestration>(.*?)<\/orchestration>/s', $reply, $matches)) {
            return null;
        }

        $json = trim($matches[1]);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
