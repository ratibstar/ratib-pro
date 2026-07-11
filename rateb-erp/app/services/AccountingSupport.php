<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\JournalEntry;

/**
 * Shared helpers for Phase 16A Accounting domain services.
 * Future Offline Replay MUST call domain services — never duplicate these helpers offline.
 */
final class AccountingSupport
{
    public static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function requireCompanyId(): int
    {
        $cid = (int) (TenantContext::companyId() ?? 0);
        if ($cid < 1) {
            throw new \RuntimeException('company_required');
        }

        return $cid;
    }

    public static function userId(): ?int
    {
        $uid = (int) (SessionManager::get('rateb_user_id') ?? 0);

        return $uid > 0 ? $uid : null;
    }

    /** @return array<string, mixed> */
    public static function actorFields(bool $creating = true): array
    {
        $uid = self::userId();
        $out = ['updated_by' => $uid];
        if ($creating) {
            $out['created_by'] = $uid;
        }

        return $out;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
            );
            $stmt->execute(['t' => $table, 'c' => $column]);
            $cache[$key] = (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }

    public static function tableExists(string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1'
            );
            $stmt->execute(['t' => $table]);
            $cache[$table] = (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findJournal(int $entryId, int $companyId): ?array
    {
        if ($entryId < 1 || $companyId < 1) {
            return null;
        }
        $sql = 'SELECT * FROM rateb_journal_entries WHERE id = :id AND company_id = :cid';
        if (self::hasColumn('rateb_journal_entries', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';
        $row = (new JournalEntry())->queryOne($sql, ['id' => $entryId, 'cid' => $companyId]);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertJournal(int $entryId, int $companyId): array
    {
        $row = self::findJournal($entryId, $companyId);
        if ($row === null) {
            throw new \RuntimeException('journal_not_found');
        }

        return $row;
    }

    /**
     * @param list<array{account_id?:int,debit?:float|int|string,credit?:float|int|string}> $lines
     */
    public static function assertBalanced(array $lines, float $tolerance = 0.0001): void
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $line) {
            $debit += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
            if ((int) ($line['account_id'] ?? 0) < 1) {
                throw new \InvalidArgumentException('account_required');
            }
        }
        if (abs($debit - $credit) > $tolerance) {
            throw new \InvalidArgumentException('journal_not_balanced');
        }
        if ($debit <= 0 && $credit <= 0) {
            throw new \InvalidArgumentException('journal_empty');
        }
    }

    public static function normalizeCurrencyCode(?string $code): ?string
    {
        $c = strtoupper(substr(trim((string) $code), 0, 3));

        return $c !== '' ? $c : null;
    }

    public static function activity(
        int $companyId,
        string $action,
        string $summary,
        ?int $journalEntryId = null,
        string $entityType = 'journal',
        ?int $entityId = null
    ): void {
        if (!self::tableExists('rateb_accounting_activities')) {
            return;
        }
        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                'INSERT INTO rateb_accounting_activities
                 (company_id, journal_entry_id, entity_type, entity_id, action, summary, created_by)
                 VALUES (:cid, :je, :et, :eid, :act, :sum, :uid)'
            );
            $stmt->execute([
                'cid' => $companyId,
                'je' => $journalEntryId,
                'et' => substr($entityType, 0, 40),
                'eid' => $entityId,
                'act' => substr($action, 0, 80),
                'sum' => $summary !== '' ? substr($summary, 0, 500) : null,
                'uid' => self::userId(),
            ]);
        } catch (\Throwable $e) {
            // Non-blocking audit trail.
        }
    }
}
