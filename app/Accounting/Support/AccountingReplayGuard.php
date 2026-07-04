<?php
declare(strict_types=1);

namespace App\Accounting\Support;

use App\Accounting\Core\AccountingResult;

/**
 * Replay idempotency — prevents duplicate journal writes during event replay (including force).
 * Does not modify pipeline or replay engine behavior.
 */
final class AccountingReplayGuard
{
    /**
     * @param array<string, mixed> $event
     */
    public static function isReplay(array $event): bool
    {
        $meta = $event['metadata'] ?? [];

        return is_array($meta) && !empty($meta['replay']);
    }

    /**
     * @param array<string, mixed> $event
     * @param array<string, mixed> $data
     */
    public static function replayAcknowledged(
        array $event,
        string $sourceSystem,
        string $message,
        array $data = []
    ): AccountingResult {
        $meta = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];

        return AccountingResult::ok(array_merge([
            'mode' => 'replay_idempotent',
            'source_system' => $sourceSystem,
            'journal_entry_id' => $meta['journal_entry_id'] ?? null,
            'ledger_journal_id' => $meta['ledger_journal_id'] ?? null,
            'reference_type' => $event['reference_type'] ?? null,
            'reference_id' => $event['reference_id'] ?? null,
        ], $data), $message);
    }
}
