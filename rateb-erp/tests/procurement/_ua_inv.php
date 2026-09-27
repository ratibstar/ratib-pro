<?php
declare(strict_types=1);
$root = "/home/admin/domains/rateb.sa/public_html/rateb-erp";
define("RATEB_ROOT", $root);
define("RATEB_ENV_NO_SESSION", true);
require_once $root . "/app/Core/Bootstrap.php";
\Rateb\App\Core\Bootstrap::init($root);
$pdo = \Rateb\App\Core\Database::connection();
$n = (int) $pdo->query("SELECT COUNT(*) FROM rateb_inventory WHERE company_id=26")->fetchColumn();
echo "inv_count=$n\n";
$rows = $pdo->query("SELECT id, item_name, sku FROM rateb_inventory WHERE company_id=26 ORDER BY id ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
