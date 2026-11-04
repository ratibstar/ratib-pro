<?php
declare(strict_types=1);

/**
 * Phase 4 catalog checks on admin_rateb_epc_phase0. Company writes roll back.
 * Run: php rateb-erp/modules/automotive/tests/run-automotive-epc-phase4-catalog-tests.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/modules/automotive/app/Services/AutomotiveGate.php';
require_once $root . '/modules/automotive/app/Services/AutomotiveOpenCatalogService.php';
require_once $root . '/modules/automotive/app/Services/AutomotiveSelectionService.php';
require_once $root . '/app/Core/TenantContext.php';

use Rateb\App\Automotive\Services\AutomotiveOpenCatalogService;
use Rateb\App\Automotive\Services\AutomotiveSelectionService;
use Rateb\App\Core\TenantContext;

$allow = true;
if (!function_exists('rateb_can')) {
    function rateb_can(string $slug): bool
    {
        global $allow;
        return $allow;
    }
}

if (getenv('RATEB_EPC_DISPOSABLE_DB') !== 'admin_rateb_epc_phase0' || !str_contains((string) getenv('RATEB_EPC_DSN'), 'dbname=admin_rateb_epc_phase0')) {
    echo "NOT RUN: database tests require admin_rateb_epc_phase0\n";
    exit(2);
}
$pdo = new PDO((string) getenv('RATEB_EPC_DSN'), (string) getenv('RATEB_EPC_USER'), (string) getenv('RATEB_EPC_PASS'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'admin_rateb_epc_phase0') {
    echo "FAIL: refused database\n";
    exit(1);
}

$passed = 0;
$failed = 0;
function p4(bool $ok, string $label): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

$en = (string) file_get_contents(dirname(__DIR__) . '/config/lang/en.php');
$ar = (string) file_get_contents(dirname(__DIR__) . '/config/lang/ar.php');
p4(str_contains($en, "'auto_open_catalog' => 'Global vehicle catalog'") && str_contains($ar, "'auto_open_catalog' => 'الكتالوج العالمي للمركبات'"), 'English and Arabic catalog labels stay in their own files');
$dir = dirname(__DIR__) . '/views/open-catalog';
$rtl = is_file($dir . '/show.php') && str_contains((string) file_get_contents($dir . '/show.php'), '__(');
p4($rtl, 'catalog views use locale labels');
$controller = (string) file_get_contents(dirname(__DIR__) . '/app/Controllers/AutomotiveOpenCatalogController.php');
p4(str_contains($controller, 'validateCsrf()') && str_contains($controller, 'automotive.selection.manage'), 'selection route checks permission and CSRF');

$inventoryBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn();
$supplierBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_suppliers')->fetchColumn();
$modelCountBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_open_models')->fetchColumn();

$allow = false;
$denied = false;
try {
    (new AutomotiveOpenCatalogService($pdo))->release();
} catch (RuntimeException) {
    $denied = true;
}
p4($denied, 'catalog read is denied without permission');
$allow = true;

$service = new AutomotiveOpenCatalogService($pdo);
$release = $service->release();
p4((int) $release['id'] === 4 && $release['version_label'] === '2026.10.4', 'release 4 version 2026.10.4 is the catalog release');
$kinds = $service->kinds();
$sum = 0;
$names = [];
foreach ($kinds as $kind) {
    $names[] = $kind['kind'];
    $sum += (int) $kind['models'];
}
sort($names);
p4($names === ['bus', 'car', 'moped', 'motorcycle', 'truck', 'van'] && $sum === 11444, 'six vehicle types total 11444 models');

$sample = $pdo->query(
    "SELECT m.id, m.kind, m.make_slug, m.model_slug, m.name_en, m.name_ar, m.record_sha256, k.market_code
     FROM rateb_auto_ref_open_models m
     JOIN rateb_auto_ref_open_model_markets k ON k.model_id = m.id
     WHERE m.release_id = 4 LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
$byMake = $service->models((string) $sample['kind'], (string) $sample['make_slug'], '', (string) $sample['make_slug'], 1);
$byModel = $service->models('', '', '', (string) $sample['name_en'], 1);
$bySlug = $service->models('', '', '', (string) $sample['model_slug'], 1);
$byMarket = $service->models((string) $sample['kind'], (string) $sample['make_slug'], (string) $sample['market_code'], '', 1);
$blocked = $service->models('', '', 'il', '', 1);
p4($byMake['total'] > 0 && $byModel['total'] > 0, 'search finds the make and model name');
p4($bySlug['total'] > 0, 'search finds the original model slug');
p4($byMarket['total'] > 0 && $blocked['total'] === 0, 'market evidence filter works and il is absent');

$detail = $service->detail((int) $sample['id']);
p4($detail['sources'] !== [] && $detail['licenses'] !== [] && str_contains((string) $detail['release']['attribution'], 'VehiclesDB'), 'detail shows sources, licenses, and attribution');
p4($sample['name_ar'] === null, 'imported model has no fabricated Arabic name');

$page1 = $service->models('', '', '', '', 1);
$page2 = $service->models('', '', '', '', 2);
$ids1 = array_column($page1['rows'], 'id');
$ids2 = array_column($page2['rows'], 'id');
p4(count($page1['rows']) === 25 && count(array_unique($ids1)) === 25 && array_intersect($ids1, $ids2) === [], 'pages are bounded and do not repeat rows');

$hashBefore = (string) $sample['record_sha256'];
TenantContext::setCompanyId(70);
$selection = new AutomotiveSelectionService($pdo);
$pdo->beginTransaction();
$selectionId = $selection->select('open_model', (int) $sample['id']);
$hashAfter = (string) $pdo->query('SELECT record_sha256 FROM rateb_auto_ref_open_models WHERE id = ' . (int) $sample['id'])->fetchColumn();
TenantContext::setCompanyId(71);
$isolated = false;
try {
    $selection->remove($selectionId);
} catch (RuntimeException) {
    $isolated = true;
}
$pdo->rollBack();
p4($selectionId > 0 && $hashBefore === $hashAfter, 'company selection references the global row and does not change it');
p4($isolated, 'another company cannot remove the selection');
$left = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_company_selections WHERE id = ' . $selectionId)->fetchColumn();
p4($left === 0, 'the test selection was rolled back');

$inventoryAfter = (int) $pdo->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn();
$supplierAfter = (int) $pdo->query('SELECT COUNT(*) FROM rateb_suppliers')->fetchColumn();
$modelCountAfter = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_open_models')->fetchColumn();
p4($inventoryBefore === $inventoryAfter && $supplierBefore === $supplierAfter && $modelCountBefore === 11444 && $modelCountAfter === 11444, 'inventory, suppliers, and the 11444 models stay unchanged');

$index = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME IN ('idx_auto_ref_open_model_browse','idx_auto_ref_open_market_code')"
)->fetchColumn();
p4($index >= 2, 'browse and market indexes exist');

echo "Phase 4 catalog checks: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
