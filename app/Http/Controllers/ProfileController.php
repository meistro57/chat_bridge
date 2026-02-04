<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Message;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $conversationIds = $user->conversations()->pluck('id');

        $stats = [
            'total_conversations' => $user->conversations()->count(),
            'total_personas' => $user->personas()->count(),
            'total_api_keys' => $user->apiKeys()->count(),
            'total_messages' => Message::whereIn('conversation_id', $conversationIds)->count(),
            'total_tokens' => (int) Message::whereIn('conversation_id', $conversationIds)->sum('tokens_used'),
            'completed_conversations' => $user->conversations()->where('status', 'completed')->count(),
        ];

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'stats' => $stats,
            'notificationPreferences' => $user->getNotificationPrefs(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Update the user's notification preferences.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'conversation_completed' => ['required', 'boolean'],
            'conversation_failed' => ['required', 'boolean'],
        ]);

        $request->user()->update([
            'notification_preferences' => $validated,
        ]);

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
