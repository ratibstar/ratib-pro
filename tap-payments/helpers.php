<?php
/**
 * EN: Handles application behavior in `tap-payments/helpers.php`.
 * AR: يدير سلوك جزء من التطبيق في `tap-payments/helpers.php`.
 */
/**
 * Tap Payments - Shared Helpers
 * Prevents duplicate function definitions across pay.php, verify.php, etc.
 */

if (!function_exists('calculateTax')) {
    function calculateTax(float $amount, ?float $rate = null): float
    {
        $rate = $rate ?? (defined('TAP_TAX_RATE') ? TAP_TAX_RATE : 0.15);
        return round($amount * $rate, 2);
    }
}

if (!function_exists('buildTapUrl')) {
    function buildTapUrl(string $path, array $params = []): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        if (defined('TAP_LIVE_MODE') && TAP_LIVE_MODE && $scheme !== 'https') {
            $scheme = 'https';
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/tap-payments'));
        $base = rtrim($scriptDir, '/');
        $url = $scheme . '://' . $host . $base . '/' . ltrim($path, '/');
        if (!empty($params)) {
            $url .= (strpos($url, '?') !== false ? '&' : '?') . http_build_query($params);
        }
        return $url;
    }
}
