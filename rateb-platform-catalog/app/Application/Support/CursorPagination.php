<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

final class CursorPagination
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param callable(array<string, mixed>): string $cursorEncoder
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public static function paginate(
        array $rows,
        int $limit,
        ?string $cursor,
        callable $cursorEncoder,
        ?callable $cursorDecoder = null
    ): array {
        $limit = max(1, min(200, $limit));
        $offset = 0;
        if ($cursor !== null && $cursor !== '' && $cursorDecoder !== null) {
            $decoded = $cursorDecoder($cursor);
            if (isset($decoded['offset'])) {
                $offset = max(0, (int) $decoded['offset']);
            }
        }

        $slice = array_slice($rows, $offset, $limit + 1);
        $hasMore = count($slice) > $limit;
        if ($hasMore) {
            array_pop($slice);
        }

        $nextCursor = null;
        if ($hasMore && $slice !== []) {
            $last = $slice[array_key_last($slice)];
            $nextCursor = $cursorEncoder(['offset' => $offset + $limit, 'last' => $last]);
        }

        return [
            'items' => $slice,
            'meta' => [
                'cursor' => $nextCursor,
                'has_more' => $hasMore,
                'count' => count($slice),
                'limit' => $limit,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('Unable to encode cursor');
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(string $cursor): array
    {
        $padded = strtr($cursor, '-_', '+/');
        $mod = strlen($padded) % 4;
        if ($mod > 0) {
            $padded .= str_repeat('=', 4 - $mod);
        }
        $json = base64_decode($padded, true);
        if ($json === false) {
            throw new \InvalidArgumentException('Invalid cursor');
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
