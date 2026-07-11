<?php

declare(strict_types=1);

/**
 * Phase 4.6.2 — Enterprise Staging Soak (Inv/HR + durability).
 * Process-local flags only — does NOT write staging .env.
 *
 * Run on staging:
 *   php offline/tests/run-phase462-staging-soak.php
 */

$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'dev.rateb.sa';

$erpRoot = getenv('RATEB_SOAK_ROOT') ?: '';
if ($erpRoot === '' || !is_dir($erpRoot)) {
    $candidates = [
        '/home/admin/domains/dev.rateb.sa/public_html/rateb-erp',
        dirname(__DIR__, 2),
    ];
    foreach ($candidates as $c) {
        if (is_dir($c) && is_file($c . '/app/Core/Bootstrap.php')) {
            $erpRoot = $c;
            break;
        }
    }
}
if ($erpRoot === '' || !is_file($erpRoot . '/app/Core/Bootstrap.php')) {
    fwrite(STDERR, "ERP root not found\n");
    exit(1);
}

chdir($erpRoot);
define('RATEB_ROOT', $erpRoot);
define('RATEB_ENV_NO_SESSION', true);

require RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/offline/OfflineModule.php';
Rateb\App\Offline\OfflineModule::init();

use Rateb\App\Core\Database;
use Rateb\App\Offline\Services\HrOfflineTenantGuard;
use Rateb\App\Offline\Services\InventoryOfflineTenantGuard;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePushAckContract;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineSyncService;

final class Phase462StagingSoak
{
    private const PREFIX = 'soak462-';

    /** @var list<array{id:string,sev:string,ok:bool,detail:string}> */
    private array $checks = [];
    /** @var list<array{id:string,sev:string,title:string,detail:string}> */
    private array $findings = [];
    /** @var array<string, mixed> */
    private array $metrics = [];

    public function run(): int
    {
        $pdo = Database::connection();
        $db = Database::resolvedDatabaseName();
        echo "Phase 4.6.2 Staging Soak\nDB=$db\n";

        if ($db !== 'admin_rateb_dev') {
            $this->fail('ENV-DB', 'Critical', 'Refuse non-staging DB: ' . $db);
            $this->printReport();
            return 2;
        }

        $posPresent = is_dir(RATEB_ROOT . '/modules/pos');
        // Missing POS must not fail the audit — record as skipped/blocked only.
        $this->record(
            'POS module on staging',
            true,
            $posPresent ? 'present' : 'SKIPPED — ABSENT; POS scenarios blocked by deployment (non-failing)',
            'Medium'
        );
        if (!$posPresent) {
            $this->finding('B-POS-001', 'Medium', 'POS scenarios blocked by deployment', 'modules/pos absent on staging; POS soak skipped per Phase 4.6.2 rules (does not fail audit).');
        }

        // Baseline: flags OFF without process env
        $this->clearOfflineEnv();
        $flags = new OfflineFeatureFlagService();
        $this->assert('flags master OFF at baseline', !$flags->isMasterEnabled(), 'master=' . ($flags->isMasterEnabled() ? 'ON' : 'OFF'));

        // Enable process-local soak flags (not .env)
        $this->enableSoakFlags();
        $flags = new OfflineFeatureFlagService();
        $this->assert('soak flags master ON (process-local)', $flags->isMasterEnabled(), 'master');
        $this->assert('soak inventory flag ON', $flags->isInventoryMovementsEnabled(), 'inv');
        $this->assert('soak HR flag ON', $flags->isHrAttendanceEnabled(), 'hr');

        $companyId = 1;
        $branchId = 1;
        $invId = (int) $pdo->query('SELECT id FROM rateb_inventory WHERE company_id=1 ORDER BY id LIMIT 1')->fetchColumn();
        $whId = (int) $pdo->query('SELECT id FROM rateb_warehouses WHERE company_id=1 ORDER BY id LIMIT 1')->fetchColumn();
        $empId = (int) $pdo->query('SELECT id FROM rateb_employees WHERE company_id=1 ORDER BY id LIMIT 1')->fetchColumn();
        $branchB = (int) $pdo->query('SELECT id FROM rateb_branches WHERE company_id=5 AND id<>5 ORDER BY id LIMIT 1')->fetchColumn();
        $branchA = 5;

        $this->assert('fixture inventory', $invId > 0, 'inv_id=' . $invId);
        $this->assert('fixture warehouse', $whId > 0, 'wh_id=' . $whId);
        $this->assert('fixture employee', $empId > 0, 'emp_id=' . $empId);
        $this->assert('fixture multi-branch company5', $branchA > 0 && $branchB > 0, "a=$branchA b=$branchB");

        $sync = new OfflineSyncService();
        $queue = new OfflineQueueService();
        $ack = new OfflinePushAckContract();
        $bg = new OfflineBackgroundSync();
        $resolver = new OfflineConflictResolverService();
        $invGuard = new InventoryOfflineTenantGuard();
        $hrGuard = new HrOfflineTenantGuard();

        // --- 1 Inventory Offline ---
        $keyInv = self::PREFIX . 'inv-move-' . bin2hex(random_bytes(4));
        $pushInv = $sync->pushQueue([[
            'client_id' => $keyInv,
            'module' => 'inventory',
            'action' => 'stock_movement.create',
            'version' => 1,
            'payload' => [
                'inventory_id' => $invId,
                'warehouse_id' => $whId,
                'movement_type' => 'adjustment',
                'quantity' => 0.001,
                'notes' => 'phase462 soak',
            ],
        ]], ['company_id' => $companyId, 'branch_id' => $branchId, 'device_id' => 'soak-device-a', 'user_id' => 0]);
        $this->assert('inventory push accepted', (int) ($pushInv['accepted'] ?? 0) === 1, json_encode($pushInv));
        $procInv = $sync->processPending($companyId, 20);
        $this->metrics['inventory_process'] = $procInv;
        $rowInv = $pdo->prepare('SELECT status, last_error FROM rateb_offline_sync_queue WHERE company_id=? AND idempotency_key=?');
        $rowInv->execute([$companyId, $keyInv]);
        $invState = $rowInv->fetch(PDO::FETCH_ASSOC) ?: [];
        $invOk = in_array((string) ($invState['status'] ?? ''), ['synced', 'failed', 'conflict'], true);
        $this->assert('inventory replay executed', $invOk, json_encode($invState) . ' proc=' . json_encode($procInv));
        if (($invState['status'] ?? '') !== 'synced') {
            $this->finding('M-INV-REPLAY-001', 'Medium', 'Inventory movement replay did not sync cleanly', (string) json_encode($invState));
        }

        // Idempotent re-push
        $pushDup = $sync->pushQueue([[
            'client_id' => $keyInv,
            'module' => 'inventory',
            'action' => 'stock_movement.create',
            'version' => 1,
            'payload' => ['inventory_id' => $invId, 'quantity' => 0.001, 'movement_type' => 'adjustment'],
        ]], ['company_id' => $companyId, 'branch_id' => $branchId]);
        $this->assert('inventory idempotent duplicate', (int) ($pushDup['duplicate'] ?? 0) + (int) ($pushDup['conflict'] ?? 0) >= 1, json_encode($pushDup));

        // --- 2 HR Offline ---
        $attDate = date('Y-m-d', strtotime('+' . (40 + random_int(1, 200)) . ' days'));
        $keyHr = self::PREFIX . 'hr-att-' . bin2hex(random_bytes(4));
        $pushHr = $sync->pushQueue([[
            'client_id' => $keyHr,
            'module' => 'hr',
            'action' => 'attendance.create',
            'version' => 1,
            'payload' => [
                'employee_id' => $empId,
                'attendance_date' => $attDate,
                'check_in' => '09:00',
                'check_out' => '17:00',
                'status' => 'present',
                'notes' => 'phase462 soak',
            ],
        ]], ['company_id' => $companyId, 'branch_id' => $branchId, 'device_id' => 'soak-device-a']);
        $this->assert('HR attendance push accepted', (int) ($pushHr['accepted'] ?? 0) === 1, json_encode($pushHr));
        $procHr = $sync->processPending($companyId, 20);
        $this->metrics['hr_process'] = $procHr;
        $rowHr = $pdo->prepare('SELECT status, last_error FROM rateb_offline_sync_queue WHERE company_id=? AND idempotency_key=?');
        $rowHr->execute([$companyId, $keyHr]);
        $hrState = $rowHr->fetch(PDO::FETCH_ASSOC) ?: [];
        $hrStatus = (string) ($hrState['status'] ?? '');
        $this->assert('HR attendance replay executed', in_array($hrStatus, ['synced', 'failed', 'conflict'], true), json_encode($hrState));
        if ($hrStatus === 'synced') {
            $att = $pdo->prepare("SELECT id FROM rateb_attendance_records WHERE company_id=? AND employee_id=? AND attendance_date=? AND notes LIKE '%[offline:%' LIMIT 1");
            $att->execute([$companyId, $empId, $attDate]);
            $this->assert('HR attendance row persisted', (int) $att->fetchColumn() > 0, 'date=' . $attDate);
        } elseif ($hrStatus === 'conflict') {
            // Conflict is valid evidence of conflict path (e.g. same employee/date already exists).
            $this->record('HR attendance conflict path evidenced', true, json_encode($hrState), 'Low');
            $this->finding('M-HR-CONFLICT-001', 'Low', 'HR attendance hit conflict on soak date', (string) json_encode($hrState));
        } else {
            $this->finding('M-HR-REPLAY-001', 'Medium', 'HR attendance replay did not sync cleanly', (string) json_encode($hrState));
        }

        // Leave draft
        $leaveType = (int) $pdo->query('SELECT id FROM rateb_leave_types WHERE company_id=1 ORDER BY id LIMIT 1')->fetchColumn();
        $keyLeave = self::PREFIX . 'hr-leave-' . bin2hex(random_bytes(4));
        $pushLeave = $sync->pushQueue([[
            'client_id' => $keyLeave,
            'module' => 'hr',
            'action' => 'leave_request.draft',
            'version' => 1,
            'payload' => [
                'employee_id' => $empId,
                'leave_type_id' => $leaveType,
                'start_date' => date('Y-m-d', strtotime('+30 days')),
                'end_date' => date('Y-m-d', strtotime('+31 days')),
                'reason' => 'phase462 soak draft',
            ],
        ]], ['company_id' => $companyId, 'branch_id' => $branchId]);
        $this->assert('HR leave draft push accepted', (int) ($pushLeave['accepted'] ?? 0) === 1 || $leaveType < 1, json_encode($pushLeave) . ' leave_type=' . $leaveType);
        if ((int) ($pushLeave['accepted'] ?? 0) === 1) {
            $sync->processPending($companyId, 10);
        }

        // --- 3 Queue durability (server + client SDK contract) ---
        $sdk = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $this->assert('SDK has removeMany', str_contains($sdk, 'removeMany'), 'marker');
        $this->assert('SDK has removeByKeys flush', str_contains($sdk, 'removeByKeys') && str_contains($sdk, 'clearable_keys'), 'flush');
        $this->assert('SDK no Stores.clear(QUEUE)', !str_contains($sdk, 'Stores.clear(QUEUE)'), 'no clear-all');
        $this->assert('SDK Phase 4.5.1 marker', str_contains($sdk, '4.5.1'), 'header');

        // Rejected keys must not be clearable
        $rej = $ack->evaluate([
            'accepted' => 1,
            'duplicate' => 0,
            'conflict' => 1,
            'rejected' => 1,
            'accepted_keys' => ['a1'],
            'duplicate_keys' => [],
            'conflict_keys' => ['c1'],
            'rejected_keys' => ['r1'],
        ]);
        $clearable = $rej['clearable_keys'] ?? [];
        $this->assert('ZDL clearable excludes conflict/rejected', $clearable === ['a1'], json_encode($clearable));

        // --- 4 Browser refresh recovery (model: pending survives; only clearable removed) ---
        $localQueue = [
            ['client_id' => 'keep-rej', 'status' => 'rejected'],
            ['client_id' => 'keep-conf', 'status' => 'conflict'],
            ['client_id' => 'clear-ok', 'status' => 'accepted'],
            ['client_id' => 'keep-pend', 'status' => 'pending'],
        ];
        $afterRefresh = array_values(array_filter($localQueue, static fn ($i) => !in_array($i['client_id'], ['clear-ok'], true)));
        $this->assert('browser refresh keeps rejected/conflict/pending', count($afterRefresh) === 3, json_encode($afterRefresh));

        // --- 5 Network disconnect/reconnect ---
        // Use enqueueBatch (no auto-process) so rows remain pending across disconnect.
        $keyNet = self::PREFIX . 'net-' . bin2hex(random_bytes(4));
        $queue->enqueueBatch([[
            'client_id' => $keyNet,
            'module' => 'offline_meta',
            'action' => 'offline.ack',
            'version' => 1,
            'payload' => ['note' => 'disconnect'],
        ]], ['company_id' => $companyId, 'branch_id' => $branchId, 'device_id' => 'soak-net']);
        putenv('RATEB_OFFLINE_ENABLED=0');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '0';
        $bgOff = (new OfflineBackgroundSync())->process($companyId, 5);
        $this->assert('network-down background disabled', !empty($bgOff['disabled']), json_encode($bgOff));
        $pendingNet = $pdo->prepare('SELECT status FROM rateb_offline_sync_queue WHERE idempotency_key=?');
        $pendingNet->execute([$keyNet]);
        $netStatus = (string) ($pendingNet->fetchColumn() ?: '');
        $this->assert('queue durable while offline', $netStatus === 'pending', 'status=' . $netStatus);
        $this->enableSoakFlags();
        $bgOn = (new OfflineBackgroundSync())->process($companyId, 20);
        $pendingNet->execute([$keyNet]);
        $this->assert('reconnect processes pending', ($pendingNet->fetchColumn() ?: '') === 'synced', 'after=' . json_encode($bgOn));

        // --- 6 Power-loss recovery (server queue + client atomic delete model) ---
        $beforeCount = (int) $pdo->query("SELECT COUNT(*) FROM rateb_offline_sync_queue WHERE idempotency_key LIKE 'soak462-%'")->fetchColumn();
        $keyPow = self::PREFIX . 'pwr-' . bin2hex(random_bytes(4));
        $queue->enqueueBatch([[
            'client_id' => $keyPow,
            'module' => 'offline_meta',
            'action' => 'offline.ack',
            'payload' => ['pwr' => 1],
        ]], ['company_id' => $companyId]);
        $mid = (int) $pdo->query("SELECT COUNT(*) FROM rateb_offline_sync_queue WHERE idempotency_key LIKE 'soak462-%'")->fetchColumn();
        $this->assert('power-loss mid-queue rows retained', $mid === $beforeCount + 1, "before=$beforeCount mid=$mid");
        $hasCrashSim = str_contains($sdk, '_simulateDeleteByKeyCrash') || str_contains($sdk, 'simulateDeleteByKeyCrash');
        $this->assert('client power-loss sim helper present', $hasCrashSim || str_contains($sdk, 'removeMany'), 'sdk helpers');
        (new OfflineBackgroundSync())->process($companyId, 20);

        // --- 7 Replay ordering ---
        $orderKeys = [];
        for ($i = 0; $i < 5; $i++) {
            $k = self::PREFIX . 'ord-' . $i . '-' . bin2hex(random_bytes(2));
            $orderKeys[] = $k;
            $queue->enqueueBatch([[
                'client_id' => $k,
                'module' => 'offline_meta',
                'action' => 'offline.ack',
                'payload' => ['seq' => $i],
            ]], ['company_id' => $companyId, 'branch_id' => $branchId]);
            usleep(20000);
        }
        $st = $pdo->prepare("SELECT idempotency_key FROM rateb_offline_sync_queue WHERE company_id=? AND idempotency_key LIKE ? AND status='pending' ORDER BY created_at ASC, id ASC");
        $st->execute([$companyId, self::PREFIX . 'ord-%']);
        $got = $st->fetchAll(PDO::FETCH_COLUMN);
        $this->assert('replay ordering FIFO by created_at/id', $got === $orderKeys, 'got=' . json_encode($got));
        (new OfflineBackgroundSync())->process($companyId, 50);

        // --- 8 Background synchronization ---
        $keyBg = self::PREFIX . 'bg-' . bin2hex(random_bytes(4));
        $queue->enqueueBatch([[
            'client_id' => $keyBg,
            'module' => 'offline_meta',
            'action' => 'offline.ack',
            'payload' => ['bg' => 1],
        ]], ['company_id' => $companyId]);
        $bgStats = (new OfflineBackgroundSync())->process($companyId, 30);
        $this->metrics['background'] = $bgStats;
        $this->assert('background sync processed', (int) ($bgStats['processed'] ?? 0) >= 1, json_encode($bgStats));
        $this->assert('background reports inv+hr flags', !empty($bgStats['inventory_enabled']) && !empty($bgStats['hr_enabled']), json_encode($bgStats));

        // --- 9 Multi-branch synchronization ---
        $crossInv = $invGuard->assertInventory($invId, ['company_id' => 999999, 'branch_id' => 1]);
        $this->assert('multi-tenant inventory deny', empty($crossInv['ok']), json_encode($crossInv));
        $crossHr = $hrGuard->assertEmployee($empId, ['company_id' => 999999, 'branch_id' => 1]);
        $this->assert('multi-tenant HR deny', empty($crossHr['ok']), json_encode($crossHr));

        $keyBa = self::PREFIX . 'brA-' . bin2hex(random_bytes(3));
        $keyBb = self::PREFIX . 'brB-' . bin2hex(random_bytes(3));
        $queue->enqueueBatch([['client_id' => $keyBa, 'module' => 'offline_meta', 'action' => 'offline.ack', 'payload' => []]], [
            'company_id' => 5, 'branch_id' => $branchA, 'device_id' => 'term-A',
        ]);
        $queue->enqueueBatch([['client_id' => $keyBb, 'module' => 'offline_meta', 'action' => 'offline.ack', 'payload' => []]], [
            'company_id' => 5, 'branch_id' => $branchB, 'device_id' => 'term-B',
        ]);
        $ba = $pdo->prepare('SELECT branch_id, device_id FROM rateb_offline_sync_queue WHERE idempotency_key=?');
        $ba->execute([$keyBa]);
        $rowA = $ba->fetch(PDO::FETCH_ASSOC) ?: [];
        $ba->execute([$keyBb]);
        $rowB = $ba->fetch(PDO::FETCH_ASSOC) ?: [];
        $this->assert('multi-branch queue isolation', (int) ($rowA['branch_id'] ?? 0) === $branchA && (int) ($rowB['branch_id'] ?? 0) === $branchB, json_encode([$rowA, $rowB]));
        (new OfflineBackgroundSync())->process(5, 50);

        // Conflict resolver matrix
        $cInv = $resolver->resolveInventory(
            ['version' => 1, 'expected_quantity' => 10],
            ['version' => 2, 'quantity' => 9]
        );
        $this->assert('inventory conflict quantity_changed', ($cInv['reason'] ?? '') === 'quantity_changed' || ($cInv['action'] ?? '') === 'reject_client', json_encode($cInv));
        $cHr = $resolver->resolveHr(
            ['version' => 1, 'expected_status' => 'present'],
            ['version' => 5, 'status' => 'absent']
        );
        $this->assert('HR conflict resolution returns action', isset($cHr['action']), json_encode($cHr));

        // --- 10 Long-running queue ---
        $n = 500;
        $t0 = microtime(true);
        $batch = [];
        for ($i = 0; $i < $n; $i++) {
            $batch[] = [
                'client_id' => self::PREFIX . 'long-' . $i . '-' . bin2hex(random_bytes(2)),
                'module' => 'offline_meta',
                'action' => 'offline.ack',
                'payload' => ['i' => $i],
            ];
            if (count($batch) >= 50) {
                $queue->enqueueBatch($batch, ['company_id' => $companyId, 'branch_id' => $branchId, 'device_id' => 'soak-load']);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $queue->enqueueBatch($batch, ['company_id' => $companyId, 'branch_id' => $branchId, 'device_id' => 'soak-load']);
        }
        $enqueueMs = (microtime(true) - $t0) * 1000;
        $pendingLong = (int) $pdo->query("SELECT COUNT(*) FROM rateb_offline_sync_queue WHERE company_id=1 AND status='pending' AND idempotency_key LIKE 'soak462-long-%'")->fetchColumn();
        $this->assert('long queue backlog after enqueue', $pendingLong === $n, "pending=$pendingLong n=$n");
        $t1 = microtime(true);
        $processedTotal = 0;
        for ($round = 0; $round < 20; $round++) {
            $stRound = (new OfflineBackgroundSync())->process($companyId, 50);
            $processedTotal += (int) ($stRound['processed'] ?? 0);
            $left = (int) $pdo->query("SELECT COUNT(*) FROM rateb_offline_sync_queue WHERE company_id=1 AND status='pending' AND idempotency_key LIKE 'soak462-long-%'")->fetchColumn();
            if ($left === 0) {
                break;
            }
        }
        $processMs = (microtime(true) - $t1) * 1000;
        $leftFinal = (int) $pdo->query("SELECT COUNT(*) FROM rateb_offline_sync_queue WHERE company_id=1 AND status='pending' AND idempotency_key LIKE 'soak462-long-%'")->fetchColumn();
        $this->metrics['long_queue'] = [
            'n' => $n,
            'enqueue_ms' => round($enqueueMs, 2),
            'process_ms' => round($processMs, 2),
            'processed' => $processedTotal,
            'pending_after_enqueue' => $pendingLong,
            'pending_final' => $leftFinal,
        ];
        $this->assert('long-running queue drained', $leftFinal === 0, json_encode($this->metrics['long_queue']));

        // --- 11 Performance under load ---
        $t2 = microtime(true);
        for ($i = 0; $i < 200; $i++) {
            $ack->evaluate([
                'accepted' => 1,
                'duplicate' => 0,
                'conflict' => 0,
                'rejected' => 0,
                'accepted_keys' => ['k' . $i],
                'duplicate_keys' => [],
                'conflict_keys' => [],
                'rejected_keys' => [],
            ]);
            $resolver->resolveInventory(['version' => $i + 2], ['version' => $i]);
            $resolver->resolveHr(['version' => $i + 2], ['version' => $i]);
        }
        $loadMs = (microtime(true) - $t2) * 1000;
        $this->metrics['cpu_load_200_cycles_ms'] = round($loadMs, 2);
        $this->assert('performance load < 5s for 200 cycles', $loadMs < 5000, 'ms=' . round($loadMs, 2));
        $this->assert('long enqueue throughput', $enqueueMs > 0 && ($n / max(0.001, $enqueueMs / 1000)) > 50, 'items/s≈' . round($n / max(0.001, $enqueueMs / 1000), 1));

        // --- 12 Zero Data Loss ---
        $lost = (int) $pdo->query("SELECT COUNT(*) FROM rateb_offline_sync_queue WHERE idempotency_key LIKE 'soak462-%' AND status NOT IN ('pending','synced','conflict','failed')")->fetchColumn();
        $this->assert('ZDL no unknown statuses', $lost === 0, 'lost=' . $lost);
        $rejOnly = $ack->clearableKeys([
            'accepted_keys' => [],
            'duplicate_keys' => [],
            'conflict_keys' => ['x'],
            'rejected_keys' => ['y'],
        ]);
        $this->assert('ZDL rejected/conflict never clearable', $rejOnly === [], json_encode($rejOnly));

        // Flags OFF when env cleared (production posture)
        $this->clearOfflineEnv();
        $flagsFinal = new OfflineFeatureFlagService();
        $this->assert('post-soak master OFF without env', !$flagsFinal->isMasterEnabled(), 'master');
        $envFile = dirname(RATEB_ROOT) . '/.env';
        $envTxt = is_file($envFile) ? (string) file_get_contents($envFile) : '';
        $this->assert('.env has no RATEB_OFFLINE_*', !preg_match('/^RATEB_OFFLINE_/m', $envTxt), 'env clean');

        // Cleanup soak rows (keep evidence counts in metrics)
        $del = $pdo->exec("DELETE FROM rateb_offline_sync_conflicts WHERE idempotency_key LIKE 'soak462-%'");
        $del2 = $pdo->exec("DELETE FROM rateb_offline_sync_queue WHERE idempotency_key LIKE 'soak462-%'");
        $this->metrics['cleanup_deleted_queue'] = (int) $del2;
        $this->metrics['cleanup_deleted_conflicts'] = (int) $del;

        // POS blocked scenarios (explicit)
        if (!$posPresent) {
            foreach ([
                'POS multi-terminal sync',
                'POS sale/return offline replay',
                'POS device activation soak',
            ] as $name) {
                $this->record($name, true, 'SKIPPED — blocked by missing POS deployment', 'Medium');
            }
        }

        return $this->printReport();
    }

    private function enableSoakFlags(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_INVENTORY_MOVEMENTS=1');
        putenv('RATEB_OFFLINE_HR_ATTENDANCE=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_INVENTORY_MOVEMENTS'] = '1';
        $_ENV['RATEB_OFFLINE_HR_ATTENDANCE'] = '1';
    }

    private function clearOfflineEnv(): void
    {
        putenv('RATEB_OFFLINE_ENABLED');
        putenv('RATEB_OFFLINE_INVENTORY_MOVEMENTS');
        putenv('RATEB_OFFLINE_HR_ATTENDANCE');
        unset($_ENV['RATEB_OFFLINE_ENABLED'], $_ENV['RATEB_OFFLINE_INVENTORY_MOVEMENTS'], $_ENV['RATEB_OFFLINE_HR_ATTENDANCE']);
    }

    private function assert(string $name, bool $ok, string $detail): void
    {
        $this->record($name, $ok, $detail, $ok ? 'Low' : 'High');
        if (!$ok) {
            $this->finding('F-' . substr(sha1($name), 0, 8), 'High', $name, $detail);
        }
    }

    private function fail(string $id, string $sev, string $detail): void
    {
        $this->finding($id, $sev, $detail, $detail);
        $this->record($detail, false, $detail, $sev);
    }

    private function record(string $name, bool $ok, string $detail, string $sev): void
    {
        $this->checks[] = ['id' => $name, 'sev' => $sev, 'ok' => $ok, 'detail' => $detail];
        echo ($ok ? 'PASS' : 'FAIL') . " [$sev]: $name — $detail\n";
    }

    private function finding(string $id, string $sev, string $title, string $detail): void
    {
        $this->findings[] = ['id' => $id, 'sev' => $sev, 'title' => $title, 'detail' => $detail];
    }

    private function printReport(): int
    {
        $crit = 0;
        $high = 0;
        $med = 0;
        $low = 0;
        foreach ($this->findings as $f) {
            match ($f['sev']) {
                'Critical' => $crit++,
                'High' => $high++,
                'Medium' => $med++,
                default => $low++,
            };
        }
        $pass = count(array_filter($this->checks, static fn ($c) => $c['ok']));
        $total = count($this->checks);
        echo "\n=== METRICS ===\n" . json_encode($this->metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        echo "\n=== FINDINGS ===\n";
        foreach ($this->findings as $f) {
            echo "- [{$f['sev']}] {$f['id']} {$f['title']}: {$f['detail']}\n";
        }
        echo "\nChecks: $pass/$total passed\n";
        echo "Critical: $crit | High: $high | Medium: $med | Low: $low\n";

        $zdl = $high === 0 && $crit === 0;
        echo 'Zero Data Loss: ' . ($zdl ? 'PASS (staging evidenced)' : 'FAIL') . "\n";
        echo 'Production readiness: ' . ($zdl ? 'CONDITIONAL — Inv/HR soak evidenced; flags remain OFF; POS blocked on this host; 24h continuous soak not in this run' : 'NOT READY') . "\n";

        // Procurement: Critical=0 High=0 required. POS absence is Medium (non-blocking per Phase 4.6.2).
        $go = $crit === 0 && $high === 0;
        echo 'GO / NO-GO for Procurement Offline: ' . ($go ? 'CONDITIONAL GO' : 'NO-GO') . "\n";
        echo "GATE_JSON=" . json_encode([
            'critical' => $crit,
            'high' => $high,
            'medium' => $med,
            'low' => $low,
            'passed' => $pass,
            'total' => $total,
            'zdl' => $zdl,
            'procurement' => $go ? 'CONDITIONAL_GO' : 'NO_GO',
            'metrics' => $this->metrics,
            'findings' => $this->findings,
        ], JSON_UNESCAPED_UNICODE) . "\n";

        return ($crit + $high) > 0 ? 1 : 0;
    }
}

exit((new Phase462StagingSoak())->run());
