<?php

declare(strict_types=1);

/**
 * Phase 12 — acceptance service unit tests (no DB / inventory / accounting).
 * Run: php modules/pos/tests/run-pos-sync-acceptance-tests.php
 */

use Rateb\App\Pos\Services\PosSyncAcceptanceService;
use Rateb\App\Pos\Services\PosSyncValidateService;

final class PosSyncAcceptanceTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testRejectsWithoutCompany();
        $this->testRejectsInvalidPayload();
        $this->testValidateSharedContract();

        return $this->results;
    }

    private function testRejectsWithoutCompany(): void
    {
        $svc = new PosSyncAcceptanceService();
        $result = $svc->accept([
            'device_id' => 'd1',
            'sync_key' => 'k1',
            'sale_id' => 's1',
            'created_at' => '2026-01-01T00:00:00Z',
            'lines' => [['product_id' => 'p1', 'qty' => 1]],
            'totals' => ['total' => 1],
        ], ['company_id' => 0]);
        $ok = ($result['accepted'] ?? true) === false
            && ($result['waiting_commit'] ?? true) === false
            && ($result['marked_synced'] ?? true) === false
            && ($result['inventory_deducted'] ?? true) === false;
        $this->record('rejects without company', $ok, (string) ($result['reason'] ?? ''));
    }

    private function testRejectsInvalidPayload(): void
    {
        $svc = new PosSyncAcceptanceService();
        $result = $svc->accept([
            'device_id' => '',
            'sync_key' => '',
            'sale_id' => '',
            'lines' => [],
            'totals' => [],
        ], ['company_id' => 1]);
        $ok = ($result['accepted'] ?? true) === false
            && ($result['waiting_commit'] ?? true) === false
            && is_array($result['conflicts'] ?? null)
            && count($result['conflicts']) > 0;
        $this->record('rejects invalid payload', $ok, (string) count($result['conflicts'] ?? []));
    }

    private function testValidateSharedContract(): void
    {
        $v = new PosSyncValidateService();
        $okPayload = $v->validate([
            'device_id' => 'd',
            'installation_id' => 'i',
            'sync_key' => 'k',
            'sale_id' => 's',
            'created_at' => 't',
            'lines' => [['product_id' => 'p', 'qty' => 2]],
            'totals' => ['total' => 20],
        ], ['company_id' => 1]);
        $ok = ($okPayload['accepted'] ?? false) === true
            && ($okPayload['inventory_deducted'] ?? true) === false
            && ($okPayload['accounting_posted'] ?? true) === false;
        $this->record('shared validate ok flags', $ok, (string) ($okPayload['mode'] ?? ''));
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}
