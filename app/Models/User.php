<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'bio',
        'avatar',
        'password',
        'role',
        'is_active',
        'notification_preferences',
        'discord_webhook_url',
        'discord_streaming_default',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'notification_preferences' => 'json',
            'discord_streaming_default' => 'boolean',
        ];
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function conversationTemplates(): HasMany
    {
        return $this->hasMany(ConversationTemplate::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Get the user's notification preferences with defaults.
     *
     * @return array{conversation_completed: bool, conversation_failed: bool}
     */
    public function getNotificationPrefs(): array
    {
        $defaults = [
            'conversation_completed' => true,
            'conversation_failed' => true,
        ];

        return array_merge($defaults, $this->notification_preferences ?? []);
    }

    /**
     * Check if a specific notification type is enabled.
     */
    public function wantsNotification(string $type): bool
    {
        return (bool) ($this->getNotificationPrefs()[$type] ?? true);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        return Storage::disk('public')->url($this->avatar);
    }
}
