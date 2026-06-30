<?php
declare(strict_types=1);
/**
 * Legacy entry — redirects to Control Panel sync page (avoids agency URL gate on /pages/).
 */
header('Cache-Control: no-store');

$cpSync = '/control-panel/pages/control/sync-test-domain.php';
if (isset($_GET['control']) && (string) $_GET['control'] === '1') {
    $cpSync .= '?control=1';
}

$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if ($host !== '') {
    header('Location: ' . $scheme . '://' . $host . $cpSync, true, 302);
    exit;
}

header('Location: ' . $cpSync, true, 302);
exit;
