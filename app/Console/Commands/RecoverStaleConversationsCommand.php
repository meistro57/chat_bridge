<?php

namespace App\Console\Commands;

use App\Services\Conversations\ActiveConversationRecoveryService;
use Illuminate\Console\Command;

class RecoverStaleConversationsCommand extends Command
{
    protected $signature = 'chat:recover-stale {--limit=0 : Max active conversations to scan (0 uses config default)}';

    protected $description = 'Auto-recover stale active conversations by re-dispatching stuck sessions';

    public function handle(ActiveConversationRecoveryService $recoveryService): int
    {
        if (! config('ai.active_conversation_auto_recovery_enabled', true)) {
            $this->line('Stale conversation auto-recovery is disabled.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $summary = $recoveryService->recoverStaleActiveConversations($limit > 0 ? $limit : null, 'scheduler');

        $this->line(sprintf(
            'Scanned %d active conversations, recovered %d, skipped %d due to stop signal, force-unlocked %d stale locks.',
            $summary['scanned'],
            $summary['recovered'],
            $summary['skipped_stop_signal'],
            $summary['force_unlocked']
        ));

        return self::SUCCESS;
    }
}
