<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApiKeyController extends Controller
{
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
}
