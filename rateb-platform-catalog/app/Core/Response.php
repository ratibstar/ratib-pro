<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Core;

final class Response
{
    /**
     * @param array<string, mixed>|list<mixed> $payload
     */
    public static function json(array $payload, int $status = 200, array $headers = []): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            foreach ($headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
