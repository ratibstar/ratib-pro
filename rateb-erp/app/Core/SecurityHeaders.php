<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class SecurityHeaders
{
    private static bool $sent = false;

    public static function send(): void
    {
        if (self::$sent || headers_sent()) {
            return;
        }
        self::$sent = true;

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "object-src 'none'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://www.googletagmanager.com https://www.google-analytics.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: https://cdn.jsdelivr.net https://fonts.gstatic.com",
            "connect-src 'self' https://www.google-analytics.com https://www.googletagmanager.com",
            "media-src 'self' blob:",
        ]);
        header('Content-Security-Policy: ' . $csp);
    }

    /** Headers for potentially active media (SVG) — force download, no script execution. */
    public static function sendRestrictedMediaHeaders(string $mime): void
    {
        self::send();
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('Content-Disposition: attachment');
        header('Content-Type: ' . $mime);
    }
}
