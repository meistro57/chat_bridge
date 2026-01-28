<?php

namespace App\Http\Controllers;

use App\Models\Persona;
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
}
