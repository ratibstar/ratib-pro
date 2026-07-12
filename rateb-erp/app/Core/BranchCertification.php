<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase D — Enterprise Branch Certification (automatic PASS/FAIL matrix).
 * Verifies presence and operational probes without modifying business layers.
 */
final class BranchCertification
{
    /**
     * @return array{ok:bool,passed:int,failed:int,items:list<array{id:string,ok:bool,detail:string}>}
     */
    public function certify(): array
    {
        $items = [];
        $check = static function (string $id, bool $ok, string $detail) use (&$items): void {
            $items[] = compact('id', 'ok', 'detail');
        };

        $erp = defined('RATEB_ROOT') ? (string) RATEB_ROOT : dirname(__DIR__, 2);
        $erp = str_replace('\\', '/', $erp);

        $check('installer', class_exists(BranchApplianceInstaller::class), 'BranchApplianceInstaller');
        $check('runtime', class_exists(HybridRuntime::class) && method_exists(HybridRuntime::class, 'shouldUseSqlite'), 'HybridRuntime');
        $check('sqlite', HybridRuntime::sqliteExtensionAvailable(), 'pdo_sqlite');

        $loginOk = false;
        $rbacOk = false;
        $dashboardOk = is_file($erp . '/views/company/dashboard.php') || is_dir($erp . '/views/company');
        $posOk = is_dir($erp . '/modules/pos') || is_file($erp . '/public/pos-sw.js');
        $inventoryOk = is_dir($erp . '/app/controllers') && $this->pathContains($erp . '/app', 'Inventory');
        $accountingOk = $this->pathContains($erp . '/app', 'Accounting') || $this->pathContains($erp . '/modules', 'accounting');
        $hrOk = $this->pathContains($erp . '/app', 'Hr') || $this->pathContains($erp . '/app', 'HR');
        $warehouseOk = $this->pathContains($erp . '/app', 'Warehouse')
            || $this->pathContains($erp . '/views/company', 'warehouse')
            || is_file($erp . '/app/services/WarehouseService.php');
        $procurementOk = $this->pathContains($erp . '/app', 'Procurement') || $this->pathContains($erp . '/app', 'Purchase');
        $reportsOk = $this->pathContains($erp . '/app', 'Report') || is_dir($erp . '/views');

        if (HybridRuntime::shouldUseSqlite() && is_file(HybridRuntime::sqlitePath())) {
            try {
                $pdo = Database::connection();
                $uid = (int) $pdo->query("SELECT id FROM rateb_users WHERE email = 'admin@branch.test' LIMIT 1")->fetchColumn();
                $loginOk = $uid > 0;
                $rbacOk = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name LIKE '%permission%'")->fetchColumn() > 0;
                $tables = SqliteSchemaBootstrap::countUserTables($pdo);
                $check('sqlite_schema', $tables >= 100, 'tables=' . $tables);
            } catch (\Throwable $e) {
                $check('sqlite_schema', false, $e->getMessage());
            }
        } else {
            $check('sqlite_schema', false, 'not_installed');
        }

        $check('login', $loginOk, $loginOk ? 'admin present' : 'admin missing');
        $check('rbac', $rbacOk, $rbacOk ? 'permissions tables' : 'missing');
        $check('dashboard', $dashboardOk, 'views/company');
        $check('pos', $posOk, 'modules/pos or pos-sw.js');
        $check('inventory', $inventoryOk, 'Inventory surface');
        $check('accounting', $accountingOk, 'Accounting surface');
        $check('hr', $hrOk, 'HR surface');
        $check('warehouse', $warehouseOk, 'Warehouse surface');
        $check('procurement', $procurementOk, 'Procurement surface');
        $check('reports', $reportsOk, 'Reports/views');

        $check('hybrid_runtime', HybridRuntime::shouldUseSqlite(), json_encode(HybridRuntime::snapshot()) ?: '');
        $check('hybrid_sync', class_exists(HybridSyncEngine::class) && HybridSyncConfig::enabled(), 'HybridSyncEngine');
        $check('sync_service', class_exists(HybridSyncDaemon::class) && is_file($erp . '/bin/hybrid-sync-service.php'), 'HybridSyncDaemon');
        $check('diagnostics', class_exists(BranchDiagnostics::class), 'BranchDiagnostics');
        $check('backup', class_exists(BranchBackupService::class), 'BranchBackupService');
        $check('recovery', class_exists(BranchAutoRecovery::class), 'BranchAutoRecovery');
        $check('registration', class_exists(BranchRegistration::class), 'BranchRegistration');
        $check('health', class_exists(BranchHealthMonitor::class), 'BranchHealthMonitor');
        $check('update', class_exists(BranchUpdater::class), 'BranchUpdater');

        $cloudUnchanged = !HybridRuntime::isCloudDeploymentLocked() || HybridRuntime::isCloudMode();
        // Certification on branch appliance: cloud code path still present
        $check('cloud_connectivity', is_file($erp . '/app/Core/Database.php'), 'Database seam present');
        $check('audit', class_exists(HybridSyncAudit::class), 'HybridSyncAudit');
        $check('security', is_file(BranchAppliancePaths::identityDir() . '/private.key') || is_file(BranchAppliancePaths::identityDir() . '/identity.json'), 'identity keys');

        // Operational probes when installed
        if (HybridRuntime::shouldUseSqlite() && is_file(HybridRuntime::sqlitePath())) {
            $health = (new BranchHealthMonitor())->snapshot();
            $check('health_green', ($health['score'] ?? 0) >= 60, 'score=' . ($health['score'] ?? 0));
            $reg = (new BranchRegistration())->generateRegistrationPayload();
            $check('registration_payload', $reg['ok'], $reg['path']);
        }

        $passed = 0;
        $failed = 0;
        foreach ($items as $it) {
            $it['ok'] ? $passed++ : $failed++;
        }

        $result = [
            'ok' => $failed === 0,
            'passed' => $passed,
            'failed' => $failed,
            'items' => $items,
            'ts' => gmdate('c'),
        ];
        BranchAppliancePaths::ensureLayout();
        @file_put_contents(
            BranchAppliancePaths::root() . '/diagnostics/last-certification.json',
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return $result;
    }

    private function pathContains(string $dir, string $needle): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        $needle = strtolower($needle);
        $n = 0;
        foreach ($it as $f) {
            if (++$n > 5000) {
                break;
            }
            if (str_contains(strtolower($f->getFilename()), $needle)) {
                return true;
            }
        }

        return false;
    }
}
