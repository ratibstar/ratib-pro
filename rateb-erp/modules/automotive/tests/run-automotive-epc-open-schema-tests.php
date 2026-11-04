<?php
declare(strict_types=1);

/**
 * Open-model schema checks on admin_rateb_epc_phase0 only.
 * Inserts are synthetic and rolled back. The VehiclesDB file is not imported.
 * Run: php rateb-erp/modules/automotive/tests/run-automotive-epc-open-schema-tests.php
 */

if (getenv('RATEB_EPC_DISPOSABLE_DB') !== 'admin_rateb_epc_phase0') {
    echo "NOT RUN: database tests require admin_rateb_epc_phase0\n";
    exit(2);
}
$dsn = (string) getenv('RATEB_EPC_DSN');
if (!str_contains($dsn, 'dbname=admin_rateb_epc_phase0')) {
    echo "FAIL: DSN is not the disposable database\n";
    exit(1);
}
$pdo = new PDO($dsn, (string) getenv('RATEB_EPC_USER'), (string) getenv('RATEB_EPC_PASS'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$db = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($db !== 'admin_rateb_epc_phase0') {
    echo "FAIL: refused database {$db}\n";
    exit(1);
}

$passed = 0;
$failed = 0;
function sch(bool $ok, string $label): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

$tables = [
    'rateb_auto_ref_open_releases',
    'rateb_auto_ref_open_models',
    'rateb_auto_ref_open_model_sources',
    'rateb_auto_ref_open_model_licenses',
    'rateb_auto_ref_open_model_markets',
];
$companyCols = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'company_id'
       AND TABLE_NAME IN ('" . implode("','", $tables) . "')"
)->fetchColumn();
sch($companyCols === 0, 'open reference tables have no company_id');

$yearCols = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_auto_ref_open_model_markets'
       AND COLUMN_NAME IN ('year_from', 'year_to')"
)->fetchColumn();
sch($yearCols === 0, 'market evidence does not require years');

$nullableAr = (string) $pdo->query(
    "SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_auto_ref_open_models' AND COLUMN_NAME = 'name_ar'"
)->fetchColumn();
sch($nullableAr === 'YES', 'Arabic model names are nullable');

$phase2 = (string) $pdo->query(
    "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)
     FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_auto_ref_models' AND INDEX_NAME = 'uq_auto_ref_model'"
)->fetchColumn();
sch($phase2 === 'vehicle_brand_id,normalized_name', 'Phase 2 model uniqueness is unchanged');

$inventoryBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn();
$supplierBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_suppliers')->fetchColumn();
$selectionBefore = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_company_selections')->fetchColumn();

$pdo->beginTransaction();
$pdo->prepare(
    'INSERT INTO rateb_auto_ref_open_releases (provider_code, version_label, retrieved_on, attribution, file_sha256)
     VALUES (:provider, :version, :retrieved, :attribution, :hash)'
)->execute([
    'provider' => 'schema-probe',
    'version' => '0',
    'retrieved' => '2026-11-04',
    'attribution' => 'Vehicle data by VehiclesDB (https://vehiclesdb.com)',
    'hash' => str_repeat('a', 64),
]);
$releaseId = (int) $pdo->lastInsertId();
$insertModel = $pdo->prepare(
    'INSERT INTO rateb_auto_ref_open_models
     (release_id, source_record_id, kind, make_slug, model_slug, name_en, name_ar, record_sha256)
     VALUES (:release, :source, :kind, :make, :model, :en, :ar, :hash)'
);
$insertModel->execute([
    'release' => $releaseId,
    'source' => 'ford/transit',
    'kind' => 'van',
    'make' => 'ford',
    'model' => 'transit',
    'en' => 'Transit',
    'ar' => null,
    'hash' => str_repeat('b', 64),
]);
$vanId = (int) $pdo->lastInsertId();
$insertModel->execute([
    'release' => $releaseId,
    'source' => 'ford/transit',
    'kind' => 'car',
    'make' => 'ford',
    'model' => 'transit',
    'en' => 'Transit',
    'ar' => null,
    'hash' => str_repeat('c', 64),
]);
$carId = (int) $pdo->lastInsertId();
sch($vanId > 0 && $carId > 0 && $vanId !== $carId, 'the same source id can exist for two vehicle kinds');
$dup = false;
try {
    $insertModel->execute([
        'release' => $releaseId,
        'source' => 'ford/transit',
        'kind' => 'van',
        'make' => 'ford',
        'model' => 'transit',
        'en' => 'Other',
        'ar' => null,
        'hash' => str_repeat('d', 64),
    ]);
} catch (PDOException $e) {
    $dup = str_contains($e->getMessage(), '1062');
}
sch($dup, 'a repeated kind and source id is rejected');

$src = $pdo->prepare(
    'INSERT INTO rateb_auto_ref_open_model_sources (model_id, origin_code, license_code) VALUES (:model, :origin, :license)'
);
$src->execute(['model' => $vanId, 'origin' => 'nl_rdw', 'license' => 'CC0-1.0']);
$src->execute(['model' => $vanId, 'origin' => 'nz_nzta', 'license' => 'CC-BY-4.0']);
$lic = $pdo->prepare('INSERT INTO rateb_auto_ref_open_model_licenses (model_id, license_code) VALUES (:model, :license)');
$lic->execute(['model' => $vanId, 'license' => 'CC0-1.0']);
$lic->execute(['model' => $vanId, 'license' => 'CC-BY-4.0']);
$mkt = $pdo->prepare(
    'INSERT INTO rateb_auto_ref_open_model_markets (model_id, market_code, origin_code, evidence) VALUES (:model, :market, :origin, :evidence)'
);
$mkt->execute(['model' => $vanId, 'market' => 'nl', 'origin' => 'nl_rdw', 'evidence' => 'registration']);
$mkt->execute(['model' => $vanId, 'market' => 'nz', 'origin' => 'nz_nzta', 'evidence' => 'registration']);
$counts = $pdo->prepare(
    'SELECT
        (SELECT COUNT(*) FROM rateb_auto_ref_open_model_sources WHERE model_id = :source_id) AS sources,
        (SELECT COUNT(*) FROM rateb_auto_ref_open_model_licenses WHERE model_id = :license_id) AS licenses,
        (SELECT COUNT(*) FROM rateb_auto_ref_open_model_markets WHERE model_id = :market_id) AS markets,
        (SELECT name_ar FROM rateb_auto_ref_open_models WHERE id = :model_id) AS name_ar'
);
$counts->execute([
    'source_id' => $vanId,
    'license_id' => $vanId,
    'market_id' => $vanId,
    'model_id' => $vanId,
]);
$row = $counts->fetch(PDO::FETCH_ASSOC);
sch((int) $row['sources'] === 2 && (int) $row['licenses'] === 2 && (int) $row['markets'] === 2, 'one model keeps two sources, two licenses, and two markets');
sch($row['name_ar'] === null, 'a model can be stored without an Arabic name');
$attr = (string) $pdo->query('SELECT attribution FROM rateb_auto_ref_open_releases WHERE id = ' . $releaseId)->fetchColumn();
sch(str_contains($attr, 'VehiclesDB') && str_contains($attr, 'https://vehiclesdb.com'), 'release attribution is stored intact');
$pdo->rollBack();

$left = (int) $pdo->query("SELECT COUNT(*) FROM rateb_auto_ref_open_releases WHERE provider_code = 'schema-probe'")->fetchColumn();
sch($left === 0, 'synthetic rows were rolled back');
$inventoryAfter = (int) $pdo->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn();
$supplierAfter = (int) $pdo->query('SELECT COUNT(*) FROM rateb_suppliers')->fetchColumn();
$selectionAfter = (int) $pdo->query('SELECT COUNT(*) FROM rateb_auto_company_selections')->fetchColumn();
sch($inventoryBefore === $inventoryAfter && $supplierBefore === $supplierAfter && $selectionBefore === $selectionAfter, 'inventory, suppliers, and company selections stay unchanged');

echo "Open schema checks: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
