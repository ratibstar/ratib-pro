<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/env/agency_resolver.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/env/agency_resolver.php`.
 */
/**
 * Resolve agency config from control_agencies by HTTP_HOST.
 * When a new agency visits their site_url (e.g. https://newagency.rateb.sa),
 * we look up their DB credentials and use them - no manual env file needed.
 *
 * @param string $host HTTP_HOST (e.g. newagency.rateb.sa)
 * @return bool true if agency found and DB_* defined, false otherwise
 */
function resolve_agency_by_host($host) {
    if (empty($host) || defined('DB_NAME')) {
        return false;
    }
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'agency_lookup.php';
    $host = rateb_normalize_http_host((string) $host);
    $row = rateb_lookup_agency_by_host($host);
    if ($row === null) {
        return false;
    }
    rateb_apply_agency_pro_constants($row, $host);
    rateb_apply_agency_erp_constants($row);

    return true;
}
