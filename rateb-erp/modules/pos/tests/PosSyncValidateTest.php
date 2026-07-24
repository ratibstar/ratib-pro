<?php

declare(strict_types=1);

/**
 * Phase 11 — dry-run sync validate unit tests.
 * Run: php modules/pos/tests/run-pos-sync-validate-tests.php
 */

use Rateb\App\Pos\Services\PosSyncValidateService;

final class PosSyncValidateTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testAcceptsValidPayload();
        $this->testRejectsMissingSyncKey();
        $this->testDryRunFlags();

        return $this->results;
    }

    private function testAcceptsValidPayload(): void
    {
        $svc = new PosSyncValidateService();
        $result = $svc->validate([
            'device_id' => 'dev-1',
            'installation_id' => 'inst-1',
            'sync_key' => 'dev-1+sale-1+2026-01-01T00:00:00Z',
            'sale_id' => 'sale-1',
            'created_at' => '2026-01-01T00:00:00Z',
            'lines' => [
                ['product_id' => 'p1', 'qty' => 1, 'unit_price' => 10, 'line_total' => 10],
            ],
            'totals' => ['total' => 10, 'subtotal' => 10, 'currency' => 'SAR'],
            'reservations' => [],
            'metadata' => ['mode' => 'DRY_RUN_ONLY'],
        ], ['company_id' => 1]);
        $ok = ($result['accepted'] ?? false) === true && ($result['conflicts'] ?? []) === [];
        $this->record('accepts valid payload', $ok, json_encode($result['conflicts'] ?? []));
    }

    private function testRejectsMissingSyncKey(): void
    {
        $svc = new PosSyncValidateService();
        $result = $svc->validate([
            'device_id' => 'dev-1',
            'sale_id' => 'sale-1',
            'created_at' => '2026-01-01T00:00:00Z',
            'lines' => [['product_id' => 'p1', 'qty' => 1]],
            'totals' => ['total' => 1],
        ]);
        $codes = array_map(static fn ($c) => $c['code'] ?? '', $result['conflicts'] ?? []);
        $ok = ($result['accepted'] ?? true) === false && in_array('missing_sync_key', $codes, true);
        $this->record('rejects missing sync_key', $ok, implode(',', $codes));
    }

    private function testDryRunFlags(): void
    {
        $svc = new PosSyncValidateService();
        $result = $svc->validate([
            'device_id' => 'd',
            'sync_key' => 'k',
            'sale_id' => 's',
            'created_at' => 't',
            'lines' => [['product_id' => 'p', 'qty' => 1]],
            'totals' => ['total' => 1],
        ]);
        $ok = ($result['dry_run'] ?? false) === true
            && ($result['mode'] ?? '') === 'DRY_RUN_ONLY'
            && ($result['inventory_deducted'] ?? true) === false
            && ($result['accounting_posted'] ?? true) === false
            && ($result['invoice_created'] ?? true) === false
            && ($result['marked_synced'] ?? true) === false;
        $this->record('dry-run flags', $ok, (string) ($result['mode'] ?? ''));
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}
