<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonalTokenRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class PersonalAccessTokenController extends Controller
{
    public function index(): Response
    {
        $tokens = auth()->user()
            ->tokens()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'created_at' => $token->created_at,
                'last_used_at' => $token->last_used_at,
            ]);

        return Inertia::render('PersonalTokens/Index', [
            'tokens' => $tokens,
            'newToken' => session('newToken'),
        ]);
    }

    public function store(StorePersonalTokenRequest $request): RedirectResponse
    {
        $token = $request->user()->createToken($request->name);

        return redirect()->route('personal-tokens.index')
            ->with('newToken', $token->plainTextToken);
    }

    public function destroy(PersonalAccessToken $personalAccessToken): RedirectResponse
    {
        abort_if($personalAccessToken->tokenable_id !== auth()->id(), 403);

        $personalAccessToken->delete();

        return redirect()->route('personal-tokens.index');
    }
}
