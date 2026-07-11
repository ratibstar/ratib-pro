<?php

declare(strict_types=1);

/**
 * Enterprise Baseline v1.2 — freeze certification gate.
 *
 * Run: php offline/tests/run-enterprise-baseline-v12-tests.php
 */

final class EnterpriseBaselineV12CertificationTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testDocsPresent();
        $this->testVersionMatrix();
        $this->testQueueContractFields();
        $this->testIdbVersionFrozen();
        $this->testFlagsDefaultOff();
        $this->testSdkPublicSurface();
        $this->testReplayDispatchAdditive();
        $this->testSwNoClientsClaim();
        $this->testRecruitmentOnlineOwnsLogic();
        $this->testSyncPolicyLimits();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testDocsPresent(): void
    {
        $ok = is_file(RATEB_ROOT . '/offline/docs/ENTERPRISE_BASELINE_V1_2_CERTIFICATION.md')
            && is_file(RATEB_ROOT . '/offline/docs/ENTERPRISE_BASELINE_V1_2_DEVELOPMENT_POLICY.md')
            && is_file(RATEB_ROOT . '/offline/docs/ENTERPRISE_BASELINE_V1_2_EXTENSION_GUIDE.md');
        $this->record('baseline v1.2 docs present', $ok);
    }

    private function testVersionMatrix(): void
    {
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $bundle = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $cert = (string) file_get_contents(RATEB_ROOT . '/offline/docs/ENTERPRISE_BASELINE_V1_2_CERTIFICATION.md');
        $ok = str_contains($sdk, "version: '14.2.0'")
            && str_contains($bundle, '14.2.0')
            && str_contains($cert, 'v1.2')
            && str_contains($cert, 'v1.1');
        $this->record('version matrix aligned (SDK 14.2.0 + Baseline v1.2)', $ok);
    }

    private function testQueueContractFields(): void
    {
        $qm = (string) file_get_contents(RATEB_ROOT . '/offline/client/sync/queue-manager.js');
        $ok = str_contains($qm, 'client_id')
            && str_contains($qm, 'idempotency_key')
            && str_contains($qm, 'module:')
            && str_contains($qm, 'action:')
            && str_contains($qm, 'payload:')
            && str_contains($qm, 'occurred_at')
            && str_contains($qm, 'retry_count')
            && str_contains($qm, 'seq:');
        $this->record('queue contract fields frozen', $ok);
    }

    private function testIdbVersionFrozen(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $ok = str_contains($schema, 'DB_VERSION = 2')
            && str_contains($schema, 'AUTH_VAULT')
            && str_contains($schema, 'SYNC_QUEUE');
        $this->record('IndexedDB DB_VERSION 2 frozen', $ok);
    }

    private function testFlagsDefaultOff(): void
    {
        $cfg = require RATEB_ROOT . '/offline/config/feature-flags.php';
        $defaults = $cfg['defaults'] ?? [];
        $keys = [
            'offline.enabled',
            'offline.inventory.movements',
            'offline.hr.attendance',
            'offline.procurement',
            'offline.procurement.goods_receipt',
            'offline.recruitment',
            'offline.recruitment.candidates',
            'offline.recruitment.workflow',
            'offline.recruitment.assignment',
            'offline.read_cache',
            'offline.auth.unlock',
            'offline.rbac.cache',
            'offline.master_data',
            'offline.pilot.ops_pages',
        ];
        $ok = true;
        foreach ($keys as $k) {
            if (($defaults[$k] ?? true) !== false) {
                $ok = false;
                break;
            }
        }
        $this->record('certified flags default OFF', $ok);
    }

    private function testSdkPublicSurface(): void
    {
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = str_contains($sdk, 'recruitment: function')
            && str_contains($sdk, 'procurement: function')
            && str_contains($sdk, 'inventory: function')
            && str_contains($sdk, 'hr: function')
            && str_contains($sdk, 'mergeFlags:')
            && str_contains($sdk, 'isRecruitmentEnabled');
        $this->record('SDK public surface includes Tier-1 adapters', $ok);
    }

    private function testReplayDispatchAdditive(): void
    {
        $engine = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineReplayEngine.php');
        $ok = str_contains($engine, 'InventoryOfflineReplayService')
            && str_contains($engine, 'HrOfflineReplayService')
            && str_contains($engine, 'ProcurementOfflineReplayService')
            && str_contains($engine, 'RecruitmentOfflineReplayService')
            && str_contains($engine, "module === 'recruitment'");
        $this->record('replay dispatch additive across Tier-1', $ok);
    }

    private function testSwNoClientsClaim(): void
    {
        $sw = (string) file_get_contents(RATEB_ROOT . '/public/rateb-offline-sw.js');
        $ok = !preg_match('/clients\.claim\s*\(/', $sw)
            && str_contains($sw, 'pos-sw');
        $this->record('ERP SW coexistence (no clients.claim)', $ok);
    }

    private function testRecruitmentOnlineOwnsLogic(): void
    {
        $replay = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/RecruitmentOfflineReplayService.php');
        $ok = str_contains($replay, 'CandidateService')
            && str_contains($replay, 'RecruitmentWorkflowService')
            && is_file(RATEB_ROOT . '/migrations/181_recruitment_platform.sql')
            && is_file(RATEB_ROOT . '/app/Services/CandidateService.php');
        $this->record('recruitment online owns business logic', $ok);
    }

    private function testSyncPolicyLimits(): void
    {
        $policy = require RATEB_ROOT . '/offline/config/sync-policy.php';
        $ok = (int) ($policy['client_queue_max'] ?? 0) === 500
            && (int) ($policy['batch_size'] ?? 0) === 50
            && (int) ($policy['max_retries'] ?? 0) === 5;
        $this->record('sync policy limits certified', $ok, json_encode($policy));
    }
}
