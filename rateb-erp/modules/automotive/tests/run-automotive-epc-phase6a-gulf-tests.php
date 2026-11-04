<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/modules/automotive/app/Services/AutomotiveGulfFoundationService.php';

use Rateb\App\Automotive\Services\AutomotiveGulfFoundationService;

if (getenv('RATEB_EPC_DISPOSABLE_DB') !== 'admin_rateb_epc_phase0' || !str_contains((string) getenv('RATEB_EPC_DSN'), 'dbname=admin_rateb_epc_phase0')) {
    echo "NOT RUN: admin_rateb_epc_phase0 required\n";
    exit(2);
}
$pdo = new PDO((string) getenv('RATEB_EPC_DSN'), (string) getenv('RATEB_EPC_USER'), (string) getenv('RATEB_EPC_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'admin_rateb_epc_phase0') {
    echo "FAIL database\n";
    exit(1);
}

$passed = 0;
$failed = 0;
function p6(bool $ok, string $label): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

$sql = (string) file_get_contents($root . '/migrations/297_auto_ref_gulf_foundation.sql');
$sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    if ($statement !== '') {
        $pdo->exec($statement);
    }
}

$service = new AutomotiveGulfFoundationService($pdo);
$markets = $service->gulfMarkets();
$codes = array_column($markets, 'code');
sort($codes);
p6($codes === ['AE', 'BH', 'KW', 'OM', 'QA', 'SA'], 'six GCC markets exist');
$unique = (int) $pdo->query("SELECT COUNT(DISTINCT code) FROM rateb_auto_ref_markets WHERE code IN ('SA','AE','KW','QA','BH','OM')")->fetchColumn();
p6($unique === 6, 'GCC market codes are unique');
$company = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_auto_ref_data_providers' AND COLUMN_NAME = 'company_id'")->fetchColumn();
p6($company === 0, 'provider registry has no company_id');

$inventory = (int) $pdo->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn();
$suppliers = (int) $pdo->query('SELECT COUNT(*) FROM rateb_suppliers')->fetchColumn();
$selections = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_company_selections')->fetchColumn();
$open = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_open_models')->fetchColumn();

$bad = false;
try {
    $service->saveProvider(['code' => 'x', 'provider_type' => 'data_provider', 'name_en' => 'A', 'name_ar' => 'أ', 'source_note' => 'note'], ['SA'], ['vehicle_identity']);
} catch (InvalidArgumentException) {
    $bad = true;
}
p6($bad, 'invalid provider code is rejected');

$provider = $service->saveProvider([
    'code' => 'P6A' . strtoupper(substr(uniqid(), -6)),
    'provider_type' => 'data_provider',
    'name_en' => 'Phase 6A fixture <script>',
    'name_ar' => 'اختبار المرحلة',
    'website' => 'https://example.test/provider',
    'status' => 'pending',
    'source_note' => 'Internal test fixture, not a licensed catalog',
    'review_note' => 'No commercial contract',
], ['SA'], ['vehicle_identity']);
p6($provider > 0, 'provider is stored with a source note');

$unknown = $service->saveRights($provider, 'No grant stated', [
    'store_local' => 'unknown',
    'commercial_display' => 'unknown',
    'access_mode' => 'api',
], '', null, null);
$reportsBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_gulf_import_reports')->fetchColumn();
$blocked = false;
try {
    $service->dryRun($unknown, 'SA', "identity,label\nFIX-1,Sample\n", '2026-11-04');
} catch (RuntimeException $e) {
    $blocked = $e->getMessage() === 'rights_blocked';
}
p6($blocked, 'unknown commercial rights block dry-run');
p6((int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_gulf_import_reports')->fetchColumn() === $reportsBefore, 'blocked dry-run writes nothing');

$expired = $service->saveRights($provider, 'Expired grant', [
    'store_local' => 'yes',
    'process_internal' => 'yes',
    'commercial_display' => 'yes',
    'saas_multi' => 'yes',
    'access_mode' => 'bulk',
], 'SA', '2026-01-01', null);
p6(!$service->commercialImportAllowed($expired, 'SA', '2026-11-04'), 'expired rights are refused');
$apiOnly = $service->saveRights($provider, 'API only', [
    'store_local' => 'yes',
    'process_internal' => 'yes',
    'commercial_display' => 'yes',
    'saas_multi' => 'yes',
    'access_mode' => 'api',
], 'SA', null, null);
p6(!$service->commercialImportAllowed($apiOnly, 'SA', '2026-11-04'), 'API access is not treated as bulk redistribution');

$allowed = $service->saveRights($provider, 'Fixture grant for SA display only', [
    'store_local' => 'yes',
    'process_internal' => 'yes',
    'commercial_display' => 'yes',
    'saas_multi' => 'yes',
    'redistribute' => 'unknown',
    'access_mode' => 'bulk',
], 'SA', null, null);
$payload = "identity,label\nFIX-1,Sample\nFIX-1,Duplicate\nbad, \nFIX-2,Second\n";
$dry = $service->dryRun($allowed, 'SA', $payload, '2026-11-04');
p6($dry['wrote'] === false && $dry['inserted'] === 2 && $dry['skipped'] === 1 && $dry['rejected'] === 1, 'dry-run counts inserts, duplicates, and rejects');
p6($dry['sha256'] === hash('sha256', $payload), 'SHA-256 matches the payload');
p6((int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_gulf_import_rows')->fetchColumn() === 0, 'dry-run inserts no fixture rows');

$applied = $service->importFixture($allowed, 'SA', $payload, '2026-11-04');
$again = $service->importFixture($allowed, 'SA', $payload, '2026-11-04');
p6($applied['wrote'] === true && $again['wrote'] === false && $again['inserted'] === 0, 'the same fingerprint is idempotent');
p6((int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_gulf_import_rows')->fetchColumn() === 2, 'duplicate identities are stored once');

$claim = $service->saveCoverage([
    'market_code' => 'SA',
    'provider_id' => $provider,
    'data_category' => 'oem_parts',
    'verification' => 'unavailable',
    'license_status' => 'unknown',
]);
$stored = $service->coverage();
$code = (string) $pdo->query('SELECT code FROM rateb_auto_ref_data_providers WHERE id = ' . $provider)->fetchColumn();
$match = array_values(array_filter($stored, static fn ($row) => $row['provider_code'] === $code && $row['verification'] === 'unavailable'));
p6($claim > 0 && $match !== [] && $match[0]['market_code'] === 'SA', 'coverage stays unavailable until a source verifies it');

$routes = (string) file_get_contents(dirname(__DIR__) . '/routes/automotive.php');
$controller = (string) file_get_contents(dirname(__DIR__) . '/app/Controllers/AutomotiveGulfFoundationController.php');
p6(substr_count($routes, "post(\$autoApp('gulf/") === 4 && str_contains($controller, 'validateCsrf()'), 'state changes are POST routes with CSRF');
$en = require dirname(__DIR__) . '/config/lang/en.php';
$ar = require dirname(__DIR__) . '/config/lang/ar.php';
p6(($en['auto_gulf_title'] ?? '') !== ($ar['auto_gulf_title'] ?? '') && !str_contains((string) $ar['auto_gulf_scope'], 'Saudi'), 'Arabic labels are not English copies');
$view = (string) file_get_contents(dirname(__DIR__) . '/views/gulf/index.php');
p6(str_contains($view, 'View::escape') && str_contains($view, "dir="), 'provider text is escaped and direction is set');

p6((int) $pdo->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn() === $inventory, 'inventory count is unchanged');
p6((int) $pdo->query('SELECT COUNT(*) FROM rateb_suppliers')->fetchColumn() === $suppliers, 'supplier count is unchanged');
p6((int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_company_selections')->fetchColumn() === $selections, 'company selections are unchanged');
p6((int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_open_models')->fetchColumn() === $open, 'open catalog count is unchanged');

echo "passed=$passed failed=$failed\n";
exit($failed > 0 ? 1 : 0);
