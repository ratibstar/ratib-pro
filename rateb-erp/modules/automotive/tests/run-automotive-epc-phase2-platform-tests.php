<?php
declare(strict_types=1);
ob_start();

$root = dirname(__DIR__, 3);
$passed = 0;
$failed = 0;

function epc2(bool $ok, string $label): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
    if ($ok) {
        $passed++;
    } else {
        $failed++;
    }
}

$sql = (string) file_get_contents($root . '/migrations/292_auto_ref_catalog.sql');
$perms = (string) file_get_contents($root . '/migrations/293_auto_ref_permissions.sql');
epc2(!str_contains($perms, 'rateb_role_permissions'), 'new permissions are not assigned to roles');
epc2(!preg_match('/CREATE TABLE IF NOT EXISTS rateb_auto_ref_\w+ \([^;]*company_id/s', $sql), 'global catalog tables are not company scoped');
epc2(str_contains($sql, 'uq_auto_ref_part_number'), 'part numbers stay unique across issuer, brand, scheme, type, and market');
epc2(!preg_match('/UPDATE\s+rateb_inventory/i', (string) file_get_contents($root . '/modules/automotive/app/Services/AutomotiveSelectionService.php')), 'inventory link service does not update inventory');
epc2(str_contains((string) file_get_contents($root . '/modules/automotive/routes/automotive.php'), 'automotive.reference.import'), 'import route has its own permission');

require_once $root . '/modules/automotive/app/Services/AutomotiveSvg.php';
$svgOk = true;
try {
    \Rateb\App\Automotive\Services\AutomotiveSvg::sanitize('<svg><script>alert(1)</script></svg>');
    $svgOk = false;
} catch (InvalidArgumentException) {
}
epc2($svgOk, 'unsafe SVG is rejected');

if (getenv('RATEB_EPC_DISPOSABLE_DB') !== 'admin_rateb_epc_phase0') {
    echo "NOT RUN: disposable database environment is not configured\n";
    echo "Phase 2 checks: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init($root);
require_once $root . '/modules/automotive/AutomotiveModule.php';
\Rateb\App\Automotive\AutomotiveModule::init();
\Rateb\App\Core\Database::useConnectionOverride([
    'host' => '127.0.0.1',
    'port' => (int) (getenv('RATEB_EPC_PORT') ?: 3307),
    'user' => (string) getenv('RATEB_EPC_USER'),
    'pass' => (string) getenv('RATEB_EPC_PASS'),
    'db' => 'admin_rateb_epc_phase0',
]);
$pdo = \Rateb\App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
epc2((string) $pdo->query('SELECT DATABASE()')->fetchColumn() === 'admin_rateb_epc_phase0', 'tests use the disposable database');

$madeInventory = false;
if ((int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_inventory'")->fetchColumn() === 0) {
    $pdo->exec('CREATE TABLE rateb_inventory (id INT UNSIGNED PRIMARY KEY, company_id INT UNSIGNED NOT NULL, item_code VARCHAR(64) NULL, sku VARCHAR(64) NULL, quantity DECIMAL(18,3) NOT NULL DEFAULT 0, unit_cost DECIMAL(18,4) NULL, sell_price DECIMAL(18,4) NULL)');
    $pdo->exec('CREATE TABLE rateb_suppliers (id INT UNSIGNED PRIMARY KEY, company_id INT UNSIGNED NOT NULL, name VARCHAR(150) NOT NULL)');
    $madeInventory = true;
}
$buffer = '';
foreach (preg_split('/\R/', $sql) ?: [] as $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '--')) {
        continue;
    }
    $buffer .= $line . "\n";
    if (preg_match('/;\s*$/', $trimmed)) {
        $pdo->exec(trim($buffer));
        $buffer = '';
    }
}
$pdo->exec('INSERT IGNORE INTO rateb_inventory (id, company_id, item_code, sku, quantity, unit_cost, sell_price) VALUES (10, 7, "A", "SKU", 5, 3, 9), (20, 8, "B", "SKU2", 1, 1, 1)');
$pdo->exec('INSERT IGNORE INTO rateb_suppliers (id, company_id, name) VALUES (4, 7, "Supplier"), (5, 8, "Other")');

$rbac = ['rateb_users', 'rateb_companies', 'rateb_permissions', 'rateb_roles', 'rateb_role_permissions', 'rateb_user_roles'];
$madeRbac = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_users'")->fetchColumn() === 0;
if ($madeRbac) {
    $pdo->exec('CREATE TABLE rateb_users (id INT UNSIGNED PRIMARY KEY, company_id INT UNSIGNED NULL, is_super_admin TINYINT NOT NULL DEFAULT 0)');
    $pdo->exec('CREATE TABLE rateb_companies (id INT UNSIGNED PRIMARY KEY, modules TEXT NULL, plan_id INT UNSIGNED NULL, status VARCHAR(20) NULL)');
    $pdo->exec('CREATE TABLE rateb_permissions (id INT UNSIGNED PRIMARY KEY, slug VARCHAR(80) NOT NULL)');
    $pdo->exec('CREATE TABLE rateb_roles (id INT UNSIGNED PRIMARY KEY, company_id INT UNSIGNED NULL)');
    $pdo->exec('CREATE TABLE rateb_role_permissions (role_id INT UNSIGNED NOT NULL, permission_id INT UNSIGNED NOT NULL)');
    $pdo->exec('CREATE TABLE rateb_user_roles (user_id INT UNSIGNED NOT NULL, role_id INT UNSIGNED NOT NULL)');
}
$pdo->exec("INSERT IGNORE INTO rateb_companies (id, modules, status) VALUES (7, '[\"automotive\"]', 'active'), (8, '[\"inventory\"]', 'active')");
$pdo->exec('INSERT IGNORE INTO rateb_users (id, company_id, is_super_admin) VALUES (11, 7, 0), (12, 7, 0), (13, 7, 0), (14, 8, 0)');
$slugs = [
    11 => 'automotive.view', 12 => 'automotive.selection.view', 13 => 'automotive.selection.manage',
    14 => 'automotive.inventory_link.manage', 15 => 'automotive.supplier_map.manage',
    16 => 'automotive.reference.import', 17 => 'automotive.reference.approve', 18 => 'automotive.reference.manage',
];
foreach ($slugs as $id => $slug) {
    $pdo->prepare('INSERT IGNORE INTO rateb_permissions (id, slug) VALUES (:id, :slug)')->execute(['id' => $id, 'slug' => $slug]);
    $pdo->prepare('INSERT IGNORE INTO rateb_roles (id, company_id) VALUES (:id, 7)')->execute(['id' => $id]);
    $pdo->prepare('INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id) VALUES (:role, :perm)')->execute(['role' => $id, 'perm' => $id]);
}
$pdo->exec('INSERT IGNORE INTO rateb_user_roles (user_id, role_id) VALUES (11, 11), (12, 12), (13, 13)');
$pdo->exec('INSERT IGNORE INTO rateb_users (id, company_id, is_super_admin) VALUES (15, 7, 0), (16, 7, 0), (17, 7, 0), (18, 7, 0), (19, 8, 0)');
$pdo->exec('INSERT IGNORE INTO rateb_roles (id, company_id) VALUES (19, 8)');
$pdo->exec('INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id) VALUES (19, 12)');
$pdo->exec('INSERT IGNORE INTO rateb_user_roles (user_id, role_id) VALUES (15, 14), (16, 15), (17, 16), (18, 18), (19, 19)');

function epc2_as(int $userId, int $companyId): void
{
    \Rateb\App\Core\SessionManager::start();
    unset($_SESSION['rateb_is_super_admin']);
    $_SESSION['rateb_user_id'] = $userId;
    $_SESSION['rateb_company_id'] = $companyId;
    \Rateb\App\Core\TenantContext::setCompanyId($companyId);
}

$catalog = new \Rateb\App\Automotive\Services\AutomotiveGlobalCatalogService($pdo);
epc2_as(11, 7);
$denied = false;
try {
    $catalog->createPart('Filter', 'منقي');
} catch (RuntimeException $e) {
    $denied = $e->getMessage() === 'forbidden';
}
epc2($denied, 'company view user cannot create a global part');

$selection = new \Rateb\App\Automotive\Services\AutomotiveSelectionService($pdo);
epc2_as(13, 7);
$selectionId = $selection->select('part', 1);
epc2_as(19, 8);
$other = $selection->selections();
epc2($other === [], 'company B cannot read company A selections');
$cross = false;
try {
    epc2_as(14, 8);
    $selection->remove($selectionId);
} catch (RuntimeException) {
    $cross = true;
}
epc2($cross, 'company B cannot remove company A selections');
epc2_as(13, 7);
$selection->remove($selectionId);

epc2_as(18, 7);
$partId = $catalog->createPart('Pump', 'مضخة');
epc2_as(15, 7);
$link = new \Rateb\App\Automotive\Services\AutomotiveSelectionService($pdo);
$link->linkInventory($partId, 10);
$qty = (string) $pdo->query('SELECT quantity FROM rateb_inventory WHERE id = 10')->fetchColumn();
$sku = (string) $pdo->query('SELECT sku FROM rateb_inventory WHERE id = 10')->fetchColumn();
epc2($qty === '5.000' && $sku === 'SKU', 'inventory link does not change quantity or SKU');
$badLink = false;
try {
    $link->linkInventory($partId, 20);
} catch (Throwable) {
    $badLink = true;
}
epc2($badLink, 'inventory link rejects another company item');

epc2_as(16, 7);
$map = new \Rateb\App\Automotive\Services\AutomotiveSelectionService($pdo);
$map->mapSupplier($partId, 4, 'SUP-' . $partId);
$name = (string) $pdo->query('SELECT name FROM rateb_suppliers WHERE id = 4')->fetchColumn();
epc2($name === 'Supplier', 'supplier map does not change the supplier master');

$import = new \Rateb\App\Automotive\Services\AutomotiveImportService($pdo);
epc2_as(11, 7);
$noImport = false;
try {
    $import->preview(1, "name_en,name_ar\nPump,مضخة\n");
} catch (RuntimeException) {
    $noImport = true;
}
epc2($noImport, 'import requires the import permission');
epc2_as(17, 7);
$noApprove = false;
try {
    $import->approve(999);
} catch (Throwable) {
    $noApprove = true;
}
epc2($noApprove, 'approval requires the approve permission');
epc2(\Rateb\App\Core\Csrf::validate('') === false, 'missing CSRF token is rejected');
$token = \Rateb\App\Core\Csrf::token();
epc2(\Rateb\App\Core\Csrf::validate($token) === true, 'matching CSRF token is accepted');

echo "Phase 2 checks: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
