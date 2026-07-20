<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class Response
{
    public static function json(array $payload, int $status = 200): void
    {
        if (class_exists(SecurityHeaders::class)) {
            SecurityHeaders::send();
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url, int $status = 302): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }
}
