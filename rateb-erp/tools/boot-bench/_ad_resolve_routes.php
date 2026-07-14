<?php
$_SERVER['HTTP_HOST'] = 'rateb.sa';
$_SERVER['HTTPS'] = 'on';
require '/home/admin/domains/rateb.sa/public_html/rateb-erp/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init('/home/admin/domains/rateb.sa/public_html/rateb-erp');
foreach (['hr', 'crm', 'inventory', 'accounting', 'purchase-requests'] as $p) {
    echo $p . ' => ' . rateb_app_route($p) . "\n";
}
