<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneratePersonaRequest;
use App\Models\Conversation;
use App\Models\Persona;
use App\Services\AI\AIManager;
use App\Services\AI\Data\MessageData;
use App\Services\AI\Drivers\MockDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PersonaController extends Controller
{
    public function index(): InertiaResponse
    {
        $personas = Persona::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('is_favorite')
            ->latest()
            ->get();
        $sessionCountsA = Conversation::query()
            ->where('user_id', auth()->id())
            ->whereNotNull('persona_a_id')
            ->selectRaw('persona_a_id as persona_id, COUNT(*) as count')
            ->groupBy('persona_a_id')
            ->pluck('count', 'persona_id');

        $sessionCountsB = Conversation::query()
            ->where('user_id', auth()->id())
            ->whereNotNull('persona_b_id')
            ->selectRaw('persona_b_id as persona_id, COUNT(*) as count')
            ->groupBy('persona_b_id')
            ->pluck('count', 'persona_id');

        $personas = $personas->map(function (Persona $persona) use ($sessionCountsA, $sessionCountsB) {
            $countA = (int) ($sessionCountsA[$persona->id] ?? 0);
            $countB = (int) ($sessionCountsB[$persona->id] ?? 0);
            $persona->setAttribute('sessions_count', $countA + $countB);

            return $persona;
        });

        return Inertia::render('Personas/Index', [
            'personas' => $personas,
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Personas/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:personas,name',
            'system_prompt' => 'required|string',
            'guidelines' => 'nullable|array',
            'temperature' => 'required|numeric|min:0|max:2',
            'notes' => 'nullable|string',
        ]);

        auth()->user()->personas()->create([
            ...$validated,
            'is_favorite' => true,
        ]);

        return redirect()->route('personas.index')->with('success', 'Persona created.');
    }

    public function edit(Persona $persona): InertiaResponse
    {
        if ($persona->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('Personas/Edit', [
            'persona' => $persona,
        ]);
    }

    public function update(Request $request, Persona $persona): RedirectResponse
    {
        if ($persona->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:personas,name,'.$persona->id,
            'system_prompt' => 'required|string',
            'guidelines' => 'nullable|array',
            'temperature' => 'required|numeric|min:0|max:2',
            'notes' => 'nullable|string',
        ]);

        $persona->update($validated);

        return redirect()->route('personas.index')->with('success', 'Persona updated.');
    }

    public function destroy(Persona $persona): RedirectResponse
    {
        if ($persona->user_id !== auth()->id()) {
            abort(403);
        }

        $persona->delete();

        return redirect()->route('personas.index')->with('success', 'Persona deleted.');
    }

    public function toggleFavorite(Request $request, Persona $persona): JsonResponse|RedirectResponse
    {
        if ($persona->user_id !== auth()->id()) {
            abort(403);
        }

        $persona->update([
            'is_favorite' => ! $persona->is_favorite,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'is_favorite' => (bool) $persona->is_favorite,
            ]);
        }

        return back()->with('success', 'Persona favorite status updated.');
    }

    public function clearFavorites(Request $request): JsonResponse|RedirectResponse
    {
        Persona::query()
            ->where('user_id', auth()->id())
            ->where('is_favorite', true)
            ->update(['is_favorite' => false]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
            ]);
        }

        return redirect()->route('personas.index')->with('success', 'All persona favorites cleared.');
    }

    public function generate(GeneratePersonaRequest $request): JsonResponse
    {
        $openAiDriver = app(AIManager::class)->createOpenAIDriver(
            (string) config('services.openai.model', 'gpt-4o-mini')
        );

        if ($openAiDriver instanceof MockDriver) {
            return response()->json([
                'message' => 'OpenAI service API key is not configured.',
            ], 422);
        }

        $validated = $request->validated();
        $idea = trim((string) ($validated['idea'] ?? ''));
        $tone = trim((string) ($validated['tone'] ?? ''));
        $audience = trim((string) ($validated['audience'] ?? ''));
        $style = trim((string) ($validated['style'] ?? ''));
        $constraints = trim((string) ($validated['constraints'] ?? ''));

        $contextLines = [
            'Persona concept: '.$idea,
            $tone !== '' ? 'Tone: '.$tone : null,
            $audience !== '' ? 'Audience: '.$audience : null,
            $style !== '' ? 'Style: '.$style : null,
            $constraints !== '' ? 'Constraints: '.$constraints : null,
        ];
        $context = collect($contextLines)->filter()->implode("\n");

        $messages = collect([
            new MessageData('system', "You generate production-ready AI personas for a chat application.\nReturn strict JSON with keys: name, system_prompt.\nRules:\n- name: short, memorable, 2-4 words, no quotes.\n- system_prompt: detailed, actionable behavior instructions, 120-260 words.\n- system_prompt must include role, objectives, style, boundaries, and response quality rules.\n- No markdown. No extra keys. JSON only."),
            new MessageData('user', $context),
        ]);

        $parsed = [];

        try {
            $response = $openAiDriver->chat($messages, 0.7);
            $parsed = $this->extractPersonaJson($response->content);
        } catch (\Throwable $exception) {
            if ($this->shouldFallbackToOpenRouter($exception)) {
                try {
                    $openAiModel = (string) config('services.openai.model', 'gpt-4o-mini');
                    $openRouterModel = $this->toOpenRouterModel($openAiModel);

                    Log::warning('Persona generation falling back to OpenRouter after OpenAI quota error', [
                        'openai_model' => $openAiModel,
                        'openrouter_model' => $openRouterModel,
                    ]);

                    $fallbackDriver = app(AIManager::class)->createOpenRouterDriver($openRouterModel);

                    if ($fallbackDriver instanceof MockDriver) {
                        return response()->json([
                            'message' => 'Failed to generate persona. OpenAI credits were exhausted and no OpenRouter API key is configured (user or system).',
                        ], 422);
                    }

                    $response = $fallbackDriver->chat($messages, 0.7);
                    $parsed = $this->extractPersonaJson($response->content);
                } catch (\Throwable $fallbackException) {
                    return response()->json([
                        'message' => 'Failed to generate persona. OpenAI credits were exhausted and OpenRouter fallback failed. '.$fallbackException->getMessage(),
                    ], 422);
                }
            }

            if ($parsed === []) {
                return response()->json([
                    'message' => 'Failed to generate persona. '.$exception->getMessage(),
                ], 422);
            }
        }

        if (! isset($parsed['name'], $parsed['system_prompt'])) {
            return response()->json([
                'message' => 'AI response did not include a valid persona name and system prompt.',
            ], 422);
        }

        return response()->json([
            'name' => $parsed['name'],
            'system_prompt' => $parsed['system_prompt'],
        ]);
    }

    /**
     * @return array{name:string,system_prompt:string}|array{}
     */
    private function extractPersonaJson(string $raw): array
    {
        $candidate = trim($raw);

        if (str_starts_with($candidate, '```')) {
            $candidate = preg_replace('/^```(?:json)?\s*/', '', $candidate) ?? $candidate;
            $candidate = preg_replace('/\s*```$/', '', $candidate) ?? $candidate;
            $candidate = trim($candidate);
        }

        $decoded = json_decode($candidate, true);

        if (! is_array($decoded)) {
            preg_match('/\{(?:[^{}]|(?R))*\}/s', $candidate, $matches);
            $decoded = isset($matches[0]) ? json_decode($matches[0], true) : null;
        }

        if (! is_array($decoded)) {
            return [];
        }

        $name = trim((string) ($decoded['name'] ?? ''));
        $systemPrompt = trim((string) ($decoded['system_prompt'] ?? ''));

        if ($name === '' || $systemPrompt === '') {
            return [];
        }

        return [
            'name' => mb_substr($name, 0, 80),
            'system_prompt' => mb_substr($systemPrompt, 0, 4000),
        ];
    }

    private function shouldFallbackToOpenRouter(\Throwable $exception): bool
    {
        $patterns = [
            'insufficient_quota',
            'exceeded your current quota',
            'billing_hard_limit_reached',
            'billing hard limit',
            'credit balance is too low',
            'not enough credits',
            'quota exceeded',
            'status code 429',
            'http request returned status code 429',
        ];

        $current = $exception;
        while ($current !== null) {
            if ((int) $current->getCode() === 429) {
                return true;
            }

            $message = strtolower($current->getMessage());

            foreach ($patterns as $pattern) {
                if (str_contains($message, $pattern)) {
                    return true;
                }
            }

            $current = $current->getPrevious();
        }

        return false;
    }

    private function toOpenRouterModel(string $openAiModel): string
    {
        if (str_contains($openAiModel, '/')) {
            return $openAiModel;
        }

        return 'openai/'.$openAiModel;
    }
}
