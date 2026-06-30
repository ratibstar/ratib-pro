<?php
/**
 * EN: Handles application behavior in `index.php`.
 * AR: يدير سلوك جزء من التطبيق في `index.php`.
 */
/**
 * Main Entry Point - RATEB
 * Redirects to login page or dashboard if already logged in
 */
require_once __DIR__ . '/includes/config.php';

$getControl = !empty($_GET['control']) && (string) $_GET['control'] === '1';
$getAgencyId = isset($_GET['agency_id']) && ctype_digit((string) $_GET['agency_id'])
    ? (int) $_GET['agency_id']
    : 0;
$singleUrlMode = defined('SINGLE_URL_MODE') && SINGLE_URL_MODE;

$host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$isMainSa = in_array($host, ['rateb.sa', 'www.rateb.sa'], true);
$isDedicatedAgencyHost = !$isMainSa
    && defined('SITE_URL')
    && function_exists('rateb_url_matches_agency_site')
    && rateb_url_matches_agency_site(SITE_URL);

// Control Panel "Open" → login (or dashboard when SSO already established in config.php).
if ($getControl && $getAgencyId > 0 && $singleUrlMode) {
    if (function_exists('rateb_program_session_is_valid_user') && rateb_program_session_is_valid_user()) {
        header('Location: ' . rateb_country_dashboard_url($getAgencyId));
        exit();
    }
    header('Location: ' . pageUrl('login.php') . '?control=1&agency_id=' . $getAgencyId);
    exit();
}

if (function_exists('rateb_program_session_is_valid_user') && rateb_program_session_is_valid_user()) {
    header('Location: ' . rateb_country_dashboard_url((int)($_SESSION['agency_id'] ?? 0)));
    exit();
}

// Agency / country subdomain: program login at root (not marketing CMS).
if ($isDedicatedAgencyHost) {
    header('Location: ' . pageUrl('login.php'));
    exit();
}

if ($isMainSa) {
    header('Location: /', true, 302);
    exit();
}
require_once __DIR__ . '/includes/rateb-public-base-url.php';
header('Location: ' . rateb_public_marketing_home_url());
exit(); 