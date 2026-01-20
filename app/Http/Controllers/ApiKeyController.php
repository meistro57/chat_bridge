<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Services\System\EnvFileService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApiKeyController extends Controller
{
    public function __construct(
        protected EnvFileService $envService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('ApiKeys/Index', [
            'apiKeys' => auth()->user()->apiKeys()->orderBy('provider')->latest()->get()->map(function ($key) {
                return [
                    'id' => $key->id,
                    'provider' => $key->provider,
                    'label' => $key->label,
                    'masked_key' => substr($key->key, 0, 8).'...'.substr($key->key, -4),
                    'is_active' => (bool) $key->is_active,
                    'is_validated' => (bool) $key->is_validated,
                    'last_validated_at' => $key->last_validated_at,
                    'validation_error' => $key->validation_error,
                    'created_at' => $key->created_at,
                ];
            }),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('ApiKeys/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider' => 'required|string',
            'key' => 'required|string',
            'label' => 'nullable|string',
        ]);

        auth()->user()->apiKeys()->create($validated);

        // Update .env
        $envKey = $this->envService->getEnvKey($validated['provider']);
        $this->envService->updateKey($envKey, $validated['key']);

        return redirect()->route('api-keys.index')->with('success', 'API Key added successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ApiKey $apiKey)
    {
        if ($apiKey->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('ApiKeys/Edit', [
            'apiKey' => [
                'id' => $apiKey->id,
                'provider' => $apiKey->provider,
                'label' => $apiKey->label,
                'is_active' => (bool) $apiKey->is_active,
                // Don't send the full key for security
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        if ($apiKey->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'provider' => 'required|string',
            'label' => 'nullable|string',
            'is_active' => 'boolean',
            'key' => 'nullable|string', // Optional, only if updating
        ]);

        $apiKey->fill([
            'provider' => $validated['provider'],
            'label' => $validated['label'],
            'is_active' => $validated['is_active'],
        ]);

        if (! empty($validated['key'])) {
            $apiKey->key = $validated['key'];
            
            // Update .env
            $envKey = $this->envService->getEnvKey($apiKey->provider);
            $this->envService->updateKey($envKey, $validated['key']);
        }

        $apiKey->save();

        return redirect()->route('api-keys.index')->with('success', 'API Key updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ApiKey $apiKey)
    {
        if ($apiKey->user_id !== auth()->id()) {
            abort(403);
        }

        $apiKey->delete();

        return redirect()->route('api-keys.index')->with('success', 'API Key deleted.');
    }

    /**
     * Test/validate an API key
     */
    public function test(ApiKey $apiKey)
    {
        if ($apiKey->user_id !== auth()->id()) {
            abort(403);
        }

        try {
            // Get the AI driver for this provider
            $driver = app('ai')->driver($apiKey->provider);

            // Test with a simple completion request
            $messages = [
                [
                    'role' => 'user',
                    'content' => 'Respond with only the word "OK" to confirm you are working.',
                ],
            ];

            $result = $driver->completion($messages);

            // If we get here without exception, the key is valid
            $apiKey->update([
                'is_validated' => true,
                'last_validated_at' => now(),
                'validation_error' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'API key validated successfully',
                'is_validated' => true,
                'last_validated_at' => $apiKey->last_validated_at,
            ]);
        } catch (\Exception $e) {
            // Key is invalid or there was an error
            $apiKey->update([
                'is_validated' => false,
                'last_validated_at' => now(),
                'validation_error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'API key validation failed',
                'error' => $e->getMessage(),
                'is_validated' => false,
                'last_validated_at' => $apiKey->last_validated_at,
            ], 422);
        }
    }
}
