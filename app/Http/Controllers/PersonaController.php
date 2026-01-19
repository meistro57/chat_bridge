<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PersonaController extends Controller
{
    public function index()
    {
        return Inertia::render('Personas/Index', [
            'personas' => auth()->user()->personas()->latest()->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Personas/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:personas,name',
            'provider' => 'required|string',
            'model' => 'nullable|string',
            'system_prompt' => 'required|string',
            'guidelines' => 'nullable|array',
            'temperature' => 'required|numeric|min:0|max:2',
            'notes' => 'nullable|string',
        ]);

        auth()->user()->personas()->create($validated);

        return redirect()->route('personas.index')->with('success', 'Persona created.');
    }

    public function edit(Persona $persona)
    {
        if ($persona->user_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('Personas/Edit', [
            'persona' => $persona,
        ]);
    }

    public function update(Request $request, Persona $persona)
    {
        if ($persona->user_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|unique:personas,name,' . $persona->id,
            'provider' => 'required|string',
            'model' => 'nullable|string',
            'system_prompt' => 'required|string',
            'guidelines' => 'nullable|array',
            'temperature' => 'required|numeric|min:0|max:2',
            'notes' => 'nullable|string',
        ]);

        $persona->update($validated);

        return redirect()->route('personas.index')->with('success', 'Persona updated.');
    }

    public function destroy(Persona $persona)
    {
        if ($persona->user_id !== auth()->id()) {
            abort(403);
        }

        $persona->delete();

        return redirect()->route('personas.index')->with('success', 'Persona deleted.');
    }
}
