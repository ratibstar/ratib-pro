<?php
/**
 * Quick health check: /control-panel/cp-ping.php (no auth).
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

echo "cp-ping ok PHP " . PHP_VERSION . "\n";

$compat = dirname(__DIR__) . '/includes/ratib-php74-compat.php';
echo 'compat_file=' . (is_file($compat) ? 'yes' : 'no') . "\n";
if (is_file($compat)) {
    require_once $compat;
}
echo 'str_contains=' . (function_exists('str_contains') ? 'yes' : 'no') . "\n";

try {
    require_once __DIR__ . '/includes/config.php';
    echo "config_load=ok\n";
    echo 'control_db=' . ((isset($GLOBALS['control_conn']) && $GLOBALS['control_conn'] instanceof mysqli) ? 'yes' : 'no') . "\n";
} catch (Throwable $e) {
    echo 'config_load=fail ' . $e->getMessage() . "\n";
}
