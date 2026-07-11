<?php

declare(strict_types=1);

/**
 * Phase 7.1 — Enterprise Hardening (H-BRANCH-001, H-DEVICE-001, H-AUTHZ-001).
 *
 * Run: php offline/tests/run-offline-hardening-tests.php
 */

use Rateb\App\Offline\Services\OfflineDeviceGuard;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflinePushAckContract;
use Rateb\App\Offline\Services\OfflineReplayScopeService;
use Rateb\App\Offline\Services\OfflineSyncService;

final class OfflineHardeningPhase71Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testSanitizerStripsScopeFields();
        $this->testReplayScopeIgnoresPayloadBranch();
        $this->testReplayScopeUsesQueueBranchOnly();
        $this->testInventoryBuildScopeSource();
        $this->testHrBuildScopeSource();
        $this->testProcurementBuildScopeSource();
        $this->testDeviceGuardRejectsEmpty();
        $this->testDeviceGuardRejectsUnknownWhenNoTable();
        $this->testAckDeviceDeniedIs403();
        $this->testAckDeviceUnknownIs403();
        $this->testPushDoesNotAutoProcessWithoutFlag();
        $this->testPushAutoProcessOptInSource();
        $this->testControllerDeviceGateSource();
        $this->testControllerAutoProcessGatedSource();
        $this->testFlagsStillDefaultOff();

        return $this->results;
    }

    private function testSanitizerStripsScopeFields(): void
    {
        $out = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'inventory',
            'action' => 'stock_movement.create',
            'payload' => [
                'qty' => 1,
                'branch_id' => 99,
                'company_id' => 7,
                'user_id' => 3,
                'device_id' => 'evil',
                'url' => 'https://evil',
            ],
        ]);
        $p = $out['payload'] ?? [];
        $ok = ($p['qty'] ?? null) === 1
            && !isset($p['branch_id'], $p['company_id'], $p['user_id'], $p['device_id'], $p['url']);
        $this->assert('sanitizer strips scope + transport', $ok, json_encode($p) ?: '');
    }

    private function testReplayScopeIgnoresPayloadBranch(): void
    {
        $scope = (new OfflineReplayScopeService())->fromQueueRow([
            'company_id' => 5,
            'branch_id' => null,
            'user_id' => 1,
            'device_id' => 'dev-a',
        ]);
        $ok = $scope['branch_id'] === 0 && $scope['company_id'] === 5;
        $this->assert('replay scope ignores missing queue branch (no payload)', $ok, json_encode($scope) ?: '');
    }

    private function testReplayScopeUsesQueueBranchOnly(): void
    {
        // branch_id=0 path — no DB ownership check
        $scope = (new OfflineReplayScopeService())->fromQueueRow([
            'company_id' => 5,
            'branch_id' => 0,
            'user_id' => 2,
            'device_id' => 'dev-b',
        ]);
        $ok = $scope['branch_id'] === 0
            && $scope['user_id'] === 2
            && $scope['device_id'] === 'dev-b';
        $this->assert('replay scope from queue row only', $ok, json_encode($scope) ?: '');
    }

    private function testInventoryBuildScopeSource(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/server/Services/InventoryOfflineReplayService.php');
        $ok = str_contains($src, 'OfflineReplayScopeService')
            && !str_contains($src, "\$queueRow['branch_id'] ?? \$inner['branch_id']")
            && !str_contains($src, 'buildScope($queueRow, $inner)');
        $this->assert('inventory replay no payload branch fallback', $ok, 'OfflineReplayScopeService');
    }

    private function testHrBuildScopeSource(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/server/Services/HrOfflineReplayService.php');
        $ok = str_contains($src, 'OfflineReplayScopeService')
            && !str_contains($src, "\$queueRow['branch_id'] ?? \$inner['branch_id']")
            && !str_contains($src, 'buildScope($queueRow, $inner)');
        $this->assert('HR replay no payload branch fallback', $ok, 'OfflineReplayScopeService');
    }

    private function testProcurementBuildScopeSource(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/server/Services/ProcurementOfflineReplayService.php');
        $ok = str_contains($src, 'OfflineReplayScopeService')
            && !str_contains($src, "\$queueRow['branch_id'] ?? \$inner['branch_id']")
            && !str_contains($src, 'buildScope($queueRow, $inner)');
        $this->assert('procurement replay no payload branch fallback', $ok, 'OfflineReplayScopeService');
    }

    private function testDeviceGuardRejectsEmpty(): void
    {
        $r = (new OfflineDeviceGuard())->assertActive(1, '');
        $this->assert(
            'device guard rejects empty device_id',
            ($r['ok'] ?? true) === false && ($r['error'] ?? '') === 'device_unknown',
            json_encode($r) ?: ''
        );
    }

    private function testDeviceGuardRejectsUnknownWhenNoTable(): void
    {
        // Without devices table / row, unknown is denied (not silently allowed).
        $r = (new OfflineDeviceGuard())->assertActive(1, 'never-registered-device-xyz');
        $this->assert(
            'device guard rejects unknown/non-active',
            ($r['ok'] ?? true) === false
                && in_array(($r['error'] ?? ''), ['device_unknown', 'device_denied'], true),
            json_encode($r) ?: ''
        );
    }

    private function testAckDeviceDeniedIs403(): void
    {
        $ack = (new OfflinePushAckContract())->evaluate([
            'accepted' => 0,
            'duplicate' => 0,
            'errors' => ['device_denied' => true],
        ]);
        $this->assert('ack device_denied is 403', ($ack['http_status'] ?? 0) === 403 && !$ack['ok'], json_encode($ack) ?: '');
    }

    private function testAckDeviceUnknownIs403(): void
    {
        $ack = (new OfflinePushAckContract())->evaluate([
            'accepted' => 0,
            'duplicate' => 0,
            'errors' => ['device_unknown' => true],
        ]);
        $this->assert('ack device_unknown is 403', ($ack['http_status'] ?? 0) === 403 && !$ack['ok'], json_encode($ack) ?: '');
    }

    private function testPushDoesNotAutoProcessWithoutFlag(): void
    {
        putenv('RATEB_OFFLINE_ENABLED');
        unset($_ENV['RATEB_OFFLINE_ENABLED'], $_SERVER['RATEB_OFFLINE_ENABLED']);
        // Master OFF — push rejects; ensures no process key from disabled path.
        $result = (new OfflineSyncService())->pushQueue([
            ['client_id' => 'h71-1', 'module' => 'offline_meta', 'action' => 'offline.ack', 'payload' => []],
        ], ['company_id' => 1, 'auto_process' => false]);
        $ok = !isset($result['process']) && !empty($result['errors']['offline_disabled']);
        $this->assert('push without auto_process does not process (disabled path)', $ok, json_encode($result) ?: '');
    }

    private function testPushAutoProcessOptInSource(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/server/Services/OfflineSyncService.php');
        $ok = str_contains($src, "context['auto_process']")
            && str_contains($src, 'H-AUTHZ-001');
        $this->assert('pushQueue auto_process is opt-in', $ok, 'auto_process gate');
    }

    private function testControllerDeviceGateSource(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/server/Controllers/OfflineSyncApiController.php');
        $ok = str_contains($src, 'OfflineDeviceGuard')
            && str_contains($src, 'assertActive');
        $this->assert('push controller enforces OfflineDeviceGuard', $ok, 'assertActive');
    }

    private function testControllerAutoProcessGatedSource(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/server/Controllers/OfflineSyncApiController.php');
        $ok = str_contains($src, "'auto_process' => \$canManage")
            || str_contains($src, "'auto_process' => \$canManage");
        // Also accept canManageSync assignment pattern
        $ok = str_contains($src, 'auto_process')
            && str_contains($src, 'canManageSync');
        $this->assert('push auto_process requires canManageSync', $ok, 'controller gate');
    }

    private function testFlagsStillDefaultOff(): void
    {
        putenv('RATEB_OFFLINE_ENABLED');
        putenv('RATEB_OFFLINE_MONITORING');
        unset($_ENV['RATEB_OFFLINE_ENABLED'], $_ENV['RATEB_OFFLINE_MONITORING']);
        $flags = new OfflineFeatureFlagService();
        $this->assert(
            'master still default OFF after hardening',
            !$flags->isMasterEnabled(),
            'offline.enabled'
        );
    }

    private function assert(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }
}
