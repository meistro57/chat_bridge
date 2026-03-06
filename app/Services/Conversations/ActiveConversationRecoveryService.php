<?php

namespace App\Services\Conversations;

use App\Jobs\RunChatSession;
use App\Models\Conversation;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ActiveConversationRecoveryService
{
    /**
     * @return array{scanned:int,recovered:int,skipped_stop_signal:int,force_unlocked:int}
     */
    public function recoverStaleActiveConversations(?int $limit = null, string $source = 'scheduler'): array
    {
        $maxToScan = (int) ($limit ?? config('ai.active_conversation_auto_recovery_limit', 100));
        $maxToScan = max(1, $maxToScan);

        $activeConversations = Conversation::query()
            ->where('status', 'active')
            ->withCount([
                'messages as assistant_turns_count' => fn ($query) => $query->where('role', 'assistant'),
            ])
            ->orderBy('updated_at')
            ->limit($maxToScan)
            ->get([
                'id',
                'status',
                'max_rounds',
                'updated_at',
            ]);

        $summary = [
            'scanned' => $activeConversations->count(),
            'recovered' => 0,
            'skipped_stop_signal' => 0,
            'force_unlocked' => 0,
        ];

        foreach ($activeConversations as $conversation) {
            $stopRequested = $this->resolveStopRequested($conversation);
            if ($stopRequested) {
                $summary['skipped_stop_signal']++;

                continue;
            }

            $result = $this->maybeKickstartConversation(
                $conversation,
                (int) ($conversation->assistant_turns_count ?? 0),
                false,
                $source
            );

            if ($result['dispatched']) {
                $summary['recovered']++;
            }

            if ($result['force_unlocked']) {
                $summary['force_unlocked']++;
            }
        }

        return $summary;
    }

    /**
     * @return array{dispatched:bool,force_unlocked:bool}
     */
    public function maybeKickstartConversation(
        Conversation $conversation,
        int $assistantTurns,
        bool $stopRequested,
        string $source = 'interactive'
    ): array {
        if (! config('ai.active_conversation_auto_recovery_enabled', true)) {
            return [
                'dispatched' => false,
                'force_unlocked' => false,
            ];
        }

        if ($conversation->status !== 'active' || $stopRequested) {
            return [
                'dispatched' => false,
                'force_unlocked' => false,
            ];
        }

        $maxRounds = max(1, (int) ($conversation->max_rounds ?? 1));
        $remainingRounds = $maxRounds - max(0, $assistantTurns);
        if ($remainingRounds <= 0) {
            return [
                'dispatched' => false,
                'force_unlocked' => false,
            ];
        }

        $staleAfterSeconds = max(15, (int) config('ai.active_conversation_kickstart_after_seconds', 90));
        $updatedAt = $conversation->updated_at;
        if (! $updatedAt || $updatedAt->gt(now()->subSeconds($staleAfterSeconds))) {
            return [
                'dispatched' => false,
                'force_unlocked' => false,
            ];
        }

        $cooldownSeconds = max(30, (int) config('ai.active_conversation_kickstart_cooldown_seconds', 120));
        $forceUnlockAfterSeconds = max(
            $staleAfterSeconds + 30,
            (int) config('ai.active_conversation_force_unlock_after_seconds', 1800)
        );

        $forceUnlocked = false;
        if ($updatedAt->lte(now()->subSeconds($forceUnlockAfterSeconds))) {
            $forceUnlocked = $this->forceUnlockConversationLock($conversation, $cooldownSeconds, $source);
        }

        $kickstartKey = "conversation.kickstart.{$conversation->id}";
        try {
            $shouldDispatch = Cache::add($kickstartKey, now()->toIso8601String(), now()->addSeconds($cooldownSeconds));
        } catch (\Throwable $exception) {
            Log::warning('Unable to kickstart stale active conversation due to cache error', [
                'conversation_id' => $conversation->id,
                'error' => $exception->getMessage(),
                'source' => $source,
            ]);

            return [
                'dispatched' => false,
                'force_unlocked' => $forceUnlocked,
            ];
        }

        if (! $shouldDispatch) {
            return [
                'dispatched' => false,
                'force_unlocked' => $forceUnlocked,
            ];
        }

        dispatch(new RunChatSession($conversation->id, $remainingRounds));

        Log::info('Kickstarted stale active conversation', [
            'conversation_id' => $conversation->id,
            'assistant_turns' => $assistantTurns,
            'remaining_rounds' => $remainingRounds,
            'stale_after_seconds' => $staleAfterSeconds,
            'cooldown_seconds' => $cooldownSeconds,
            'force_unlocked' => $forceUnlocked,
            'source' => $source,
        ]);

        return [
            'dispatched' => true,
            'force_unlocked' => $forceUnlocked,
        ];
    }

    public function resolveStopRequested(Conversation $conversation): bool
    {
        try {
            return (bool) Cache::get("conversation.stop.{$conversation->id}");
        } catch (\Throwable $exception) {
            Log::warning('Unable to resolve stop signal for conversation recovery', [
                'conversation_id' => $conversation->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function forceUnlockConversationLock(Conversation $conversation, int $cooldownSeconds, string $source): bool
    {
        $unlockCooldownKey = "conversation.force_unlock.{$conversation->id}";

        try {
            $shouldUnlock = Cache::add($unlockCooldownKey, now()->toIso8601String(), now()->addSeconds($cooldownSeconds));
        } catch (\Throwable $exception) {
            Log::warning('Unable to evaluate force-unlock cooldown for conversation', [
                'conversation_id' => $conversation->id,
                'error' => $exception->getMessage(),
                'source' => $source,
            ]);

            return false;
        }

        if (! $shouldUnlock) {
            return false;
        }

        $lockKey = (new WithoutOverlapping("run-chat-session:{$conversation->id}"))
            ->getLockKey(new RunChatSession($conversation->id, 1));

        try {
            Cache::lock($lockKey, 1)->forceRelease();

            Log::warning('Force-released stale RunChatSession overlap lock', [
                'conversation_id' => $conversation->id,
                'lock_key' => $lockKey,
                'source' => $source,
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Failed to force-release stale RunChatSession overlap lock', [
                'conversation_id' => $conversation->id,
                'lock_key' => $lockKey,
                'error' => $exception->getMessage(),
                'source' => $source,
            ]);

            return false;
        }
    }
}
