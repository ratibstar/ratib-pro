<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/includes/control-api-same-origin-cors.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/includes/control-api-same-origin-cors.php`.
 */
/**
 * Session-protected control APIs: set Access-Control-Allow-Origin only when
 * Origin matches this request's host and port (no wildcard).
 */
if (!function_exists('applyControlApiSameOriginCors')) {
    function applyControlApiSameOriginCors()
    {
        if (headers_sent()) {
            return;
        }
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $httpHostHeader = $_SERVER['HTTP_HOST'] ?? '';
        if ($origin === '' || $httpHostHeader === '') {
            return;
        }
        $parsed = parse_url($origin);
        if (!is_array($parsed) || empty($parsed['host'])) {
            return;
        }
        $originHost = $parsed['host'];
        $scheme = strtolower($parsed['scheme'] ?? 'http');
        $originPort = isset($parsed['port']) ? (int) $parsed['port'] : ($scheme === 'https' ? 443 : 80);

        $serverHost = $httpHostHeader;
        $serverPort = null;
        if (preg_match('/^\[([^\]]+)\](?::(\d+))?$/', $httpHostHeader, $m)) {
            $serverHost = $m[1];
            $serverPort = isset($m[2]) ? (int) $m[2] : null;
        } elseif (strpos($httpHostHeader, ':') !== false && strpos($httpHostHeader, ']') === false) {
            $pos = strrpos($httpHostHeader, ':');
            $maybePort = substr($httpHostHeader, $pos + 1);
            if ($pos > 0 && ctype_digit($maybePort)) {
                $serverHost = substr($httpHostHeader, 0, $pos);
                $serverPort = (int) $maybePort;
            }
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        if ($serverPort === null) {
            $serverPort = $isHttps ? 443 : 80;
        }

        if (strcasecmp($originHost, $serverHost) === 0 && $originPort === $serverPort) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
    }
}
