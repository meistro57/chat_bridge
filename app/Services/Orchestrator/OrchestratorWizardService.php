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
     * Creates any new personas defined in the draft, then builds the orchestration and steps.
     *
     * @param  array<string, mixed>  $draft
     */
    public function materialize(User $user, array $draft): Orchestration
    {
        // Resolve persona refs → real IDs, creating new personas as needed.
        $personaMap = $this->resolvePersonas($user, $draft['new_personas'] ?? []);

        $orchestration = Orchestration::create([
            'user_id' => $user->id,
            'name' => $draft['name'] ?? 'Untitled Orchestration',
            'description' => $draft['description'] ?? null,
            'goal' => $draft['goal'] ?? null,
            'is_scheduled' => $draft['is_scheduled'] ?? false,
            'cron_expression' => $draft['cron_expression'] ?? null,
            'timezone' => $draft['timezone'] ?? 'UTC',
            'status' => 'idle',
            'metadata' => [
                'discord_streaming_enabled' => (bool) ($draft['discord_streaming_enabled'] ?? false),
                'discourse_streaming_enabled' => (bool) ($draft['discourse_streaming_enabled'] ?? false),
            ],
        ]);
        foreach ($draft['steps'] ?? [] as $index => $stepData) {
            $personaAId = $this->resolvePersonaId($stepData, 'persona_a', $personaMap);
            $personaBId = $this->resolvePersonaId($stepData, 'persona_b', $personaMap);

            OrchestratorStep::create([
                'orchestration_id' => $orchestration->id,
                'step_number' => $stepData['step_number'] ?? ($index + 1),
                'label' => $stepData['label'] ?? null,
                'template_id' => $stepData['template_id'] ?? null,
                'persona_a_id' => $personaAId,
                'persona_b_id' => $personaBId,
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
     * Create new personas from the draft and return a ref → ID map.
     * Refs that are existing UUIDs are passed through directly.
     *
     * @param  array<int, array<string, mixed>>  $newPersonas
     * @return array<string, string>
     */
    protected function resolvePersonas(User $user, array $newPersonas): array
    {
        $map = [];

        foreach ($newPersonas as $personaData) {
            $ref = $personaData['ref'] ?? null;

            if (! $ref) {
                continue;
            }

            $persona = \App\Models\Persona::create([
                'user_id' => $user->id,
                'name' => $personaData['name'] ?? 'Unnamed Persona',
                'system_prompt' => $personaData['system_prompt'] ?? '',
                'guidelines' => $personaData['guidelines'] ?? null,
                'temperature' => $personaData['temperature'] ?? 1.0,
            ]);

            $map[$ref] = $persona->id;
        }

        return $map;
    }

    /**
     * Resolve a persona ID from step data, checking ref map then falling back to direct ID.
     *
     * @param  array<string, mixed>  $stepData
     * @param  array<string, string>  $personaMap
     */
    protected function resolvePersonaId(array $stepData, string $key, array $personaMap): ?string
    {
        $ref = $stepData["{$key}_ref"] ?? null;

        if ($ref && isset($personaMap[$ref])) {
            return $personaMap[$ref];
        }

        // Ref might be a raw UUID for an existing persona
        if ($ref) {
            return $ref;
        }

        return $stepData["{$key}_id"] ?? null;
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

        $existingPersonas = $personas ?: 'None yet';
        $existingTemplates = $templates ?: 'None yet';

        return <<<PROMPT
You are an AI orchestration assistant for ChatBridge. Help the user design a sequence of AI conversation tasks.

Existing personas: {$existingPersonas}
Existing templates: {$existingTemplates}
Available providers: openai, anthropic, gemini, openrouter, deepseek, ollama, lmstudio
IMPORTANT: Always set model_a and model_b to null in the draft JSON. The user will select the correct model in the review step.

Each orchestration step runs a two-persona AI conversation. Every step needs persona_a and persona_b.
You can either use an existing persona (by ID) or define a brand-new one inline.

Ask clarifying questions one at a time until you understand:
1. The overall goal
2. Each step — what it does, and what role each persona plays (create new personas with detailed system prompts if needed)
3. Input/output wiring between steps
4. Whether to schedule and how often

When you have enough information, respond with JSON inside <orchestration> tags.
Use "persona_a_ref" / "persona_b_ref" to reference a persona by its ref key.
Define new personas in the top-level "new_personas" array; reference existing ones with ref = their UUID.

<orchestration>
{
  "name": "...",
  "description": "...",
  "goal": "...",
  "is_scheduled": false,
  "cron_expression": null,
  "timezone": "UTC",
  "discord_streaming_enabled": false,
  "discourse_streaming_enabled": false,
  "new_personas": [
    {
      "ref": "persona_researcher",
      "name": "Research Analyst",
      "system_prompt": "You are a thorough research analyst. Your job is to ...",
      "guidelines": null,
      "temperature": 0.7
    }
  ],
  "steps": [
    {
      "step_number": 1,
      "label": "...",
      "template_id": null,
      "persona_a_ref": "persona_researcher",
      "persona_b_ref": "existing-uuid-here",
      "provider_a": "openai",
      "model_a": null,
      "provider_b": "openai",
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
