<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$index = __DIR__ . '/index.php';
$raw = is_file($index) ? (string) file_get_contents($index) : '';
echo 'index_sha12=' . ($raw !== '' ? substr(hash('sha256', $raw), 0, 12) : 'missing') . PHP_EOL;
echo 'has_x_rateb_erp_build=' . (str_contains($raw, 'X-Rateb-Erp-Build') ? 'yes' : 'no') . PHP_EOL;
echo 'has_device_registry_degraded=' . (str_contains($raw, 'device_registry') || str_contains($raw, 'degraded') ? 'yes' : 'no') . PHP_EOL;
echo 'opcache_enabled=' . (function_exists('opcache_get_status') ? 'yes' : 'no') . PHP_EOL;
if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    echo 'opcache_restart_pending=' . (!empty($st['restart_pending']) ? 'yes' : 'no') . PHP_EOL;
}
echo 'probe_ok' . PHP_EOL;
