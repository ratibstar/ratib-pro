<?php
header("Content-Type: application/json");
$root = dirname(__DIR__);
require_once $root . "/app/Core/Bootstrap.php";
\Rateb\App\Core\Bootstrap::init($root);
$pdo = \Rateb\App\Core\Database::connection();
$pdo->prepare("UPDATE rateb_mobile_app_configs SET status='active', app_name=:n WHERE company_id=29")->execute(["n"=>"راتب — الموارد البشرية"]);
$q=$pdo->query("SELECT company_id,app_name,status FROM rateb_mobile_app_configs WHERE company_id=29");
echo json_encode($q->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);