<?php
declare(strict_types=1);

/**
 * Automotive Phase 2 catalog behavior on an in-memory database.
 * Does not connect to the ERP database and does not run migrations.
 * Run: php rateb-erp/modules/automotive/tests/run-automotive-phase2-tests.php
 */

$root = dirname(__DIR__, 3);
require_once $root . '/modules/automotive/app/Services/AutomotiveCatalogService.php';

use Rateb\App\Automotive\Services\AutomotiveCatalogService;

$passed = 0;
$failed = 0;

function auto2_assert(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        echo "PASS: {$label}\n";
        $passed++;
        return;
    }
    echo "FAIL: {$label}\n";
    $failed++;
}

$pdo = null;
try {
    if (getenv('RATEB_EPC_DISPOSABLE_DB') === 'admin_rateb_epc_phase0') {
        $pdo = new PDO(
            (string) getenv('RATEB_EPC_DSN'),
            (string) getenv('RATEB_EPC_USER'),
            (string) getenv('RATEB_EPC_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $actualDb = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($actualDb !== 'admin_rateb_epc_phase0') {
            echo "FAIL: catalog test refused database {$actualDb}\n";
            exit(1);
        }
        $busy = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN (
                 'rateb_auto_parts','rateb_auto_part_numbers','rateb_auto_barcodes',
                 'rateb_auto_vehicles','rateb_auto_fitment','rateb_auto_interchange'
               )"
        )->fetchColumn();
        if ($busy > 0) {
            echo "NOT RUN: disposable database already contains catalog tables\n";
            exit(2);
        }
        $heldInventory = (int) $pdo->query(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory'"
        )->fetchColumn() > 0;
        if ($heldInventory) {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $pdo->exec('RENAME TABLE rateb_inventory TO rateb_inventory_epc_hold');
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        register_shutdown_function(static function () use ($pdo, $heldInventory): void {
            foreach ([
                'rateb_auto_interchange', 'rateb_auto_fitment', 'rateb_auto_barcodes',
                'rateb_auto_part_numbers', 'rateb_auto_vehicles', 'rateb_auto_parts', 'rateb_inventory',
            ] as $table) {
                $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
            }
            if ($heldInventory) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                $pdo->exec('RENAME TABLE rateb_inventory_epc_hold TO rateb_inventory');
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        });
    } elseif (extension_loaded('pdo_sqlite')) {
        $pdo = new PDO('sqlite::memory:');
    } else {
        $server = new PDO('mysql:host=127.0.0.1', 'root', '');
        $server->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $server->exec('CREATE DATABASE IF NOT EXISTS rateb_auto_phase2_tmp');
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=rateb_auto_phase2_tmp', 'root', '');
        register_shutdown_function(static function (): void {
            try {
                $drop = new PDO('mysql:host=127.0.0.1', 'root', '');
                $drop->exec('DROP DATABASE IF EXISTS rateb_auto_phase2_tmp');
            } catch (Throwable) {
            }
        });
    }
} catch (Throwable $e) {
    echo 'NOT RUN: catalog behavior tests need pdo_sqlite or a local MySQL server (' . $e->getMessage() . ")\n";
    $failed++;
    $pdo = null;
}
if ($pdo instanceof PDO) {
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE rateb_inventory (id INTEGER PRIMARY KEY, company_id INTEGER NOT NULL, item_name TEXT, sku TEXT, item_code TEXT, quantity REAL NOT NULL DEFAULT 0)');
$pdo->exec('CREATE TABLE rateb_auto_parts (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    company_id INTEGER NOT NULL,
    inventory_id INTEGER NOT NULL UNIQUE,
    manufacturer TEXT, brand TEXT, oem_number TEXT, status TEXT NOT NULL DEFAULT "active",
    UNIQUE (company_id, inventory_id),
    UNIQUE (company_id, oem_number)
)');
$pdo->exec('CREATE TABLE rateb_auto_part_numbers (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    company_id INTEGER NOT NULL,
    part_id INTEGER NOT NULL,
    code TEXT NOT NULL,
    code_type TEXT NOT NULL,
    UNIQUE (company_id, code)
)');
$pdo->exec('CREATE TABLE rateb_auto_barcodes (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    company_id INTEGER NOT NULL,
    part_id INTEGER NOT NULL,
    code TEXT NOT NULL,
    UNIQUE (company_id, code)
)');
$pdo->exec('CREATE TABLE rateb_auto_vehicles (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    company_id INTEGER NOT NULL,
    make TEXT NOT NULL, model TEXT NOT NULL, model_year INTEGER NOT NULL,
    engine TEXT NOT NULL DEFAULT "", trim TEXT NOT NULL DEFAULT "",
    UNIQUE (company_id, make, model, model_year, engine, trim)
)');
$pdo->exec('CREATE TABLE rateb_auto_fitment (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    company_id INTEGER NOT NULL,
    part_id INTEGER NOT NULL,
    vehicle_id INTEGER NOT NULL,
    UNIQUE (part_id, vehicle_id)
)');
$pdo->exec('CREATE TABLE rateb_auto_interchange (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    company_id INTEGER NOT NULL,
    part_id INTEGER NOT NULL,
    alternate_inventory_id INTEGER NOT NULL,
    relation_type TEXT NOT NULL DEFAULT "interchange",
    UNIQUE (company_id, part_id, alternate_inventory_id)
)');
$pdo->exec("INSERT INTO rateb_inventory (id, company_id, item_name, quantity) VALUES (10, 1, 'Filter', 5), (11, 1, 'Pad', 2), (20, 2, 'Other', 9)");

$catalog = new AutomotiveCatalogService($pdo);
$before = (int) $pdo->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn();
$partId = $catalog->createPart(1, 10, 'Toyota', 'Denso', 'oem-123', 'active');
$after = (int) $pdo->query('SELECT COUNT(*) FROM rateb_inventory')->fetchColumn();
auto2_assert($partId > 0, 'creates a part for an existing inventory id');
auto2_assert($before === $after, 'does not create an inventory row');
auto2_assert((float) $pdo->query('SELECT quantity FROM rateb_inventory WHERE id = 10')->fetchColumn() === 5.0, 'does not change stock quantity');

$cross = false;
try {
    $catalog->createPart(2, 10, 'X', 'Y', 'OTHER', 'active');
} catch (InvalidArgumentException) {
    $cross = true;
}
auto2_assert($cross, 'rejects an inventory id from another company');

$dupOem = false;
try {
    $catalog->createPart(1, 11, 'A', 'B', 'OEM-123', 'active');
} catch (InvalidArgumentException) {
    $dupOem = true;
}
auto2_assert($dupOem, 'rejects a duplicate OEM number in the company');

$other = $catalog->createPart(1, 11, 'Bosch', 'Bosch', 'OEM-999', 'active');
$catalog->addBarcode(1, $partId, 'BC100');
$dupBarcode = false;
try {
    $catalog->addBarcode(1, $other, 'bc100');
} catch (InvalidArgumentException) {
    $dupBarcode = true;
}
auto2_assert($dupBarcode, 'rejects a duplicate barcode in the company');

$clash = false;
try {
    $catalog->addNumber(1, $other, 'oem-123', 'aftermarket');
} catch (InvalidArgumentException) {
    $clash = true;
}
auto2_assert($clash, 'rejects an alternate number that matches an OEM number');

$catalog->addNumber(1, $other, 'AF-1', 'aftermarket');
$vehicleId = $catalog->createVehicle(1, 'Toyota', 'Camry', 2020, '2.5', 'LE');
$fit = $catalog->addFitment(1, $partId, $vehicleId);
auto2_assert($fit > 0, 'links fitment inside the company');

$foreignVehicle = $catalog->createVehicle(2, 'Honda', 'Civic', 2019, '', '');
$foreignFit = false;
try {
    $catalog->addFitment(1, $partId, $foreignVehicle);
} catch (InvalidArgumentException) {
    $foreignFit = true;
}
auto2_assert($foreignFit, 'rejects fitment to another company vehicle');

$foreignAlt = false;
try {
    $catalog->addInterchange(1, $partId, 20);
} catch (InvalidArgumentException) {
    $foreignAlt = true;
}
auto2_assert($foreignAlt, 'rejects interchange to another company inventory id');

$visible = $catalog->listParts(2);
auto2_assert($visible === [], 'company 2 does not see company 1 parts');
$detail = $catalog->partDetail(1, $partId);
auto2_assert((int) $detail['inventory_id'] === 10, 'part detail keeps the original inventory id');
auto2_assert(count($detail['barcodes']) === 1, 'barcode is stored on the part');
auto2_assert(count($detail['fitment']) === 1, 'fitment is stored on the part');
}

$migration = (string) file_get_contents($root . '/migrations/272_automotive_catalog.sql');
auto2_assert(!preg_match('/ALTER TABLE\s+rateb_inventory/i', $migration), 'migration does not alter rateb_inventory');
auto2_assert(str_contains($migration, 'rateb_auto_parts'), 'migration creates parts table');
auto2_assert(str_contains($migration, 'company_id'), 'migration columns are company scoped');

echo "\nAutomotive Phase 2: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
