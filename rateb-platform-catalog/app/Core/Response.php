<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Core;

final class Response
{
    /** @var null|callable(array<string, mixed>|list<mixed>, int, array<string, string>): void */
    private static $beforeExitCallback = null;

    /**
     * @param null|callable(array<string, mixed>|list<mixed>, int, array<string, string>): void $callback
     */
    public static function onBeforeExit(?callable $callback): void
    {
        self::$beforeExitCallback = $callback;
    }

    public static function resetBeforeExit(): void
    {
        self::$beforeExitCallback = null;
    }

    /**
     * @param array<string, mixed>|list<mixed> $payload
     */
    public static function json(array $payload, int $status = 200, array $headers = []): void
    {
        if (self::$beforeExitCallback !== null) {
            (self::$beforeExitCallback)($payload, $status, $headers);
            self::$beforeExitCallback = null;
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            foreach ($headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (defined('RATEB_CATALOG_TESTING') && RATEB_CATALOG_TESTING) {
            throw new ResponseSentException($payload, $status, $headers);
        }

        exit;
    }
}
