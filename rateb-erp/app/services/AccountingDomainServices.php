<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\ChartOfAccount;
use Rateb\App\Models\CostCenter;
use Rateb\App\Models\FiscalPeriod;
use Rateb\App\Models\JournalEntry;

/**
 * Chart of Accounts domain — reusable for controllers and future Offline Replay.
 * Delegates defaults / destroy to AccountingService where already implemented.
 */
final class ChartOfAccountsService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        (new AccountingService())->ensureDefaultAccounts($companyId);
        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || $name === '') {
            throw new \InvalidArgumentException('code_and_name_required');
        }
        $type = strtolower(trim((string) ($data['account_type'] ?? 'asset')));
        if (!in_array($type, ['asset', 'liability', 'equity', 'revenue', 'expense'], true)) {
            throw new \InvalidArgumentException('invalid_account_type');
        }
        $payload = array_merge([
            'company_id' => $companyId,
            'code' => substr($code, 0, 20),
            'name' => substr($name, 0, 200),
            'name_ar' => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'account_type' => $type,
            'parent_id' => isset($data['parent_id']) && (int) $data['parent_id'] > 0 ? (int) $data['parent_id'] : null,
            'is_active' => isset($data['is_active']) ? ((int) $data['is_active'] ? 1 : 0) : 1,
        ], AccountingSupport::actorFields(true));
        if (AccountingSupport::hasColumn('rateb_chart_of_accounts', 'public_uuid')) {
            $payload['public_uuid'] = AccountingSupport::uuidV4();
        }
        $id = (new ChartOfAccount())->create($payload);
        AccountingSupport::activity($companyId, 'coa.create', 'Account ' . $code, null, 'coa', $id);
        (new AuditService())->log('create', 'chart_of_account', $id, ['code' => $code]);

        return ['id' => $id, 'code' => $code];
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $companyId = AccountingSupport::requireCompanyId();
        $row = $this->assertAccount($id, $companyId);
        $patch = AccountingSupport::actorFields(false);
        foreach (['name', 'name_ar', 'code'] as $k) {
            if (array_key_exists($k, $data)) {
                $v = trim((string) $data[$k]);
                $patch[$k] = $v !== '' ? $v : null;
            }
        }
        if (isset($data['account_type'])) {
            $type = strtolower(trim((string) $data['account_type']));
            if (!in_array($type, ['asset', 'liability', 'equity', 'revenue', 'expense'], true)) {
                throw new \InvalidArgumentException('invalid_account_type');
            }
            $patch['account_type'] = $type;
        }
        if (array_key_exists('parent_id', $data)) {
            $patch['parent_id'] = (int) $data['parent_id'] > 0 ? (int) $data['parent_id'] : null;
        }
        if (array_key_exists('is_active', $data)) {
            $patch['is_active'] = (int) $data['is_active'] ? 1 : 0;
        }
        (new ChartOfAccount())->update($id, $patch);
        AccountingSupport::activity($companyId, 'coa.update', 'Account ' . ($row['code'] ?? $id), null, 'coa', $id);
    }

    public function softDelete(int $id): void
    {
        $companyId = AccountingSupport::requireCompanyId();
        $this->assertAccount($id, $companyId);
        if (AccountingSupport::hasColumn('rateb_chart_of_accounts', 'deleted_at')) {
            (new ChartOfAccount())->update($id, array_merge(['deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0], AccountingSupport::actorFields(false)));
        } else {
            (new AccountingService())->destroyChartAccount($id, $companyId);
        }
        AccountingSupport::activity($companyId, 'coa.delete', 'Account soft-deleted', null, 'coa', $id);
    }

    /** @return list<array<string, mixed>> */
    public function list(?int $branchId = null, int $limit = 500): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        $sql = 'SELECT * FROM rateb_chart_of_accounts WHERE company_id = :cid';
        $params = ['cid' => $companyId];
        if (AccountingSupport::hasColumn('rateb_chart_of_accounts', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' ORDER BY code ASC LIMIT ' . max(1, min(2000, $limit));

        return (new ChartOfAccount())->query($sql, $params) ?: [];
    }

    /** @return array<string, mixed> */
    public function assertAccount(int $id, int $companyId): array
    {
        $sql = 'SELECT * FROM rateb_chart_of_accounts WHERE id = :id AND company_id = :cid';
        if (AccountingSupport::hasColumn('rateb_chart_of_accounts', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' LIMIT 1';
        $row = (new ChartOfAccount())->queryOne($sql, ['id' => $id, 'cid' => $companyId]);
        if (!is_array($row)) {
            throw new \RuntimeException('account_not_found');
        }

        return $row;
    }
}

/**
 * Journal domain — draft create/update via AccountingService; balance validation here.
 */
final class JournalService
{
    /**
     * @param array<string, mixed> $data
     * @return array{id: int, entry_no?: string}
     */
    public function createDraft(array $data): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        $lines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        AccountingSupport::assertBalanced($lines);
        $date = trim((string) ($data['entry_date'] ?? date('Y-m-d')));
        if ($date === '') {
            throw new \InvalidArgumentException('entry_date_required');
        }
        if ((new AccountingService())->periodBlocksPosting($companyId, $date)) {
            throw new \RuntimeException('period_locked');
        }
        $desc = trim((string) ($data['description'] ?? ''));
        if ($desc === '') {
            throw new \InvalidArgumentException('description_required');
        }
        $normalized = [];
        foreach ($lines as $line) {
            $normalized[] = [
                'account_id' => (int) ($line['account_id'] ?? 0),
                'debit' => (float) ($line['debit'] ?? 0),
                'credit' => (float) ($line['credit'] ?? 0),
                'memo' => isset($line['memo']) ? (string) $line['memo'] : '',
                'cost_center_id' => isset($line['cost_center_id']) ? (int) $line['cost_center_id'] : null,
            ];
        }
        $id = (new AccountingService())->createManualDraft(
            $companyId,
            $date,
            $desc,
            trim((string) ($data['description_ar'] ?? '')),
            $normalized,
            AccountingSupport::userId(),
            isset($data['branch_id']) ? (int) $data['branch_id'] : null
        );
        $this->applyEnterpriseMeta($id, $companyId, $data, true);
        AccountingSupport::activity($companyId, 'journal.draft', 'Draft journal created', $id);
        (new AuditService())->log('create', 'journal_entry', $id, ['lifecycle' => 'draft']);

        return ['id' => $id];
    }

    /** @param array<string, mixed> $data */
    public function updateDraft(int $id, array $data): void
    {
        $companyId = AccountingSupport::requireCompanyId();
        $entry = AccountingSupport::assertJournal($id, $companyId);
        $life = (string) ($entry['lifecycle_status'] ?? $entry['status'] ?? 'draft');
        if (!in_array($life, ['draft', 'balanced'], true) && !in_array((string) ($entry['status'] ?? ''), ['draft', 'rejected'], true)) {
            throw new \RuntimeException('journal_not_editable');
        }
        $lines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        AccountingSupport::assertBalanced($lines);
        $normalized = [];
        foreach ($lines as $line) {
            $normalized[] = [
                'account_id' => (int) ($line['account_id'] ?? 0),
                'debit' => (float) ($line['debit'] ?? 0),
                'credit' => (float) ($line['credit'] ?? 0),
                'memo' => isset($line['memo']) ? (string) $line['memo'] : '',
                'cost_center_id' => isset($line['cost_center_id']) ? (int) $line['cost_center_id'] : null,
            ];
        }
        $ok = (new AccountingService())->updateManualDraft(
            $id,
            $companyId,
            trim((string) ($data['entry_date'] ?? $entry['entry_date'])),
            trim((string) ($data['description'] ?? $entry['description'])),
            trim((string) ($data['description_ar'] ?? ($entry['description_ar'] ?? ''))),
            $normalized,
            isset($data['branch_id']) ? (int) $data['branch_id'] : null
        );
        if (!$ok) {
            throw new \RuntimeException('journal_update_failed');
        }
        $this->applyEnterpriseMeta($id, $companyId, $data, false);
        AccountingSupport::activity($companyId, 'journal.update', 'Draft journal updated', $id);
    }

    /** @param array<string, mixed> $data */
    private function applyEnterpriseMeta(int $id, int $companyId, array $data, bool $creating): void
    {
        if (!AccountingSupport::hasColumn('rateb_journal_entries', 'lifecycle_status')) {
            return;
        }
        $patch = AccountingSupport::actorFields($creating);
        if ($creating && AccountingSupport::hasColumn('rateb_journal_entries', 'public_uuid')) {
            $patch['public_uuid'] = AccountingSupport::uuidV4();
        }
        $patch['lifecycle_status'] = 'balanced';
        if (array_key_exists('currency_code', $data)) {
            $patch['currency_code'] = AccountingSupport::normalizeCurrencyCode((string) $data['currency_code']);
        }
        if (array_key_exists('exchange_rate', $data) && $data['exchange_rate'] !== null && $data['exchange_rate'] !== '') {
            $rate = (float) $data['exchange_rate'];
            if ($rate <= 0) {
                throw new \InvalidArgumentException('invalid_exchange_rate');
            }
            $patch['exchange_rate'] = $rate;
        }
        if (array_key_exists('profit_center_id', $data)) {
            $patch['profit_center_id'] = (int) $data['profit_center_id'] > 0 ? (int) $data['profit_center_id'] : null;
        }
        if (array_key_exists('tax_code_id', $data)) {
            $patch['tax_code_id'] = (int) $data['tax_code_id'] > 0 ? (int) $data['tax_code_id'] : null;
        }
        if (!empty($data['is_opening_balance'])) {
            $patch['is_opening_balance'] = 1;
        }
        (new JournalEntry())->update($id, $patch);
    }
}

/**
 * Only AccountingWorkflowService may change lifecycle states.
 */
final class AccountingWorkflowService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_BALANCED = 'balanced';
    public const STATUS_POSTED = 'posted';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_ARCHIVED = 'archived';

    /** @return array<string, list<string>> */
    public static function transitionMap(): array
    {
        return [
            self::STATUS_DRAFT => [self::STATUS_BALANCED, self::STATUS_ARCHIVED],
            self::STATUS_BALANCED => [self::STATUS_DRAFT, self::STATUS_POSTED, self::STATUS_ARCHIVED],
            self::STATUS_POSTED => [self::STATUS_LOCKED, self::STATUS_REVERSED],
            self::STATUS_LOCKED => [self::STATUS_REVERSED],
            self::STATUS_REVERSED => [self::STATUS_ARCHIVED],
            self::STATUS_ARCHIVED => [],
        ];
    }

    /**
     * @return array{ok: bool, from: string, to: string, entry_id: int}
     */
    public function transition(int $entryId, string $toStatus, ?string $reason = null): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        $entry = AccountingSupport::assertJournal($entryId, $companyId);
        $to = strtolower(trim($toStatus));
        $from = strtolower((string) ($entry['lifecycle_status'] ?? ''));
        if ($from === '' || $from === 'draft') {
            $legacy = (string) ($entry['status'] ?? 'draft');
            $from = match ($legacy) {
                'posted' => self::STATUS_POSTED,
                'void' => self::STATUS_REVERSED,
                default => self::STATUS_DRAFT,
            };
            if ((string) ($entry['lifecycle_status'] ?? '') === 'balanced') {
                $from = self::STATUS_BALANCED;
            }
        }
        $allowed = self::transitionMap()[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new \RuntimeException('workflow_transition_denied');
        }

        $acct = new AccountingService();
        switch ($to) {
            case self::STATUS_BALANCED:
                $this->setLifecycle($entryId, $from, $to, $reason);
                break;
            case self::STATUS_DRAFT:
                $this->setLifecycle($entryId, $from, $to, $reason);
                (new JournalEntry())->update($entryId, ['status' => 'draft']);
                break;
            case self::STATUS_POSTED:
                if ($acct->periodBlocksPosting($companyId, (string) $entry['entry_date'])) {
                    throw new \RuntimeException('period_locked');
                }
                if (!$acct->postDraftEntry($entryId, $companyId)) {
                    throw new \RuntimeException('journal_post_failed');
                }
                $this->setLifecycle($entryId, $from, $to, $reason);
                break;
            case self::STATUS_LOCKED:
                if (!AccountingSupport::hasColumn('rateb_journal_entries', 'locked_at')) {
                    throw new \RuntimeException('migration_required');
                }
                (new JournalEntry())->update($entryId, [
                    'locked_at' => date('Y-m-d H:i:s'),
                    'locked_by' => AccountingSupport::userId(),
                ]);
                $this->setLifecycle($entryId, $from, $to, $reason);
                break;
            case self::STATUS_REVERSED:
                if (!$acct->voidPostedEntry($entryId, $companyId)) {
                    throw new \RuntimeException('journal_reverse_failed');
                }
                $this->setLifecycle($entryId, $from, $to, $reason);
                break;
            case self::STATUS_ARCHIVED:
                $patch = ['archived_at' => date('Y-m-d H:i:s')];
                if (AccountingSupport::hasColumn('rateb_journal_entries', 'deleted_at') && in_array($from, [self::STATUS_DRAFT, self::STATUS_BALANCED], true)) {
                    $patch['deleted_at'] = date('Y-m-d H:i:s');
                }
                (new JournalEntry())->update($entryId, $patch);
                $this->setLifecycle($entryId, $from, $to, $reason);
                break;
            default:
                throw new \RuntimeException('unknown_lifecycle_status');
        }

        AccountingSupport::activity($companyId, 'journal.transition', $from . ' → ' . $to, $entryId);
        (new AuditService())->log('workflow', 'journal_entry', $entryId, ['from' => $from, 'to' => $to]);

        return ['ok' => true, 'from' => $from, 'to' => $to, 'entry_id' => $entryId];
    }

    private function setLifecycle(int $entryId, string $from, string $to, ?string $reason): void
    {
        $companyId = AccountingSupport::requireCompanyId();
        if (AccountingSupport::hasColumn('rateb_journal_entries', 'lifecycle_status')) {
            (new JournalEntry())->update($entryId, array_merge([
                'lifecycle_status' => $to,
            ], AccountingSupport::actorFields(false)));
        }
        if (AccountingSupport::tableExists('rateb_accounting_status_history')) {
            $db = Database::connection();
            $stmt = $db->prepare(
                'INSERT INTO rateb_accounting_status_history
                 (company_id, journal_entry_id, from_status, to_status, reason, created_by)
                 VALUES (:cid, :je, :fr, :to, :rs, :uid)'
            );
            $stmt->execute([
                'cid' => $companyId,
                'je' => $entryId,
                'fr' => $from,
                'to' => $to,
                'rs' => $reason !== null && $reason !== '' ? substr($reason, 0, 500) : null,
                'uid' => AccountingSupport::userId(),
            ]);
        }
    }
}

/** Ledger read APIs — wrap AccountingService reports. */
final class LedgerService
{
    /** @return list<array<string, mixed>>|array<string, mixed> */
    public function trialBalance(?string $asOf = null): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        // Existing AccountingService::trialBalance is company-scoped; $asOf reserved for future filter.
        unset($asOf);

        return (new AccountingService())->trialBalance($companyId);
    }

    /** @return array<string, mixed> */
    public function accountStatement(int $accountId, ?string $from = null, ?string $to = null): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        (new ChartOfAccountsService())->assertAccount($accountId, $companyId);

        return (new AccountingService())->accountStatement($companyId, $accountId, $from, $to);
    }
}

/** Fiscal periods — open/close/lock via AccountingService + additive lock flag. */
final class FiscalPeriodService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $companyId = AccountingSupport::requireCompanyId();
        $name = trim((string) ($data['name'] ?? ''));
        $start = trim((string) ($data['start_date'] ?? ''));
        $end = trim((string) ($data['end_date'] ?? ''));
        if ($name === '' || $start === '' || $end === '') {
            throw new \InvalidArgumentException('fiscal_period_fields_required');
        }
        $id = (new AccountingService())->createFiscalPeriod($companyId, $name, $start, $end);
        if ($id === null || $id < 1) {
            throw new \RuntimeException('fiscal_period_create_failed');
        }
        AccountingSupport::activity($companyId, 'fiscal.create', $name, null, 'fiscal_period', $id);

        return $id;
    }

    public function close(int $periodId, bool $withClosingEntry = false): void
    {
        $companyId = AccountingSupport::requireCompanyId();
        if (!(new AccountingService())->closeFiscalPeriod($periodId, $companyId, AccountingSupport::userId(), $withClosingEntry)) {
            throw new \RuntimeException('fiscal_period_close_failed');
        }
        AccountingSupport::activity($companyId, 'fiscal.close', 'Period closed', null, 'fiscal_period', $periodId);
    }

    public function lock(int $periodId): void
    {
        $companyId = AccountingSupport::requireCompanyId();
        $row = (new FiscalPeriod())->queryOne(
            'SELECT * FROM rateb_fiscal_periods WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $periodId, 'cid' => $companyId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('fiscal_period_not_found');
        }
        if (!AccountingSupport::hasColumn('rateb_fiscal_periods', 'locked')) {
            throw new \RuntimeException('migration_required');
        }
        (new FiscalPeriod())->update($periodId, ['locked' => 1]);
        AccountingSupport::activity($companyId, 'fiscal.lock', 'Period locked', null, 'fiscal_period', $periodId);
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $companyId = AccountingSupport::requireCompanyId();

        return (new AccountingService())->listFiscalPeriods($companyId);
    }

    public function blocksPosting(string $entryDate): bool
    {
        return (new AccountingService())->periodBlocksPosting(AccountingSupport::requireCompanyId(), $entryDate);
    }
}

/** Cost centers — thin domain API over existing table. */
final class CostCenterService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $companyId = AccountingSupport::requireCompanyId();
        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || $name === '') {
            throw new \InvalidArgumentException('code_and_name_required');
        }
        $id = (new CostCenter())->create([
            'company_id' => $companyId,
            'branch_id' => isset($data['branch_id']) && (int) $data['branch_id'] > 0 ? (int) $data['branch_id'] : null,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'parent_id' => isset($data['parent_id']) && (int) $data['parent_id'] > 0 ? (int) $data['parent_id'] : null,
            'is_active' => 1,
        ]);
        AccountingSupport::activity($companyId, 'cost_center.create', $code, null, 'cost_center', $id);

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function list(int $limit = 500): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        $sql = 'SELECT * FROM rateb_cost_centers WHERE company_id = :cid';
        if (AccountingSupport::hasColumn('rateb_cost_centers', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $sql .= ' ORDER BY code ASC LIMIT ' . max(1, min(2000, $limit));

        return (new CostCenter())->query($sql, ['cid' => $companyId]) ?: [];
    }
}
