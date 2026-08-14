<?php

declare(strict_types=1);

/**
 * Phase E — Feature-flagged payroll → accounting adapter (source + config analysis).
 *
 * Run: php tests/hr/run-hr-phase-e-accounting-tests.php
 */
final class HrPhaseEAccountingTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testFlagDefaultsOff();
        $this->testFlagOnlyExplicitOn();
        $this->testAdapterSkipsWhenFlagOff();
        $this->testPostPayrollWiresAdapterBehindFlag();
        $this->testAdapterUsesAccountingServiceDraftOnly();
        $this->testAdapterDoesNotRecalculatePayroll();
        $this->testAccountCodesConfigurableNotHardcodedOnly();
        $this->testCompanyIsolationChecks();
        $this->testRequiresPostedPayroll();
        $this->testFiscalPeriodGuard();
        $this->testIdempotencyMarker();
        $this->testFailureAudited();
        $this->testNoBankWps();
        $this->testNoPayroll2Accounting2();
        $this->testWorkflowUnchanged();
        $this->testReconciliationAwareOfFlag();
        $this->testPhaseDContractFlagOff();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testFlagDefaultsOff(): void
    {
        $prev = $_ENV['HR_PAYROLL_ACCOUNTING_ENABLED'] ?? null;
        unset($_ENV['HR_PAYROLL_ACCOUNTING_ENABLED']);
        putenv('HR_PAYROLL_ACCOUNTING_ENABLED');
        $off = !\Rateb\App\Services\HrPayrollAccountingConfig::isEnabled();
        if ($prev !== null) {
            $_ENV['HR_PAYROLL_ACCOUNTING_ENABLED'] = $prev;
            putenv('HR_PAYROLL_ACCOUNTING_ENABLED=' . $prev);
        } else {
            unset($_ENV['HR_PAYROLL_ACCOUNTING_ENABLED']);
            putenv('HR_PAYROLL_ACCOUNTING_ENABLED');
        }
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollAccountingConfig.php');
        $ok = $off && str_contains($src, 'return false');
        $this->record('Feature flag defaults OFF when env unset', $ok);
    }

    private function testFlagOnlyExplicitOn(): void
    {
        $_ENV['HR_PAYROLL_ACCOUNTING_ENABLED'] = 'false';
        putenv('HR_PAYROLL_ACCOUNTING_ENABLED=false');
        $a = !\Rateb\App\Services\HrPayrollAccountingConfig::isEnabled();
        $_ENV['HR_PAYROLL_ACCOUNTING_ENABLED'] = 'true';
        putenv('HR_PAYROLL_ACCOUNTING_ENABLED=true');
        $b = \Rateb\App\Services\HrPayrollAccountingConfig::isEnabled();
        unset($_ENV['HR_PAYROLL_ACCOUNTING_ENABLED']);
        putenv('HR_PAYROLL_ACCOUNTING_ENABLED');
        $this->record('Flag enables only for true/1/on/yes', $a && $b);
    }

    private function testAdapterSkipsWhenFlagOff(): void
    {
        unset($_ENV['HR_PAYROLL_ACCOUNTING_ENABLED']);
        putenv('HR_PAYROLL_ACCOUNTING_ENABLED');
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollAccountingAdapter.php');
        $ok = str_contains($src, "status' => 'skipped'")
            && str_contains($src, "reason' => 'flag_off'")
            && str_contains($src, 'HrPayrollAccountingConfig::isEnabled()');
        // Live call without DB period still returns skipped
        $res = (new \Rateb\App\Services\HrPayrollAccountingAdapter())->ensureDraftJournal(0);
        $ok = $ok && ($res['status'] ?? '') === 'skipped' && ($res['flag_enabled'] ?? true) === false;
        $this->record('Adapter no-ops when flag OFF', $ok);
    }

    private function testPostPayrollWiresAdapterBehindFlag(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ok = preg_match(
            '/function postPayroll[\s\S]*?HrPayrollAccountingConfig::isEnabled\(\)[\s\S]*?HrPayrollAccountingAdapter/',
            $src
        ) === 1;
        $this->record('postPayroll calls adapter only behind feature flag', (bool) $ok);
    }

    private function testAdapterUsesAccountingServiceDraftOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollAccountingAdapter.php');
        $ok = str_contains($src, 'createManualDraft')
            && !str_contains($src, 'createPostedEntry')
            && str_contains($src, 'new AccountingService()')
            && str_contains($src, 'DRAFT');
        $this->record('Adapter uses AccountingService createManualDraft (not createPostedEntry)', $ok);
    }

    private function testAdapterDoesNotRecalculatePayroll(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollAccountingAdapter.php');
        $ok = str_contains($src, 'SUM(basic_salary)')
            && str_contains($src, 'SUM(net_salary)')
            && !str_contains($src, 'salary_base / 30')
            && !str_contains($src, 'generatePayrollLines');
        $this->record('Adapter maps payroll line sums — does not recalculate', $ok);
    }

    private function testAccountCodesConfigurableNotHardcodedOnly(): void
    {
        $cfg = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollAccountingConfig.php');
        $ok = str_contains($cfg, 'HR_PAYROLL_EXPENSE_ACCOUNT_CODE')
            && str_contains($cfg, 'DEFAULT_EXPENSE_CODE = \'5020101\'')
            && str_contains($cfg, 'DEFAULT_PAYABLE_CODE = \'20105\'');
        $this->record('Account mapping via config/env defaults (Saudi COA codes)', $ok);
    }

    private function testCompanyIsolationChecks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollAccountingAdapter.php');
        $ok = str_contains($src, 'tenant_mismatch')
            && str_contains($src, 'journal_company_mismatch')
            && str_contains($src, 'expectedCompanyId');
        $this->record('Adapter enforces payroll/accounting company isolation', $ok);
    }

    private function testRequiresPostedPayroll(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollAccountingAdapter.php');
        $ok = str_contains($src, "payrollStatus !== 'posted'")
            && str_contains($src, 'payroll_not_posted');
        $this->record('Adapter denies accounting for non-posted payroll', $ok);
    }

    private function testFiscalPeriodGuard(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollAccountingAdapter.php');
        $ok = str_contains($src, 'periodBlocksPosting')
            && str_contains($src, 'period_closed');
        $this->record('Adapter validates fiscal period (no auto-open)', $ok);
    }

    private function testIdempotencyMarker(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollAccountingAdapter.php');
        $ok = str_contains($src, 'MARKER_PREFIX')
            && str_contains($src, 'already_exists')
            && str_contains($src, 'findExistingJournalId');
        $this->record('Idempotency via HR_PAYROLL_PERIOD marker', $ok);
    }

    private function testFailureAudited(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollAccountingAdapter.php');
        $ok = str_contains($src, 'payroll_accounting_attempted')
            && str_contains($src, 'payroll_accounting_posted')
            && str_contains($src, 'payroll_accounting_failed');
        $this->record('Accounting attempt/success/failure audited', $ok);
    }

    private function testNoBankWps(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollAccountingAdapter.php');
        $ok = !str_contains($src, 'WPS')
            && !str_contains($src, 'BankTransfer')
            && !str_contains($src, 'initiateTransfer')
            && !str_contains($src, 'PaymentGateway');
        $this->record('No Bank/WPS implementation in adapter', $ok);
    }

    private function testNoPayroll2Accounting2(): void
    {
        $files = [
            RATEB_ROOT . '/app/services/HrPayrollAccountingAdapter.php',
            RATEB_ROOT . '/app/services/HrPayrollAccountingConfig.php',
            RATEB_ROOT . '/app/services/HrService.php',
        ];
        $ok = true;
        foreach ($files as $f) {
            $s = (string) file_get_contents($f);
            if (str_contains($s, 'Payroll2') || str_contains($s, 'Accounting2') || str_contains($s, 'PayrollAccountingService2')) {
                $ok = false;
            }
        }
        $this->record('No Payroll2 / Accounting2 engines', $ok);
    }

    private function testWorkflowUnchanged(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        $ok = preg_match(
            '/function postPayroll[\s\S]*?\$status !== \'approved\'[\s\S]*?payroll_not_approved/',
            $src
        ) === 1;
        $ok = $ok && preg_match(
            '/function approvePayroll[\s\S]*?!== \'draft\'[\s\S]*?payroll_not_draft/',
            $src
        ) === 1;
        $this->record('Payroll workflow draft→approved→posted unchanged', (bool) $ok);
    }

    private function testReconciliationAwareOfFlag(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrPayrollIntegrityService.php');
        $ok = str_contains($src, 'accounting_flag_enabled')
            && str_contains($src, 'missing_while_flag_on')
            && str_contains($src, 'findExistingJournalId');
        $this->record('Reconciliation diagnostic reports accounting marker state', $ok);
    }

    private function testPhaseDContractFlagOff(): void
    {
        unset($_ENV['HR_PAYROLL_ACCOUNTING_ENABLED']);
        putenv('HR_PAYROLL_ACCOUNTING_ENABLED');
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/HrService.php');
        // Direct AccountingService must not be invoked inside postPayroll body
        $ok = preg_match('/function postPayroll\s*\((.*?)(?=\n    public function |\n    private function loadPayrollPeriod)/s', $src, $m) === 1;
        $block = $m[0] ?? '';
        $ok = $ok && !str_contains($block, 'new AccountingService')
            && !str_contains($block, 'createPostedEntry')
            && str_contains($block, "'gl_posted' => false")
            && !\Rateb\App\Services\HrPayrollAccountingConfig::isEnabled();
        $this->record('Flag OFF preserves Phase D no-GL contract on postPayroll', $ok);
    }
}
