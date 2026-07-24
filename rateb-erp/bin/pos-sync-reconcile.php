<?php
declare(strict_types=1);

/**
 * CLI / cron-safe POS sync acceptance reconcile (Phase 14.1).
 *
 * Usage:
 *   php bin/pos-sync-reconcile.php              # all active companies
 *   php bin/pos-sync-reconcile.php <companyId> # one company
 *   php bin/pos-sync-reconcile.php <companyId> <ttlSeconds>
 *
 * Never creates orders. Matches sync_key → rateb_pos_orders.idempotency_key.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

define('RATEB_ROOT', dirname(__DIR__));
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

if (is_file(RATEB_ROOT . '/modules/pos/PosModule.php')) {
    require_once RATEB_ROOT . '/modules/pos/PosModule.php';
    \Rateb\App\Pos\PosModule::init();
}

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Services\PosSyncAcceptanceReconcileService;

$companyArg = isset($argv[1]) ? (int) $argv[1] : 0;
$ttl = isset($argv[2]) ? (int) $argv[2] : PosSyncAcceptanceReconcileService::DEFAULT_TTL_SECONDS;
$svc = new PosSyncAcceptanceReconcileService();

$companyIds = [];
if ($companyArg > 0) {
    $companyIds[] = $companyArg;
} else {
    $rows = Database::connection()->query(
        "SELECT id FROM rateb_companies WHERE status = 'active' ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            $companyIds[] = $id;
        }
    }
}

$totals = ['ok' => true, 'companies' => 0, 'reconciled' => 0, 'interrupted' => 0, 'scanned' => 0];
foreach ($companyIds as $cid) {
    TenantContext::setCompanyId($cid);
    $out = $svc->reconcileCompany($cid, $ttl);
    $totals['companies']++;
    $totals['reconciled'] += (int) ($out['reconciled'] ?? 0);
    $totals['interrupted'] += (int) ($out['interrupted'] ?? 0);
    $totals['scanned'] += (int) ($out['scanned'] ?? 0);
    echo 'company=' . $cid
        . ' reconciled=' . (int) ($out['reconciled'] ?? 0)
        . ' interrupted=' . (int) ($out['interrupted'] ?? 0)
        . ' scanned=' . (int) ($out['scanned'] ?? 0)
        . PHP_EOL;
}
TenantContext::setCompanyId(null);

echo 'TOTAL companies=' . $totals['companies']
    . ' reconciled=' . $totals['reconciled']
    . ' interrupted=' . $totals['interrupted']
    . ' scanned=' . $totals['scanned']
    . PHP_EOL;

exit(0);
