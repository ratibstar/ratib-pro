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
require_once $root . '/app/Core/SessionManager.php';
require_once $root . '/app/Core/Csrf.php';
require_once $root . '/app/Core/Response.php';
require_once $root . '/app/services/Logger.php';
require_once $root . '/app/Core/View.php';
require_once $root . '/app/Core/Controller.php';
require_once $root . '/modules/automotive/app/Controllers/AutomotiveBaseController.php';
require_once $root . '/modules/automotive/app/Controllers/AutomotiveOpenCatalogController.php';

use Rateb\App\Automotive\Controllers\AutomotiveOpenCatalogController;
use Rateb\App\Automotive\Services\AutomotiveOpenCatalogService;
use Rateb\App\Automotive\Services\AutomotiveSelectionService;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Core\View;

$p4Rtl = false;
$p4Lang = 'en';
if (!function_exists('__')) {
    function __(string $key, array $replace = []): string
    {
        global $p4Lang;
        $file = dirname(__DIR__) . '/config/lang/' . ($p4Lang === 'ar' ? 'ar' : 'en') . '.php';
        $map = require $file;
        return (string) ($map[$key] ?? $key);
    }
}
if (!function_exists('rateb_is_rtl')) {
    function rateb_is_rtl(): bool
    {
        global $p4Rtl;
        return $p4Rtl;
    }
}
if (!function_exists('rateb_url')) {
    function rateb_url(string $path = ''): string
    {
        return '/admin/' . ltrim($path, '/');
    }
}
if (!function_exists('rateb_app_route')) {
    function rateb_app_route(string $path): string
    {
        return $path;
    }
}

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
if (in_array((string) getenv('P4_CHILD'), ['csrf', 'missing', 'duplicate'], true)) {
    p4_child($pdo, (string) getenv('P4_CHILD'));
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
$stillThere = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_company_selections WHERE id = ' . $selectionId . ' AND company_id = 70')->fetchColumn();
$pdo->rollBack();
p4($selectionId > 0 && $hashBefore === $hashAfter, 'company selection references the global row and does not change it');
p4($isolated, 'another company cannot remove the selection');
p4($stillThere === 1, 'the first company selection stays intact after the other company is rejected');
$left = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_company_selections WHERE id = ' . $selectionId)->fetchColumn();
p4($left === 0, 'the test selection was rolled back');

$huge = $service->models('', '', '', '', PHP_INT_MAX);
$negative = $service->models('', '', '', '', -4);
$invalid = $service->models('', '', '', '', 0);
p4($huge['page'] === (int) ceil(11444 / 25) && count($huge['rows']) === (11444 % 25), 'an excessive page is clamped to the last page');
p4($negative['page'] === 1 && count($negative['rows']) === 25, 'a negative page is normalized to the first page');
p4($invalid['page'] === 1 && count($invalid['rows']) === 25, 'an invalid page is normalized to the first page');

$html = p4_detail_html($detail, false);
p4(str_contains($html, (string) $detail['sources'][0]['origin_code']) && str_contains($html, (string) $detail['licenses'][0]), 'rendered detail HTML contains the source origin and license');
$hostile = $detail;
$hostile['sources'] = [['origin_code' => '<img src=x onerror=alert(1)>', 'license_code' => 'CC-BY-4.0']];
$escaped = p4_detail_html($hostile, false);
p4(str_contains($escaped, '&lt;img src=x onerror=alert(1)&gt;') && !str_contains($escaped, '<img src=x'), 'source values are escaped in the rendered HTML');
$p4Rtl = true;
$p4Lang = 'ar';
$arabic = p4_detail_html($detail, false);
p4(str_contains($arabic, 'dir="rtl"') && str_contains($arabic, 'المصادر') && str_contains($arabic, (string) $detail['sources'][0]['origin_code']), 'the Arabic detail page renders right to left');
$p4Rtl = false;
$p4Lang = 'en';

$selectionsBeforeBehavior = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_company_selections')->fetchColumn();
p4_behavior($pdo, (int) $sample['id'], $selectionsBeforeBehavior);

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

function p4_detail_html(array $detail, bool $canSelect): string
{
    $can_select = $canSelect;
    ob_start();
    include dirname(__DIR__) . '/views/open-catalog/show.php';
    return (string) ob_get_clean();
}

function p4_child(PDO $pdo, string $case): void
{
    global $allow;
    $allow = true;
    $modelId = (int) getenv('P4_MODEL_ID');
    register_shutdown_function(static function (): void {
        $message = (string) ($_SESSION['_flash']['error'] ?? '');
        echo 'FLASH:' . $message;
    });
    SessionManager::start();
    $token = bin2hex(random_bytes(16));
    $_SESSION['_csrf_token'] = $token;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['_csrf'] = $case === 'csrf' ? 'invalid-token-value' : $token;
    $_POST['model_id'] = $case === 'missing' ? '2147483646' : (string) $modelId;
    TenantContext::setCompanyId(70);
    if ($case === 'duplicate') {
        (new AutomotiveSelectionService($pdo))->select('open_model', $modelId);
    }
    (new AutomotiveOpenCatalogController(new AutomotiveSelectionService($pdo)))->select();
}

function p4_behavior(PDO $pdo, int $modelId, int $before): void
{
    $env = [];
    foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $env[$key] = $value;
        }
    }
    $env['RATEB_EPC_DISPOSABLE_DB'] = 'admin_rateb_epc_phase0';
    $env['RATEB_EPC_DSN'] = (string) getenv('RATEB_EPC_DSN');
    $env['RATEB_EPC_USER'] = (string) getenv('RATEB_EPC_USER');
    $env['RATEB_EPC_PASS'] = (string) getenv('RATEB_EPC_PASS');
    $env['P4_MODEL_ID'] = (string) $modelId;
    foreach (['csrf', 'missing', 'duplicate'] as $case) {
        $env['P4_CHILD'] = $case;
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__);
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $count = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_company_selections')->fetchColumn();
        if ($case === 'csrf') {
            p4($count === $before && str_contains($out, 'FLASH:Invalid CSRF token') && !str_contains($out, 'SQLSTATE'), 'invalid CSRF token rejects selection and inserts nothing');
        } elseif ($case === 'missing') {
            p4($count === $before && str_contains($out, 'FLASH:The vehicle could not be selected.') && !str_contains($out . $err, 'SQLSTATE'), 'a missing open model is rejected without a database error');
        } else {
            $owned = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_company_selections WHERE company_id = 70 AND entity_type = \'open_model\' AND entity_id = ' . $modelId)->fetchColumn();
            p4($owned === 1 && str_contains($out, 'FLASH:This vehicle is already selected for the company.') && !str_contains($out . $err, 'SQLSTATE'), 'a duplicate selection stays consistent and hides the database error');
            $pdo->prepare('DELETE FROM rateb_auto_company_selections WHERE company_id = 70 AND entity_type = \'open_model\' AND entity_id = :id')->execute(['id' => $modelId]);
            $after = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_company_selections')->fetchColumn();
            p4($after === $before, 'the duplicate-selection probe was removed');
        }
    }
}
