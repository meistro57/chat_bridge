<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{id}', function ($user, string $id) {
    if ($user === null) {
        return false;
    }

    return Conversation::query()
        ->whereKey($id)
        ->where('user_id', $user->id)
        ->exists();
});
