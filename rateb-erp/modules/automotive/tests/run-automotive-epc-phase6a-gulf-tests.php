<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/modules/automotive/app/Services/AutomotiveGulfFoundationService.php';
require_once $root . '/modules/automotive/app/Services/AutomotiveUsCatalogService.php';

use Rateb\App\Automotive\Services\AutomotiveGulfFoundationService;
use Rateb\App\Automotive\Services\AutomotiveUsCatalogService;

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
$sql298 = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($root . '/migrations/298_auto_ref_rights_conflicts.sql')) ?? '';
foreach (array_filter(array_map('trim', explode(';', $sql298))) as $statement) {
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
    'status' => 'active',
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

$expiredProvider = $service->saveProvider(['code' => 'P6E' . strtoupper(substr(uniqid(), -6)), 'provider_type' => 'data_provider', 'name_en' => 'Expired', 'name_ar' => 'منته', 'status' => 'active', 'source_note' => 'expiry fixture'], ['SA'], ['vehicle_identity']);
$expired = $service->saveRights($expiredProvider, 'Expired grant', [
    'store_local' => 'yes', 'process_internal' => 'yes', 'commercial_display' => 'yes', 'saas_multi' => 'yes', 'access_mode' => 'bulk',
], 'SA', '2026-01-01', null, '2025-01-01');
p6(!$service->commercialImportAllowed($expired, 'SA', '2026-11-04'), 'expired rights are refused');
$apiProvider = $service->saveProvider(['code' => 'P6I' . strtoupper(substr(uniqid(), -6)), 'provider_type' => 'data_provider', 'name_en' => 'API', 'name_ar' => 'واجهة', 'status' => 'active', 'source_note' => 'api fixture'], ['SA'], ['vehicle_identity']);
$apiOnly = $service->saveRights($apiProvider, 'API only', [
    'store_local' => 'yes', 'process_internal' => 'yes', 'commercial_display' => 'yes', 'saas_multi' => 'yes', 'access_mode' => 'api',
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
$token = strtoupper(substr(uniqid(), -6));
$payload = "identity,label\n{$token}A,Sample\n{$token}A,Duplicate\nbad, \n{$token}B,Second\n";
$rowsBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_gulf_import_rows')->fetchColumn();
$dry = $service->dryRun($allowed, 'SA', $payload, '2026-11-04');
p6($dry['wrote'] === false && $dry['inserted'] === 2 && $dry['skipped'] === 1 && $dry['rejected'] === 1, 'dry-run counts inserts, duplicates, and rejects');
p6($dry['sha256'] === hash('sha256', $payload), 'SHA-256 matches the payload');
p6((int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_gulf_import_rows')->fetchColumn() === $rowsBefore, 'dry-run inserts no fixture rows');

$applied = $service->importFixture($allowed, 'SA', $payload, '2026-11-04');
$again = $service->importFixture($allowed, 'SA', $payload, '2026-11-04');
p6($applied['wrote'] === true && $again['wrote'] === false && $again['inserted'] === $applied['inserted'] && $again['skipped'] === $applied['skipped'] && $again['rejected'] === $applied['rejected'] && $again['status'] === 'applied', 'a repeated fingerprint returns the stored report');
p6((int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_gulf_import_rows')->fetchColumn() === $rowsBefore + 2, 'duplicate identities are stored once');

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

$mismatch = false;
try {
    $service->dryRun($allowed, 'SA', $payload, '2026-11-04', 'vehicle_identity', false, str_repeat('a', 64));
} catch (RuntimeException $e) {
    $mismatch = $e->getMessage() === 'checksum_mismatch';
}
p6($mismatch, 'a supplied checksum that does not match the file is rejected');

$grant = [
    'store_local' => 'yes', 'process_internal' => 'yes', 'commercial_display' => 'yes', 'saas_multi' => 'yes', 'redistribute' => 'yes', 'diagrams' => 'yes', 'access_mode' => 'bulk',
];
foreach (['suspended', 'pending', 'rejected'] as $blockedStatus) {
    $id = $service->saveProvider(['code' => 'P6' . strtoupper(substr($blockedStatus, 0, 1) . substr(uniqid(), -5)), 'provider_type' => 'data_provider', 'name_en' => $blockedStatus, 'name_ar' => 'حالة', 'status' => $blockedStatus, 'source_note' => 'status fixture'], ['SA'], ['vehicle_identity']);
    $rights = $service->saveRights($id, 'Permissive but provider is not active', $grant, 'SA', null, null);
    p6(!$service->commercialImportAllowed($rights, 'SA', '2026-11-04'), $blockedStatus . ' provider cannot authorize an import');
}
$redist = $service->saveProvider(['code' => 'P6R' . strtoupper(substr(uniqid(), -6)), 'provider_type' => 'data_provider', 'name_en' => 'Redist', 'name_ar' => 'توزيع', 'status' => 'active', 'source_note' => 'redistribution fixture'], ['SA'], ['vehicle_identity']);
$unknownRedist = $service->saveRights($redist, 'Display without redistribution', [
    'store_local' => 'yes', 'process_internal' => 'yes', 'commercial_display' => 'yes', 'saas_multi' => 'yes', 'redistribute' => 'unknown', 'access_mode' => 'bulk',
], 'SA', null, null);
p6(!$service->commercialImportAllowed($unknownRedist, 'SA', '2026-11-04', 'vehicle_identity', true), 'unknown redistribution blocks redistribution');
p6($service->commercialImportAllowed($unknownRedist, 'SA', '2026-11-04', 'vehicle_identity', false), 'display does not borrow redistribution permission');

$futureProvider = $service->saveProvider(['code' => 'P6F' . strtoupper(substr(uniqid(), -6)), 'provider_type' => 'data_provider', 'name_en' => 'Future', 'name_ar' => 'لاحق', 'status' => 'active', 'source_note' => 'future fixture'], ['SA'], ['vehicle_identity']);
$future = $service->saveRights($futureProvider, 'Starts later', $grant, 'SA', null, null, '2026-12-01');
p6(!$service->commercialImportAllowed($future, 'SA', '2026-11-04'), 'a future effective date does not authorize import');

$diagramProvider = $service->saveProvider(['code' => 'P6D' . strtoupper(substr(uniqid(), -6)), 'provider_type' => 'data_provider', 'name_en' => 'Diagrams', 'name_ar' => 'رسوم', 'status' => 'active', 'source_note' => 'diagram fixture'], ['SA'], ['diagrams']);
$noDiagram = $service->saveRights($diagramProvider, 'No diagram grant', [
    'store_local' => 'yes', 'process_internal' => 'yes', 'commercial_display' => 'yes', 'saas_multi' => 'yes', 'diagrams' => 'unknown', 'access_mode' => 'bulk',
], 'SA', null, null, null, 'diagrams');
p6(!$service->commercialImportAllowed($noDiagram, 'SA', '2026-11-04', 'diagrams'), 'diagram import requires diagram permission');

$conflictProvider = $service->saveProvider(['code' => 'P6C' . strtoupper(substr(uniqid(), -6)), 'provider_type' => 'data_provider', 'name_en' => 'Conflict', 'name_ar' => 'تعارض', 'status' => 'active', 'source_note' => 'conflict fixture'], ['SA'], ['vehicle_identity']);
$deny = $service->saveRights($conflictProvider, 'Redistribution denied', [
    'store_local' => 'yes', 'process_internal' => 'yes', 'commercial_display' => 'yes', 'saas_multi' => 'yes', 'redistribute' => 'no', 'access_mode' => 'bulk',
], 'SA', null, null);
$allow = $service->saveRights($conflictProvider, 'Redistribution allowed', $grant, 'SA', null, null);
$conflicted = false;
try {
    $service->dryRun($allow, 'SA', "identity,label\nFIX-9,Sample\n", '2026-11-04', 'vehicle_identity', true);
} catch (RuntimeException $e) {
    $conflicted = $e->getMessage() === 'rights_conflict';
}
$evidence = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_rights_conflicts WHERE left_rights_id = ' . min($deny, $allow) . ' AND right_rights_id = ' . max($deny, $allow))->fetchColumn();
p6($conflicted && $evidence === 1, 'conflicting allow and deny records block import and keep evidence');

p6(AutomotiveUsCatalogService::economyUnit('Electricity') === 'MPGe', 'electricity uses MPGe');
p6(AutomotiveUsCatalogService::economyUnit('Regular Gasoline') === 'MPG', 'gasoline uses MPG');
p6(!str_contains(AutomotiveUsCatalogService::licenseText('US government public domain, 17 USC 105'), '17 USC'), 'stored public-domain wording is not displayed');
p6(!str_contains((string) file_get_contents(dirname(__DIR__) . '/app/Services/AutomotiveUsImportService.php'), '17 USC'), 'new imports do not store the unsupported statute claim');

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
