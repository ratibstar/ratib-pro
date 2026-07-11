<?php

declare(strict_types=1);

/**
 * Phase 16A — Enterprise Accounting Platform (ONLINE) gate tests.
 * Asserts domain services, migration, RBAC, workflow map, and no Offline Baseline changes.
 *
 * Run: php tests/accounting/run-accounting-phase16a-tests.php
 */

final class AccountingPhase16ATest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testBaselineUntouched();
        $this->testMigrationExists();
        $this->testDomainServicesPresent();
        $this->testWorkflowTransitionMap();
        $this->testBalanceValidation();
        $this->testUuidHelper();
        $this->testControllersAndViews();
        $this->testRoutesRegistered();
        $this->testRbacConfig();
        $this->testOfflineReadinessDoc();
        $this->testNoOfflineAccountingModule();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testBaselineUntouched(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $engine = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineReplayEngine.php');
        $flags = (string) file_get_contents(RATEB_ROOT . '/offline/config/feature-flags.php');
        $ok = str_contains($schema, 'DB_VERSION = 2')
            && str_contains($sdk, "version: '14.2.0'")
            && !str_contains($flags, 'offline.accounting')
            && !preg_match("/module === 'accounting'/", $engine)
            && !is_file(RATEB_ROOT . '/offline/client/adapters/accounting-adapter.js')
            && !is_file(RATEB_ROOT . '/offline/server/Services/AccountingOfflineReplayService.php');
        $this->record('Baseline v1.2 / Offline Foundation untouched', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/182_accounting_platform_enterprise.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_accounting_currencies')
            && str_contains($sql, 'rateb_accounting_exchange_rates')
            && str_contains($sql, 'rateb_accounting_tax_codes')
            && str_contains($sql, 'rateb_accounting_profit_centers')
            && str_contains($sql, 'rateb_accounting_recurring_journals')
            && str_contains($sql, 'lifecycle_status')
            && str_contains($sql, 'accounting.create')
            && str_contains($sql, 'accounting.reverse')
            && str_contains($sql, 'accounting.close_period')
            && str_contains($sql, 'accounting.admin');
        $this->record('migration 182 schema + permissions', $ok);
    }

    private function testDomainServicesPresent(): void
    {
        $ok = class_exists(\Rateb\App\Services\ChartOfAccountsService::class)
            && class_exists(\Rateb\App\Services\JournalService::class)
            && class_exists(\Rateb\App\Services\LedgerService::class)
            && class_exists(\Rateb\App\Services\FiscalPeriodService::class)
            && class_exists(\Rateb\App\Services\AccountingWorkflowService::class)
            && class_exists(\Rateb\App\Services\TaxService::class)
            && class_exists(\Rateb\App\Services\CurrencyService::class)
            && class_exists(\Rateb\App\Services\CostCenterService::class)
            && class_exists(\Rateb\App\Services\ExchangeRateService::class)
            && class_exists(\Rateb\App\Services\ProfitCenterService::class)
            && class_exists(\Rateb\App\Services\RecurringJournalService::class)
            && class_exists(\Rateb\App\Services\OpeningBalanceService::class)
            && class_exists(\Rateb\App\Services\AccountingDocumentMetaService::class)
            && class_exists(\Rateb\App\Services\AccountingSupport::class);
        $this->record('domain services present', $ok, $ok ? 'ok' : 'missing class');
    }

    private function testWorkflowTransitionMap(): void
    {
        $map = \Rateb\App\Services\AccountingWorkflowService::transitionMap();
        $ok = isset($map['draft'], $map['balanced'], $map['posted'], $map['locked'], $map['reversed'], $map['archived'])
            && in_array('posted', $map['balanced'], true)
            && in_array('reversed', $map['posted'], true)
            && $map['archived'] === [];
        $this->record('workflow transition map', $ok);
    }

    private function testBalanceValidation(): void
    {
        try {
            \Rateb\App\Services\AccountingSupport::assertBalanced([
                ['account_id' => 1, 'debit' => 100, 'credit' => 0],
                ['account_id' => 2, 'debit' => 0, 'credit' => 50],
            ]);
            $this->record('rejects unbalanced journal', false, 'no exception');
        } catch (\InvalidArgumentException $e) {
            $this->record('rejects unbalanced journal', $e->getMessage() === 'journal_not_balanced', $e->getMessage());
        }
        try {
            \Rateb\App\Services\AccountingSupport::assertBalanced([
                ['account_id' => 1, 'debit' => 100, 'credit' => 0],
                ['account_id' => 2, 'debit' => 0, 'credit' => 100],
            ]);
            $this->record('accepts balanced journal', true);
        } catch (\Throwable $e) {
            $this->record('accepts balanced journal', false, $e->getMessage());
        }
    }

    private function testUuidHelper(): void
    {
        $a = \Rateb\App\Services\AccountingSupport::uuidV4();
        $b = \Rateb\App\Services\AccountingSupport::uuidV4();
        $ok = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $a) === 1
            && $a !== $b;
        $this->record('uuid v4 helper', $ok, $a);
    }

    private function testControllersAndViews(): void
    {
        $ok = is_file(RATEB_ROOT . '/app/controllers/Company/AccountingPlatformControllers.php')
            && is_file(RATEB_ROOT . '/views/company/accounting/platform-hub.php')
            && is_file(RATEB_ROOT . '/views/company/accounting/currencies/index.php')
            && is_file(RATEB_ROOT . '/views/company/accounting/tax-codes/form.php')
            && is_file(RATEB_ROOT . '/views/company/accounting/profit-centers/index.php')
            && is_file(RATEB_ROOT . '/views/company/accounting/recurring/form.php')
            && is_file(RATEB_ROOT . '/views/company/accounting/opening-balances/form.php');
        $this->record('controllers and views present', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($src, 'accounting/platform')
            && str_contains($src, 'accounting/currencies')
            && str_contains($src, 'accounting/tax-codes')
            && str_contains($src, 'accounting/profit-centers')
            && str_contains($src, 'accounting/recurring')
            && str_contains($src, 'opening-balances')
            && str_contains($src, 'journal-entries/{id}/lifecycle');
        $this->record('routes registered under accounting', $ok);
    }

    private function testRbacConfig(): void
    {
        $cfg = require RATEB_ROOT . '/config/permissions-system.php';
        $slugs = $cfg['accounting_permission_slugs'] ?? [];
        $implies = $cfg['permission_implies'] ?? [];
        $ok = in_array('accounting.create', $slugs, true)
            && in_array('accounting.update', $slugs, true)
            && in_array('accounting.reverse', $slugs, true)
            && in_array('accounting.close_period', $slugs, true)
            && in_array('accounting.admin', $slugs, true)
            && isset($implies['accounting.admin']);
        $this->record('RBAC config wired', $ok);
    }

    private function testOfflineReadinessDoc(): void
    {
        $path = RATEB_ROOT . '/docs/PHASE_16A_ACCOUNTING_ONLINE.md';
        $src = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = str_contains($src, 'Offline Readiness')
            && str_contains($src, 'JournalService')
            && str_contains($src, 'AccountingWorkflowService')
            && (str_contains($src, 'NO offline') || str_contains($src, 'Do NOT implement Offline'));
        $this->record('offline readiness matrix doc', $ok);
    }

    private function testNoOfflineAccountingModule(): void
    {
        $modules = require RATEB_ROOT . '/offline/config/modules.php';
        $active = $modules['active_modules'] ?? [];
        $ok = !in_array('accounting', $active, true);
        $this->record('offline modules registry has no accounting', $ok);
    }
}
