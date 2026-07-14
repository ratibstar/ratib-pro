<?php
declare(strict_types=1);
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_URI'] = '/rateb-erp/public/admin';
$_SERVER['DOCUMENT_ROOT'] = '/home/admin/domains/rateb.sa/public_html';
define('RATEB_ROOT', '/home/admin/domains/rateb.sa/public_html/rateb-erp');
require RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
$pdo = \Rateb\App\Core\Database::connection();
$st = $pdo->prepare('SELECT * FROM rateb_users WHERE email = ? LIMIT 1');
$st->execute(['admin@rateb.sa']);
$u = $st->fetch(PDO::FETCH_ASSOC);
\Rateb\App\Core\Auth::loginUser($u);
if (function_exists('rateb_adopt_ops_company_id')) {
    rateb_adopt_ops_company_id(22);
}
$name = session_name();
$id = session_id();
session_write_close();
file_put_contents('/tmp/rateb-admin.cookie', "rateb.sa\tFALSE\t/\tTRUE\t0\t{$name}\t{$id}\n");
echo "{$name}={$id}\n";
