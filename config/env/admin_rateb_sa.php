<?php
/**
 * admin.rateb.sa — bind Pro + ERP databases from control_agencies (agency #34 / تجربة 2).
 */
declare(strict_types=1);

if (defined('DB_NAME')) {
    return;
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'agency_resolver.php';
if (!resolve_agency_by_host('admin.rateb.sa')) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'erp_agency_resolver.php';
    resolve_agency_erp_by_host('admin.rateb.sa');
}
