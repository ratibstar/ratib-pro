<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Responses;

final class ApiEnvelope
{
    /**
     * @param mixed $data
     * @param array<string, mixed> $meta
     * @param list<array<string, mixed>> $errors
     * @param array<string, string> $headers
     */
    public static function success($data, array $meta = [], array $errors = [], int $status = 200, array $headers = []): void
    {
        \Rateb\PlatformCatalog\Core\Response::json([
            'data' => $data,
            'meta' => $meta,
            'errors' => $errors,
        ], $status, $headers);
    }

    /**
     * @param list<array<string, mixed>> $errors
     */
    public static function error(array $errors, int $status = 400, array $meta = [], array $headers = []): void
    {
        \Rateb\PlatformCatalog\Core\Response::json([
            'data' => null,
            'meta' => $meta,
            'errors' => $errors,
        ], $status, $headers);
    }
}
