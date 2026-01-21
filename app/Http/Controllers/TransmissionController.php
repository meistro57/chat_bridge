<?php

namespace App\Http\Controllers;

use App\Models\Transmission;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransmissionController extends Controller
{
    /**
     * Display a listing of transmissions.
     */
    public function index(Request $request)
    {
        $transmissions = Transmission::query()
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Transmission/Index', [
            'transmissions' => $transmissions,
        ]);
    }

    /**
     * Store a new transmission.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'destination' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'nullable|in:low,medium,high',
            'method' => 'nullable|string|max:50',
        ]);

        $transmission = Transmission::create([
            'user_id' => auth()->id(),
            'destination' => $validated['destination'],
            'message' => $validated['message'],
            'priority' => $validated['priority'] ?? 'medium',
            'method' => $validated['method'] ?? 'default',
            'status' => 'pending',
        ]);

        return redirect()->route('transmission.index')
            ->with('success', 'Transmission created successfully.');
    }
}
