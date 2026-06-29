<?php
/**
 * Legacy alias — redirects to standalone register-agency page.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/rateb-public-base-url.php';

$plan = trim((string) ($_GET['plan'] ?? 'gold')) ?: 'gold';
$years = isset($_GET['years']) ? (int) $_GET['years'] : 1;
$extra = $_GET;
unset($extra['open']);

header('Location: ' . rateb_public_agency_register_url('', $plan, $years, $extra), true, 302);
exit;
