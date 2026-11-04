<?php
declare(strict_types=1);

/**
 * Phase 3 licensed-import checks. Database work runs only on admin_rateb_epc_phase0.
 * Run: php rateb-erp/modules/automotive/tests/run-automotive-epc-phase3-tests.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/modules/automotive/app/Services/AutomotiveLicensedProviders.php';
require_once $root . '/modules/automotive/app/Services/AutomotiveSvg.php';
require_once $root . '/modules/automotive/app/Services/AutomotiveGate.php';
require_once $root . '/modules/automotive/app/Services/AutomotiveImportService.php';
require_once $root . '/modules/automotive/app/Services/AutomotiveCoverageService.php';
require_once $root . '/modules/automotive/app/Services/AutomotiveGlobalCatalogService.php';
require_once $root . '/modules/automotive/app/Services/AutomotiveSelectionService.php';
require_once $root . '/app/Core/TenantContext.php';

use Rateb\App\Automotive\Services\AutomotiveCoverageService;
use Rateb\App\Automotive\Services\AutomotiveGlobalCatalogService;
use Rateb\App\Automotive\Services\AutomotiveImportService;
use Rateb\App\Automotive\Services\AutomotiveLicensedProviders;
use Rateb\App\Automotive\Services\AutomotiveSelectionService;
use Rateb\App\Core\TenantContext;

if (!function_exists('rateb_can')) {
    function rateb_can(string $slug): bool
    {
        return true;
    }
}

$passed = 0;
$failed = 0;
function p3(bool $cond, string $label): void
{
    global $passed, $failed;
    echo ($cond ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $cond ? $passed++ : $failed++;
}

$providers = AutomotiveLicensedProviders::catalog();
p3(count($providers) === 2, 'two licensed providers are identified');
p3($providers[0]['loaded'] === false && $providers[1]['loaded'] === false, 'no provider dataset is marked loaded');
p3(!str_contains(json_encode($providers), 'worldwide'), 'provider text does not claim worldwide coverage');

$rejected = false;
try {
    AutomotiveLicensedProviders::parse("provider,unknown\n" . AutomotiveLicensedProviders::HEADER . "\npart,ZZ,A,ب,,,,,");
} catch (InvalidArgumentException) {
    $rejected = true;
}
p3($rejected, 'unknown provider is rejected');

$scraped = false;
try {
    AutomotiveLicensedProviders::parse("<html><script></script>");
} catch (InvalidArgumentException) {
    $scraped = true;
}
p3($scraped, 'scraped markup is rejected');

if (getenv('RATEB_EPC_DISPOSABLE_DB') !== 'admin_rateb_epc_phase0') {
    echo "NOT RUN: database tests require admin_rateb_epc_phase0\n";
    echo "Phase 3 checks: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 2);
}

$pdo = new PDO((string) getenv('RATEB_EPC_DSN'), (string) getenv('RATEB_EPC_USER'), (string) getenv('RATEB_EPC_PASS'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$actual = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($actual !== 'admin_rateb_epc_phase0') {
    echo "FAIL: refused database {$actual}\n";
    exit(1);
}
$pdo->exec("DELETE f FROM rateb_auto_ref_fitment f JOIN rateb_auto_ref_parts p ON p.id = f.part_id WHERE p.normalized_name = 'PROBE PART'");
$pdo->exec("DELETE d FROM rateb_auto_ref_diagrams d JOIN rateb_auto_ref_assemblies a ON a.id = d.assembly_id WHERE a.code = 'ASM-P3'");
$pdo->exec("DELETE FROM rateb_auto_ref_assemblies WHERE code = 'ASM-P3'");
$pdo->exec("DELETE FROM rateb_auto_ref_systems WHERE code = 'SYS-P3'");
$pdo->exec("DELETE v FROM rateb_auto_ref_vehicles v JOIN rateb_auto_ref_generations g ON g.id = v.generation_id JOIN rateb_auto_ref_markets m ON m.id = g.market_id WHERE m.code = 'ZZ3'");
$pdo->exec("DELETE g FROM rateb_auto_ref_generations g JOIN rateb_auto_ref_markets m ON m.id = g.market_id WHERE m.code = 'ZZ3'");
$pdo->exec("DELETE mo FROM rateb_auto_ref_models mo JOIN rateb_auto_ref_vehicle_brands b ON b.id = mo.vehicle_brand_id WHERE b.normalized_name = 'PROBE BRAND'");
$pdo->exec("DELETE FROM rateb_auto_ref_parts WHERE normalized_name = 'PROBE PART'");
$pdo->exec("DELETE FROM rateb_auto_ref_engines WHERE code = 'ENG-P3'");
$pdo->exec("DELETE FROM rateb_auto_ref_transmissions WHERE code = 'TR-P3'");
$pdo->exec("DELETE r FROM rateb_auto_ref_import_rows r JOIN rateb_auto_ref_import_batches b ON b.id = r.batch_id JOIN rateb_auto_ref_sources s ON s.id = b.source_id WHERE s.dataset_name IN ('Probe license','Blank license')");
$pdo->exec("DELETE a FROM rateb_auto_ref_audit a JOIN rateb_auto_ref_import_batches b ON b.id = a.batch_id JOIN rateb_auto_ref_sources s ON s.id = b.source_id WHERE s.dataset_name IN ('Probe license','Blank license')");
$pdo->exec("DELETE b FROM rateb_auto_ref_import_batches b JOIN rateb_auto_ref_sources s ON s.id = b.source_id WHERE s.dataset_name IN ('Probe license','Blank license')");
$pdo->exec("DELETE FROM rateb_auto_ref_sources WHERE dataset_name IN ('Probe license','Blank license')");
$pdo->exec("DELETE FROM rateb_auto_ref_vehicle_brands WHERE normalized_name = 'PROBE BRAND'");
$pdo->exec("DELETE FROM rateb_auto_ref_orgs WHERE normalized_name = 'PROBE MAKER'");
$pdo->exec("DELETE FROM rateb_auto_ref_markets WHERE code = 'ZZ3'");
$inventoryBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn();
$supplierBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_suppliers')->fetchColumn();
$_SESSION['rateb_user_id'] = 1;
TenantContext::setCompanyId(7);

$header = AutomotiveLicensedProviders::HEADER;
$csv = "provider,tecalliance_tecdoc\n{$header}\n"
    . "engine,ZZ3,Probe Engine," . "\u{0645}\u{062d}\u{0631}\u{0643}" . ",,,,ENG-P3,\n"
    . "transmission,ZZ3,Probe Transmission," . "\u{0646}\u{0627}\u{0642}\u{0644}" . ",,,,TR-P3,\n"
    . "model,ZZ3,Probe Model," . "\u{0645}\u{0648}\u{062f}\u{064a}\u{0644}" . ",PROBE BRAND,,,,\n"
    . "generation,ZZ3,Probe Generation," . "\u{062c}\u{064a}\u{0644}" . ",PROBE MODEL,2020,2024,,PROBE BRAND\n"
    . "vehicle,ZZ3,Probe Vehicle," . "\u{0645}\u{0631}\u{0643}\u{0628}\u{0629}" . ",PROBE MODEL,2020,2024,ENG-P3|TR-P3,PROBE BRAND\n"
    . "part,ZZ3,Probe Part," . "\u{0642}\u{0637}\u{0639}\u{0629}" . ",,,,,\n"
    . "fitment,ZZ3,Probe Part," . "\u{0642}\u{0637}\u{0639}\u{0629}" . ",PROBE MODEL,2020,2024,ENG-P3|TR-P3,PROBE BRAND\n"
    . "diagram,ZZ3,Probe Diagram," . "\u{0631}\u{0633}\u{0645}" . ",ASM-P3,,,SYS-P3,\n";

$pdo->beginTransaction();
$pdo->prepare('INSERT INTO rateb_auto_ref_markets (code, name_en, name_ar) VALUES (:code, :en, :ar)')
    ->execute(['code' => 'ZZ3', 'en' => 'Probe Market', 'ar' => "\u{0633}\u{0648}\u{0642}"]);
$marketId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO rateb_auto_ref_orgs (org_role, home_market_id, name_en, name_ar, normalized_name) VALUES (:role, :market, :en, :ar, :norm)')
    ->execute(['role' => 'vehicle_manufacturer', 'market' => $marketId, 'en' => 'Probe Maker', 'ar' => "\u{0635}\u{0627}\u{0646}\u{0639}", 'norm' => 'PROBE MAKER']);
$orgId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO rateb_auto_ref_vehicle_brands (vehicle_mfr_org_id, name_en, name_ar, normalized_name) VALUES (:org, :en, :ar, :norm)')
    ->execute(['org' => $orgId, 'en' => 'Probe Brand', 'ar' => "\u{0639}\u{0644}\u{0627}\u{0645}\u{0629}", 'norm' => 'PROBE BRAND']);
$pdo->prepare('INSERT INTO rateb_auto_ref_sources (org_id, dataset_name, license_ref, market_id, permitted_use) VALUES (:org, :name, :license, :market, :use)')
    ->execute(['org' => $orgId, 'name' => 'Probe license', 'license' => 'LIC-P3', 'market' => $marketId, 'use' => 'test']);
$sourceId = (int) $pdo->lastInsertId();
$pdo->prepare('INSERT INTO rateb_auto_ref_sources (org_id, dataset_name, license_ref, market_id, permitted_use) VALUES (:org, :name, :license, :market, :use)')
    ->execute(['org' => $orgId, 'name' => 'Blank license', 'license' => ' ', 'market' => $marketId, 'use' => 'test']);
$blankSource = (int) $pdo->lastInsertId();
$pdo->commit();

$import = new AutomotiveImportService($pdo);
$unlicensed = false;
try {
    $import->preview($blankSource, $csv);
} catch (InvalidArgumentException $e) {
    $unlicensed = $e->getMessage() === 'unlicensed_source';
}
p3($unlicensed, 'a source without a license is rejected');

$preview = $import->preview($sourceId, $csv);
p3($preview['rows'] === 8 && $preview['conflicts'] === 0, 'licensed file previews eight rows');
$again = false;
try {
    $import->preview($sourceId, $csv);
} catch (InvalidArgumentException $e) {
    $again = $e->getMessage() === 'duplicate_import';
}
p3($again, 'the same licensed file cannot be previewed twice');
$import->approve((int) $preview['batch_id']);
$parts = (int) $pdo->query("SELECT COUNT(*) FROM rateb_auto_ref_parts WHERE normalized_name = 'PROBE PART'")->fetchColumn();
$fitment = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_ref_fitment f JOIN rateb_auto_ref_parts p ON p.id = f.part_id WHERE p.normalized_name = \'PROBE PART\'')->fetchColumn();
p3($parts === 1 && $fitment === 1, 'approval creates one part and one confirmed fitment');

$conflictCsv = "provider,autocare_aces_pies\n{$header}\npart,ZZ3,Probe Part," . "\u{0642}\u{0637}\u{0639}\u{0629}" . ",,,,,\n";
$conflict = $import->preview($sourceId, $conflictCsv);
p3($conflict['conflicts'] === 1, 'an existing part name is a conflict before approval');
$import->approve((int) $conflict['batch_id']);
$partsAfter = (int) $pdo->query("SELECT COUNT(*) FROM rateb_auto_ref_parts WHERE normalized_name = 'PROBE PART'")->fetchColumn();
p3($partsAfter === 1, 'approval skips the conflicting part');

$coverage = (new AutomotiveCoverageService($pdo))->summary();
$covered = array_values(array_filter($coverage, static fn (array $row): bool => $row['market_code'] === 'ZZ3'));
p3(count($covered) === 1 && (int) $covered[0]['year_from'] === 2020 && (int) $covered[0]['year_to'] === 2024, 'coverage reports only the imported market and years');

$found = (new AutomotiveGlobalCatalogService($pdo))->search('Probe Part');
p3($found !== [] && (int) $found[0]['id'] > 0, 'imported part is searchable');
$selectionId = (new AutomotiveSelectionService($pdo))->select('part', (int) $found[0]['id']);
p3($selectionId > 0, 'a company can select the imported part');

$pdo->prepare('DELETE FROM rateb_auto_company_selections WHERE id = :id')->execute(['id' => $selectionId]);
$pdo->exec("DELETE f FROM rateb_auto_ref_fitment f JOIN rateb_auto_ref_parts p ON p.id = f.part_id WHERE p.normalized_name = 'PROBE PART'");
$pdo->exec("DELETE d FROM rateb_auto_ref_diagrams d JOIN rateb_auto_ref_assemblies a ON a.id = d.assembly_id WHERE a.code = 'ASM-P3'");
$pdo->exec("DELETE FROM rateb_auto_ref_assemblies WHERE code = 'ASM-P3'");
$pdo->exec("DELETE FROM rateb_auto_ref_systems WHERE code = 'SYS-P3'");
$pdo->exec("DELETE v FROM rateb_auto_ref_vehicles v JOIN rateb_auto_ref_generations g ON g.id = v.generation_id JOIN rateb_auto_ref_markets m ON m.id = g.market_id WHERE m.code = 'ZZ3'");
$pdo->exec("DELETE g FROM rateb_auto_ref_generations g JOIN rateb_auto_ref_markets m ON m.id = g.market_id WHERE m.code = 'ZZ3'");
$pdo->exec("DELETE mo FROM rateb_auto_ref_models mo JOIN rateb_auto_ref_vehicle_brands b ON b.id = mo.vehicle_brand_id WHERE b.normalized_name = 'PROBE BRAND'");
$pdo->exec("DELETE FROM rateb_auto_ref_parts WHERE normalized_name = 'PROBE PART'");
$pdo->exec("DELETE FROM rateb_auto_ref_engines WHERE code = 'ENG-P3'");
$pdo->exec("DELETE FROM rateb_auto_ref_transmissions WHERE code = 'TR-P3'");
$pdo->exec('DELETE a FROM rateb_auto_ref_audit a JOIN rateb_auto_ref_import_batches b ON b.id = a.batch_id WHERE b.source_id IN (' . $sourceId . ',' . $blankSource . ')');
$pdo->exec('DELETE r FROM rateb_auto_ref_import_rows r JOIN rateb_auto_ref_import_batches b ON b.id = r.batch_id WHERE b.source_id IN (' . $sourceId . ',' . $blankSource . ')');
$pdo->exec('DELETE FROM rateb_auto_ref_import_batches WHERE source_id IN (' . $sourceId . ',' . $blankSource . ')');
$pdo->exec('DELETE FROM rateb_auto_ref_sources WHERE id IN (' . $sourceId . ',' . $blankSource . ')');
$pdo->exec("DELETE FROM rateb_auto_ref_vehicle_brands WHERE normalized_name = 'PROBE BRAND'");
$pdo->exec("DELETE FROM rateb_auto_ref_orgs WHERE normalized_name = 'PROBE MAKER'");
$pdo->exec("DELETE FROM rateb_auto_ref_markets WHERE code = 'ZZ3'");

$inventoryAfter = (int) $pdo->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn();
$supplierAfter = (int) $pdo->query('SELECT COUNT(*) FROM rateb_suppliers')->fetchColumn();
p3($inventoryBefore === $inventoryAfter && $supplierBefore === $supplierAfter, 'inventory and suppliers stay unchanged');
$left = (int) $pdo->query("SELECT COUNT(*) FROM rateb_auto_ref_markets WHERE code = 'ZZ3'")->fetchColumn();
p3($left === 0, 'probe rows are removed');

echo "Phase 3 checks: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
