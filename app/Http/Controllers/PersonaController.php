<?php

namespace App\Http\Controllers;

use App\Http\Requests\GeneratePersonaRequest;
use App\Models\Persona;
use App\Services\AI\Data\MessageData;
use App\Services\AI\Drivers\OpenAIDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PersonaController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Personas/Index', [
            'personas' => Persona::latest()->get(),
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

        auth()->user()->personas()->create($validated);

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

    public function generate(GeneratePersonaRequest $request): JsonResponse
    {
        $openAiKey = (string) config('services.openai.key', '');

        if ($openAiKey === '') {
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

        try {
            $driver = new OpenAIDriver(
                apiKey: $openAiKey,
                model: (string) config('services.openai.model', 'gpt-4o-mini')
            );
            $response = $driver->chat($messages, 0.7);
            $parsed = $this->extractPersonaJson($response->content);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => 'Failed to generate persona. '.$exception->getMessage(),
            ], 422);
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
}
