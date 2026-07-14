<?php

declare(strict_types=1);

/**
 * Phase 16B — Accounting Offline (Tier 1 drafts) tests.
 *
 * Run: php offline/tests/run-accounting-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\AccountingOfflineMasterDataDirectoryService;
use Rateb\App\Offline\Services\AccountingOfflineReplayService;
use Rateb\App\Offline\Services\AccountingOfflineTenantGuard;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;

final class AccountingOfflinePhase16bTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagsDefaultOff();
        $this->testRequiresMaster();
        $this->testSubflagsRequireAccounting();
        $this->testEntityManifestHasAccounting();
        $this->testModulesRegistryActiveAccounting();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasAccountingAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoPostingReverseClosePaymentsZatcaInReplay();
        $this->testConflictJournalAlreadyPosted();
        $this->testConflictPeriodClosed();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsJournalsWithoutSubflag();
        $this->testQueueAliases();
        $this->testPayloadSanitizerKeepsAccountingModule();
        $this->testAuthzAllowsAccountingAbility();
        $this->testTenantGuardSource();
        $this->testMasterDataEntitiesRegistered();
        $this->testOpsAllowlistAccountingDraftsOnly();
        $this->testOpsFormsAccountingHooks();
        $this->testBackgroundReportsAccountingFlag();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_ACCOUNTING',
            'RATEB_OFFLINE_ACCOUNTING_JOURNALS',
            'RATEB_OFFLINE_ACCOUNTING_WORKFLOW',
            'RATEB_OFFLINE_ACCOUNTING_MASTERDATA',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_ACCOUNTING=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_ACCOUNTING'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_ACCOUNTING_JOURNALS',
            'RATEB_OFFLINE_ACCOUNTING_WORKFLOW',
            'RATEB_OFFLINE_ACCOUNTING_MASTERDATA',
        ] as $k) {
            putenv($k . '=1');
            $_ENV[$k] = '1';
        }
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testFlagsDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.accounting') === false
            && $svc->isAccountingEnabled() === false
            && $svc->isAccountingJournalsEnabled() === false
            && $svc->isAccountingWorkflowEnabled() === false
            && $svc->isAccountingMasterDataEnabled() === false;
        $this->record('accounting flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_ACCOUNTING=1');
        $_ENV['RATEB_OFFLINE_ACCOUNTING'] = '1';
        putenv('RATEB_OFFLINE_ENABLED');
        unset($_ENV['RATEB_OFFLINE_ENABLED']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.accounting') === true && $svc->isAccountingEnabled() === false;
        $this->record('accounting requires master flag', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireAccounting(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_ACCOUNTING_JOURNALS=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_ACCOUNTING_JOURNALS'] = '1';
        putenv('RATEB_OFFLINE_ACCOUNTING');
        unset($_ENV['RATEB_OFFLINE_ACCOUNTING']);
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isAccountingJournalsEnabled() === false;
        $this->record('journals subflag requires accounting', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasAccounting(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset(
            $cfg['accounting_journal_create'],
            $cfg['accounting_workflow_transition'],
            $cfg['accounting_recurring_create'],
            $cfg['chart_of_accounts_directory']
        );
        $this->record('entity manifest has accounting ops + CoA directory', $ok);
    }

    private function testModulesRegistryActiveAccounting(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/modules.php';
        $active = $cfg['active_modules'] ?? [];
        $ops = $cfg['operations'] ?? [];
        $ok = in_array('accounting', $active, true)
            && isset($ops['accounting.journal.create'], $ops['accounting.workflow.transition'], $ops['accounting.note.create']);
        $this->record('modules registry activates accounting ops', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = AccountingOfflineReplayService::deferredActions();
        $ok = in_array('journal.create', $a, true)
            && in_array('journal.update', $a, true)
            && in_array('workflow.transition', $a, true)
            && in_array('recurring.create', $a, true)
            && in_array('opening_balance.create', $a, true)
            && in_array('note.create', $a, true);
        $this->record('deferred actions cover Tier-1 accounting', $ok, implode(',', $a));
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/accounting-adapter.js');
        $ok = str_contains($src, 'journal.create')
            && str_contains($src, 'workflow.transition')
            && str_contains($src, "module: 'accounting'")
            && str_contains($src, 'enqueue')
            && str_contains($src, 'draft:')
            && str_contains($src, 'retry:')
            && str_contains($src, 'status:')
            && str_contains($src, 'sync:')
            && !preg_match('/enqueue\([\'"][^\'"]*post/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*zatca/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*payment/i', $src);
        $this->record('client adapter queues Tier-1 drafts only', $ok);
    }

    private function testSdkBundleHasAccountingAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflineAccountingAdapter')
            && str_contains($src, 'isAccountingEnabled')
            && str_contains($src, 'accounting: function')
            && str_contains($src, "'offline.accounting'")
            && str_contains($src, '14.2.0');
        $this->record('SDK bundle contains accounting adapter', $ok);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/AccountingOfflineReplayService.php');
        $ok = str_contains($src, 'JournalService')
            && str_contains($src, 'AccountingWorkflowService')
            && str_contains($src, 'RecurringJournalService')
            && str_contains($src, 'OpeningBalanceService')
            && !str_contains($src, 'function createDraftJournal')
            && !str_contains($src, 'INSERT INTO rateb_journal_entries');
        $this->record('replay uses existing accounting domain only', $ok);
    }

    private function testNoPostingReverseClosePaymentsZatcaInReplay(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/AccountingOfflineReplayService.php');
        $ok = !preg_match('/->post\(/', $src)
            && !preg_match('/->reverse\(/', $src)
            && !preg_match('/closePeriod|close_period/', $src)
            && !preg_match('/PaymentService|BankReconciliation|Zatca/', $src)
            && str_contains($src, 'accounting_offline_transition_denied');
        $this->record('replay excludes posting/reverse/close/payments/ZATCA', $ok);
    }

    private function testConflictJournalAlreadyPosted(): void
    {
        $r = (new OfflineConflictResolverService())->resolveAccounting(
            ['version' => 2, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'posted']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'journal_already_posted';
        $this->record('conflict journal_already_posted', $ok, json_encode($r));
    }

    private function testConflictPeriodClosed(): void
    {
        $r = (new OfflineConflictResolverService())->resolveAccounting(
            ['version' => 2],
            ['version' => 1, 'status' => 'draft', 'period_closed' => true]
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'period_closed';
        $this->record('conflict period_closed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveAccounting(
            ['version' => 1, 'expected_status' => 'draft'],
            ['version' => 5, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveAccounting(
            ['version' => 3, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when status matches', $ok, json_encode($r));
    }

    private function testReplaySkipsWhenFlagOff(): void
    {
        $this->clearEnv();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'accounting',
            'action' => 'journal.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'journal.create', 'payload' => ['description' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'accounting_offline_disabled';
        $this->record('replay skips accounting when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'accounting',
            'action' => 'journal.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'journal.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped' || ($out['error'] ?? '') !== 'accounting_offline_disabled';
        $ok = $ok && in_array(($out['status'] ?? ''), ['failed', 'synced', 'conflict'], true);
        $this->record('replay engine delegates when flag ON', $ok, json_encode($out));
        $this->clearEnv();
    }

    private function testQueueRejectsWhenFlagOff(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $result = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'acc-off-' . bin2hex(random_bytes(3)),
            'module' => 'accounting',
            'action' => 'journal.create',
            'payload' => ['description' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($result['rejected'] ?? 0) >= 1 && (int) ($result['accepted'] ?? 0) === 0;
        $this->record('queue rejects accounting without flag', $ok, json_encode($result));
        $this->clearEnv();
    }

    private function testQueueRejectsJournalsWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_ACCOUNTING_JOURNALS');
        unset($_ENV['RATEB_OFFLINE_ACCOUNTING_JOURNALS']);
        $result = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'acc-sub-' . bin2hex(random_bytes(3)),
            'module' => 'accounting',
            'action' => 'journal.create',
            'payload' => ['description' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($result['rejected'] ?? 0) >= 1;
        $this->record('queue rejects journal.create without journals subflag', $ok, json_encode($result));
        $this->clearEnv();
    }

    private function testQueueAliases(): void
    {
        $this->enableAll();
        $ref = new \ReflectionClass(OfflineQueueService::class);
        $m = $ref->getMethod('normalizeAccountingAction');
        $m->setAccessible(true);
        $svc = new OfflineQueueService();
        $ok = $m->invoke($svc, 'create_journal') === 'journal.create'
            && $m->invoke($svc, 'journal.update') === 'journal.update'
            && $m->invoke($svc, 'create_opening_balance') === 'opening_balance.create'
            && $m->invoke($svc, 'bogus') === '';
        $this->record('queue accounting action aliases', $ok);
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsAccountingModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'accounting',
            'action' => 'note.create',
            'url' => 'http://evil',
            'payload' => ['body' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'accounting' && !isset($n['url']);
        $this->record('payload sanitizer keeps accounting module', $ok, json_encode($n));
    }

    private function testAuthzAllowsAccountingAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['accounting']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows accounting ability token', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/AccountingOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertJournal')
            && str_contains($src, 'assertAccount')
            && str_contains($src, 'assertPeriod')
            && str_contains($src, 'branch_mismatch')
            && class_exists(AccountingOfflineTenantGuard::class);
        $this->record('tenant guard asserts journal/account/period ownership', $ok);
    }

    private function testMasterDataEntitiesRegistered(): void
    {
        $names = AccountingOfflineMasterDataDirectoryService::entityNames();
        $cfg = require RATEB_ROOT . '/offline/config/master-data-entities.php';
        $entities = $cfg['entities'] ?? [];
        $ok = in_array('chart_of_accounts_directory', $names, true)
            && in_array('accounting_currency_directory', $names, true)
            && in_array('accounting_fiscal_period_directory', $names, true)
            && isset($entities['chart_of_accounts_directory'], $entities['accounting_tax_code_directory']);
        $this->record('master-data accounting directories registered', $ok);
    }

    private function testOpsAllowlistAccountingDraftsOnly(): void
    {
        OfflineModule::resetOpsAllowlistMemo();
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $routes = $cfg['routes'] ?? [];
        $pathJoined = implode(',', array_map('strval', $paths));
        $hasJournal = in_array('journal-entries', $paths, true)
            || isset($routes['journal-entries']);
        $hasPlatform = in_array('accounting/platform', $paths, true)
            || isset($routes['accounting/platform']);
        // Accounting draft browse present; ZATCA stay out of accounting paths (payroll lives in same shared allowlist).
        $ok = $hasJournal
            && $hasPlatform
            && !str_contains($pathJoined, 'zatca');
        $this->record('ops allowlist includes accounting draft browse paths', $ok);
    }

    private function testOpsFormsAccountingHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, 'journal.create')
            && str_contains($src, 'offline.accounting')
            && str_contains($src, 'isAccountingPostPath')
            && str_contains($src, 'RatebOfflineAccountingAdapter');
        $this->record('ops forms accounting hooks present', $ok);
    }

    private function testBackgroundReportsAccountingFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('accounting_enabled', $stats)
            && ($stats['accounting_enabled'] ?? true) === false;
        $this->record('background reports accounting_enabled', $ok, json_encode($stats));
    }

    private function testFoundationUntouchedMarkers(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = str_contains($schema, 'DB_VERSION') && preg_match('/DB_VERSION\s*=\s*2/', $schema)
            && str_contains($sdk, "version: '14.2.0'");
        $this->record('foundation markers untouched (IDB v2, SDK 14.2.0)', $ok);
    }
}
