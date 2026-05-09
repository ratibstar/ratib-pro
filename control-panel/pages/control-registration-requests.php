<?php
/**
 * Legacy compatibility route for registration requests page.
 * Canonical page is `control/registration-requests.php`.
 */
require_once __DIR__ . '/../includes/config.php';

$params = $_GET;
unset($params['embedded']);
$target = pageUrl('control/registration-requests.php');
$qs = http_build_query($params);
header('Location: ' . $target . ($qs !== '' ? ('?' . $qs) : ''), true, 302);
exit;

