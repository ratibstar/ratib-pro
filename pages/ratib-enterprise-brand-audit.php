<?php
/**
 * Web wrapper — enterprise brand audit output.
 * https://out.ratib.sa/pages/ratib-enterprise-brand-audit.php?key=ratib-deploy-sync-2026
 */
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
if ($key !== 'ratib-deploy-sync-2026') {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

$script = dirname(__DIR__) . '/scripts/rateb-enterprise-brand-audit.php';
if (!is_file($script)) {
    echo "audit_script_missing\n";
    exit;
}

passthru('php ' . escapeshellarg($script));
