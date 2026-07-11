<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\AccountingCurrency;
use Rateb\App\Models\AccountingDocumentLink;
use Rateb\App\Models\AccountingExchangeRate;
use Rateb\App\Models\AccountingProfitCenter;
use Rateb\App\Models\AccountingRecurringJournal;
use Rateb\App\Models\AccountingRecurringJournalLine;
use Rateb\App\Models\AccountingTaxCode;

/** Currencies — company base + foreign currencies. */
final class CurrencyService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        if (!AccountingSupport::tableExists('rateb_accounting_currencies')) {
            throw new \RuntimeException('migration_required');
        }
        $code = AccountingSupport::normalizeCurrencyCode((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === null || $name === '') {
            throw new \InvalidArgumentException('currency_code_and_name_required');
        }
        $isBase = !empty($data['is_base']);
        if ($isBase) {
            $db = Database::connection();
            $db->prepare('UPDATE rateb_accounting_currencies SET is_base = 0 WHERE company_id = :cid AND deleted_at IS NULL')
                ->execute(['cid' => $companyId]);
        }
        $uuid = AccountingSupport::uuidV4();
        $id = (new AccountingCurrency())->create(array_merge([
            'public_uuid' => $uuid,
            'company_id' => $companyId,
            'branch_id' => isset($data['branch_id']) && (int) $data['branch_id'] > 0 ? (int) $data['branch_id'] : null,
            'code' => $code,
            'name' => substr($name, 0, 120),
            'name_ar' => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'symbol' => trim((string) ($data['symbol'] ?? '')) ?: null,
            'decimal_places' => max(0, min(6, (int) ($data['decimal_places'] ?? 2))),
            'is_base' => $isBase ? 1 : 0,
            'status' => 'active',
        ], AccountingSupport::actorFields(true)));
        AccountingSupport::activity($companyId, 'currency.create', $code, null, 'currency', $id);

        return ['id' => $id, 'public_uuid' => $uuid, 'code' => $code];
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        if (!AccountingSupport::tableExists('rateb_accounting_currencies')) {
            return [];
        }

        return (new AccountingCurrency())->query(
            'SELECT * FROM rateb_accounting_currencies WHERE company_id = :cid AND deleted_at IS NULL ORDER BY is_base DESC, code ASC',
            ['cid' => $companyId]
        ) ?: [];
    }

    public function assertActive(string $code): void
    {
        $companyId = AccountingSupport::requireCompanyId();
        $code = AccountingSupport::normalizeCurrencyCode($code);
        if ($code === null) {
            throw new \InvalidArgumentException('currency_required');
        }
        if (!AccountingSupport::tableExists('rateb_accounting_currencies')) {
            return;
        }
        $row = (new AccountingCurrency())->queryOne(
            'SELECT id FROM rateb_accounting_currencies
             WHERE company_id = :cid AND code = :c AND status = \'active\' AND deleted_at IS NULL LIMIT 1',
            ['cid' => $companyId, 'c' => $code]
        );
        if ($row === null) {
            throw new \RuntimeException('currency_not_found');
        }
    }
}

/** Exchange rates between currencies. */
final class ExchangeRateService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        if (!AccountingSupport::tableExists('rateb_accounting_exchange_rates')) {
            throw new \RuntimeException('migration_required');
        }
        $from = AccountingSupport::normalizeCurrencyCode((string) ($data['from_currency'] ?? ''));
        $to = AccountingSupport::normalizeCurrencyCode((string) ($data['to_currency'] ?? ''));
        $rate = (float) ($data['rate'] ?? 0);
        $date = trim((string) ($data['rate_date'] ?? date('Y-m-d')));
        if ($from === null || $to === null || $rate <= 0 || $date === '') {
            throw new \InvalidArgumentException('exchange_rate_fields_required');
        }
        if ($from === $to) {
            throw new \InvalidArgumentException('exchange_rate_same_currency');
        }
        (new CurrencyService())->assertActive($from);
        (new CurrencyService())->assertActive($to);
        $uuid = AccountingSupport::uuidV4();
        $id = (new AccountingExchangeRate())->create(array_merge([
            'public_uuid' => $uuid,
            'company_id' => $companyId,
            'from_currency' => $from,
            'to_currency' => $to,
            'rate' => $rate,
            'rate_date' => $date,
            'source' => trim((string) ($data['source'] ?? '')) ?: null,
            'status' => 'active',
        ], AccountingSupport::actorFields(true)));
        AccountingSupport::activity($companyId, 'fx.create', $from . '/' . $to, null, 'exchange_rate', $id);

        return ['id' => $id, 'public_uuid' => $uuid];
    }

    public function rateOn(string $from, string $to, ?string $date = null): ?float
    {
        $companyId = AccountingSupport::requireCompanyId();
        $from = AccountingSupport::normalizeCurrencyCode($from);
        $to = AccountingSupport::normalizeCurrencyCode($to);
        if ($from === null || $to === null) {
            return null;
        }
        if ($from === $to) {
            return 1.0;
        }
        if (!AccountingSupport::tableExists('rateb_accounting_exchange_rates')) {
            return null;
        }
        $date = $date ?: date('Y-m-d');
        $row = (new AccountingExchangeRate())->queryOne(
            'SELECT rate FROM rateb_accounting_exchange_rates
             WHERE company_id = :cid AND from_currency = :f AND to_currency = :t
               AND rate_date <= :d AND status = \'active\' AND deleted_at IS NULL
             ORDER BY rate_date DESC LIMIT 1',
            ['cid' => $companyId, 'f' => $from, 't' => $to, 'd' => $date]
        );

        return $row ? (float) $row['rate'] : null;
    }
}

/** Tax codes (VAT / withholding). */
final class TaxService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        if (!AccountingSupport::tableExists('rateb_accounting_tax_codes')) {
            throw new \RuntimeException('migration_required');
        }
        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || $name === '') {
            throw new \InvalidArgumentException('tax_code_and_name_required');
        }
        $type = strtolower(trim((string) ($data['tax_type'] ?? 'vat')));
        if (!in_array($type, ['vat', 'withholding', 'other'], true)) {
            throw new \InvalidArgumentException('invalid_tax_type');
        }
        $rate = (float) ($data['rate_percent'] ?? 0);
        if ($rate < 0 || $rate > 100) {
            throw new \InvalidArgumentException('invalid_tax_rate');
        }
        $uuid = AccountingSupport::uuidV4();
        $id = (new AccountingTaxCode())->create(array_merge([
            'public_uuid' => $uuid,
            'company_id' => $companyId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'rate_percent' => $rate,
            'tax_type' => $type,
            'recoverable' => !isset($data['recoverable']) || $data['recoverable'] ? 1 : 0,
            'account_id' => isset($data['account_id']) && (int) $data['account_id'] > 0 ? (int) $data['account_id'] : null,
            'status' => 'active',
        ], AccountingSupport::actorFields(true)));
        AccountingSupport::activity($companyId, 'tax.create', $code, null, 'tax_code', $id);

        return ['id' => $id, 'public_uuid' => $uuid];
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        if (!AccountingSupport::tableExists('rateb_accounting_tax_codes')) {
            return [];
        }

        return (new AccountingTaxCode())->query(
            'SELECT * FROM rateb_accounting_tax_codes WHERE company_id = :cid AND deleted_at IS NULL ORDER BY code ASC',
            ['cid' => $companyId]
        ) ?: [];
    }
}

/** Profit centers. */
final class ProfitCenterService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $companyId = AccountingSupport::requireCompanyId();
        if (!AccountingSupport::tableExists('rateb_accounting_profit_centers')) {
            throw new \RuntimeException('migration_required');
        }
        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || $name === '') {
            throw new \InvalidArgumentException('code_and_name_required');
        }
        $id = (new AccountingProfitCenter())->create(array_merge([
            'public_uuid' => AccountingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => isset($data['branch_id']) && (int) $data['branch_id'] > 0 ? (int) $data['branch_id'] : null,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'parent_id' => isset($data['parent_id']) && (int) $data['parent_id'] > 0 ? (int) $data['parent_id'] : null,
            'status' => 'active',
        ], AccountingSupport::actorFields(true)));
        AccountingSupport::activity($companyId, 'profit_center.create', $code, null, 'profit_center', $id);

        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        if (!AccountingSupport::tableExists('rateb_accounting_profit_centers')) {
            return [];
        }

        return (new AccountingProfitCenter())->query(
            'SELECT * FROM rateb_accounting_profit_centers WHERE company_id = :cid AND deleted_at IS NULL ORDER BY code ASC',
            ['cid' => $companyId]
        ) ?: [];
    }
}

/** Recurring journal templates — generate drafts via JournalService. */
final class RecurringJournalService
{
    /**
     * @param array<string, mixed> $data
     * @return array{id: int}
     */
    public function create(array $data): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        if (!AccountingSupport::tableExists('rateb_accounting_recurring_journals')) {
            throw new \RuntimeException('migration_required');
        }
        $lines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        AccountingSupport::assertBalanced($lines);
        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || $name === '') {
            throw new \InvalidArgumentException('code_and_name_required');
        }
        $freq = strtolower(trim((string) ($data['frequency'] ?? 'monthly')));
        if (!in_array($freq, ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'], true)) {
            throw new \InvalidArgumentException('invalid_frequency');
        }
        $id = (new AccountingRecurringJournal())->create(array_merge([
            'public_uuid' => AccountingSupport::uuidV4(),
            'company_id' => $companyId,
            'branch_id' => isset($data['branch_id']) && (int) $data['branch_id'] > 0 ? (int) $data['branch_id'] : null,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 190),
            'name_ar' => trim((string) ($data['name_ar'] ?? '')) ?: null,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'frequency' => $freq,
            'next_run_date' => trim((string) ($data['next_run_date'] ?? date('Y-m-d'))) ?: null,
            'end_date' => trim((string) ($data['end_date'] ?? '')) ?: null,
            'currency_code' => AccountingSupport::normalizeCurrencyCode((string) ($data['currency_code'] ?? '')),
            'status' => 'active',
        ], AccountingSupport::actorFields(true)));
        $sort = 0;
        foreach ($lines as $line) {
            (new AccountingRecurringJournalLine())->create([
                'company_id' => $companyId,
                'recurring_journal_id' => $id,
                'account_id' => (int) ($line['account_id'] ?? 0),
                'cost_center_id' => isset($line['cost_center_id']) && (int) $line['cost_center_id'] > 0 ? (int) $line['cost_center_id'] : null,
                'profit_center_id' => isset($line['profit_center_id']) && (int) $line['profit_center_id'] > 0 ? (int) $line['profit_center_id'] : null,
                'debit' => (float) ($line['debit'] ?? 0),
                'credit' => (float) ($line['credit'] ?? 0),
                'memo' => isset($line['memo']) ? (string) $line['memo'] : null,
                'sort_order' => $sort++,
            ]);
        }
        AccountingSupport::activity($companyId, 'recurring.create', $code, null, 'recurring_journal', $id);

        return ['id' => $id];
    }

    /** Generate a balanced draft journal from template. */
    public function generateDraft(int $recurringId, ?string $entryDate = null): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        $tpl = (new AccountingRecurringJournal())->queryOne(
            'SELECT * FROM rateb_accounting_recurring_journals
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $recurringId, 'cid' => $companyId]
        );
        if (!is_array($tpl)) {
            throw new \RuntimeException('recurring_not_found');
        }
        if ((string) ($tpl['status'] ?? '') !== 'active') {
            throw new \RuntimeException('recurring_inactive');
        }
        $lines = (new AccountingRecurringJournalLine())->query(
            'SELECT * FROM rateb_accounting_recurring_journal_lines WHERE recurring_journal_id = :id ORDER BY sort_order ASC, id ASC',
            ['id' => $recurringId]
        ) ?: [];
        $payloadLines = [];
        foreach ($lines as $line) {
            $payloadLines[] = [
                'account_id' => (int) $line['account_id'],
                'debit' => (float) $line['debit'],
                'credit' => (float) $line['credit'],
                'memo' => (string) ($line['memo'] ?? ''),
                'cost_center_id' => $line['cost_center_id'] ?? null,
            ];
        }
        $created = (new JournalService())->createDraft([
            'entry_date' => $entryDate ?: date('Y-m-d'),
            'description' => 'Recurring: ' . (string) $tpl['name'],
            'description_ar' => (string) ($tpl['name_ar'] ?? ''),
            'branch_id' => $tpl['branch_id'] ?? null,
            'currency_code' => $tpl['currency_code'] ?? null,
            'lines' => $payloadLines,
        ]);
        (new AccountingRecurringJournal())->update($recurringId, [
            'last_generated_entry_id' => $created['id'],
            'updated_by' => AccountingSupport::userId(),
        ]);

        return $created;
    }
}

/** Opening balances — creates a balanced opening draft journal. */
final class OpeningBalanceService
{
    /**
     * @param array<string, mixed> $data
     * @return array{id: int}
     */
    public function create(array $data): array
    {
        $lines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        AccountingSupport::assertBalanced($lines);
        $data['description'] = trim((string) ($data['description'] ?? 'Opening balances'));
        $data['is_opening_balance'] = 1;

        return (new JournalService())->createDraft($data);
    }
}

/** Attachment metadata only — binary upload stays online DocumentService. */
final class AccountingDocumentMetaService
{
    /**
     * @param array<string, mixed> $meta
     * @return array{id: int}
     */
    public function link(int $journalEntryId, array $meta): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        AccountingSupport::assertJournal($journalEntryId, $companyId);
        if (!AccountingSupport::tableExists('rateb_accounting_document_links')) {
            throw new \RuntimeException('migration_required');
        }
        $id = (new AccountingDocumentLink())->create(array_merge([
            'public_uuid' => AccountingSupport::uuidV4(),
            'company_id' => $companyId,
            'journal_entry_id' => $journalEntryId,
            'entity_type' => 'journal',
            'entity_id' => $journalEntryId,
            'document_id' => isset($meta['document_id']) && (int) $meta['document_id'] > 0 ? (int) $meta['document_id'] : null,
            'file_name' => trim((string) ($meta['file_name'] ?? '')) ?: null,
            'mime_type' => trim((string) ($meta['mime_type'] ?? '')) ?: null,
            'notes' => trim((string) ($meta['notes'] ?? '')) ?: null,
            'created_by' => AccountingSupport::userId(),
        ]));
        AccountingSupport::activity($companyId, 'document.link', 'Metadata linked', $journalEntryId);

        return ['id' => $id];
    }

    /** @return list<array<string, mixed>> */
    public function listFor(int $journalEntryId): array
    {
        $companyId = AccountingSupport::requireCompanyId();
        if (!AccountingSupport::tableExists('rateb_accounting_document_links')) {
            return [];
        }

        return (new AccountingDocumentLink())->query(
            'SELECT * FROM rateb_accounting_document_links
             WHERE company_id = :cid AND journal_entry_id = :je AND deleted_at IS NULL
             ORDER BY id DESC',
            ['cid' => $companyId, 'je' => $journalEntryId]
        ) ?: [];
    }
}
