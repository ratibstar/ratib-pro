<?php
declare(strict_types=1);

namespace App\Accounting\Support;

use App\Accounting\Core\AccountingResult;
use App\Accounting\Infrastructure\AccountingConnectionFactory;

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
     * Universal replay gate — call before any gateway-first journal INSERT.
     * Returns AccountingResult when write must be skipped; null when write may proceed.
     *
     * @param array<string, mixed> $event
     * @param callable(array): (?int) $resolveJournalByReference Optional adapter-specific journal lookup
     */
    public static function gateBeforeJournalWrite(
        array $event,
        string $sourceSystem,
        ?callable $resolveJournalByReference = null
    ): ?AccountingResult {
        if (!self::isReplay($event)) {
            return null;
        }

        $meta = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];

        if (!empty($meta['journal_entry_id']) || !empty($meta['ledger_journal_id'])) {
            return self::replayAcknowledged(
                $event,
                $sourceSystem,
                'Replay idempotent — journal id present in event metadata',
                array_filter([
                    'journal_entry_id' => $meta['journal_entry_id'] ?? null,
                    'ledger_journal_id' => $meta['ledger_journal_id'] ?? null,
                ], static fn ($v) => $v !== null && $v !== '')
            );
        }

        if ($resolveJournalByReference !== null) {
            $resolvedId = $resolveJournalByReference($event);
            if ($resolvedId !== null && $resolvedId > 0) {
                return self::replayAcknowledged(
                    $event,
                    $sourceSystem,
                    'Replay idempotent — journal already materialized for reference',
                    ['journal_entry_id' => $resolvedId]
                );
            }
        }

        $eventUuid = trim((string) ($meta['event_uuid'] ?? ''));
        if ($eventUuid !== '') {
            $fromProcessed = self::journalEvidenceFromProcessedStore($eventUuid);
            if ($fromProcessed !== null) {
                return self::replayAcknowledged(
                    $event,
                    $sourceSystem,
                    'Replay idempotent — accounting_processed_events record',
                    $fromProcessed
                );
            }

            $fromAudit = self::journalEvidenceFromAuditLog($eventUuid, $sourceSystem);
            if ($fromAudit !== null) {
                return self::replayAcknowledged(
                    $event,
                    $sourceSystem,
                    'Replay idempotent — prior adapter execution in audit log',
                    $fromAudit
                );
            }

            if (self::auditShowsSuccessfulAdapterRun($eventUuid, $sourceSystem)) {
                return self::replayAcknowledged(
                    $event,
                    $sourceSystem,
                    'Replay idempotent — audit shows successful adapter run'
                );
            }

            if (self::eventStoreStatus($eventUuid) === 'processed') {
                return self::replayAcknowledged(
                    $event,
                    $sourceSystem,
                    'Replay idempotent — event already processed in event store'
                );
            }
        }

        return null;
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

    /**
     * @return array<string, mixed>|null
     */
    private static function journalEvidenceFromProcessedStore(string $eventUuid): ?array
    {
        $pdo = AccountingConnectionFactory::pdo();
        if ($pdo === null || !self::tableExists($pdo, 'accounting_processed_events')) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT result_payload FROM accounting_processed_events
                 WHERE event_uuid = :uuid AND result_status = :st LIMIT 1'
            );
            $stmt->execute(['uuid' => $eventUuid, 'st' => 'processed']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row || empty($row['result_payload'])) {
                return null;
            }

            $payload = json_decode((string) $row['result_payload'], true);

            return self::extractJournalEvidence(is_array($payload) ? $payload : []);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function journalEvidenceFromAuditLog(string $eventUuid, string $sourceSystem): ?array
    {
        $pdo = AccountingConnectionFactory::pdo();
        if ($pdo === null || !self::tableExists($pdo, 'accounting_audit_logs')) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT metadata FROM accounting_audit_logs
                 WHERE event_uuid = :uuid AND action = 'adapter_executed' AND status = 'processed'
                 AND (system = :sys OR system = '' OR system IS NULL)
                 ORDER BY id DESC LIMIT 1"
            );
            $stmt->execute(['uuid' => $eventUuid, 'sys' => $sourceSystem]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row || empty($row['metadata'])) {
                return null;
            }

            $meta = json_decode((string) $row['metadata'], true);
            if (!is_array($meta)) {
                return null;
            }

            $result = is_array($meta['result'] ?? null) ? $meta['result'] : $meta;

            return self::extractJournalEvidence($result);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function auditShowsSuccessfulAdapterRun(string $eventUuid, string $sourceSystem): bool
    {
        $pdo = AccountingConnectionFactory::pdo();
        if ($pdo === null || !self::tableExists($pdo, 'accounting_audit_logs')) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT 1 FROM accounting_audit_logs
                 WHERE event_uuid = :uuid AND action = 'adapter_executed' AND status = 'processed'
                 AND (system = :sys OR system = '' OR system IS NULL)
                 LIMIT 1"
            );
            $stmt->execute(['uuid' => $eventUuid, 'sys' => $sourceSystem]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private static function eventStoreStatus(string $eventUuid): ?string
    {
        $pdo = AccountingConnectionFactory::pdo();
        if ($pdo === null || !self::tableExists($pdo, 'accounting_events')) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT status FROM accounting_events WHERE event_uuid = :uuid LIMIT 1');
            $stmt->execute(['uuid' => $eventUuid]);
            $status = $stmt->fetchColumn();

            return $status !== false ? (string) $status : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private static function extractJournalEvidence(array $payload): ?array
    {
        $evidence = [];
        if (!empty($payload['journal_entry_id'])) {
            $evidence['journal_entry_id'] = $payload['journal_entry_id'];
        }
        if (!empty($payload['ledger_journal_id'])) {
            $evidence['ledger_journal_id'] = $payload['ledger_journal_id'];
        }
        if (!empty($payload['reference'])) {
            $evidence['reference'] = $payload['reference'];
        }
        if ($evidence !== []) {
            return $evidence;
        }

        if (($payload['mode'] ?? '') === 'gateway_write' || ($payload['mode'] ?? '') === 'acknowledged') {
            return [];
        }

        return null;
    }

    private static function tableExists(\PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
