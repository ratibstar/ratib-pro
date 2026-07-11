<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/**
 * Phase 13 — Normalized delta cursor: updated_at|primary_key
 * Legacy formats accepted on parse only.
 */
final class OfflineDeltaCursorCodec
{
    /**
     * @return array{0: int, 1: string} [id, updated_at]
     */
    public static function parse(?string $token): array
    {
        $token = trim((string) $token);
        if ($token === '') {
            return [0, ''];
        }

        if (!str_contains($token, '|')) {
            // Legacy bare id
            return [max(0, (int) $token), ''];
        }

        $parts = explode('|', $token, 2);
        $left = trim((string) ($parts[0] ?? ''));
        $right = trim((string) ($parts[1] ?? ''));

        // Normalized: updated_at|id  (updated_at contains non-digits / dashes / spaces / colons)
        if ($right !== '' && ctype_digit($right)) {
            return [max(0, (int) $right), $left];
        }

        // Legacy supplier: id|updated_at
        if ($left !== '' && ctype_digit($left)) {
            return [max(0, (int) $left), $right];
        }

        return [max(0, (int) $right), $left];
    }

    public static function encode(int $id, string $updatedAt): string
    {
        $id = max(0, $id);
        $updatedAt = trim($updatedAt);
        if ($updatedAt !== '') {
            return $updatedAt . '|' . $id;
        }

        // Fallback when timestamp missing — still include pipe for new responses when possible
        return '0|' . $id;
    }

    public static function isInactiveStatus(string $status, ?int $isActive = null): bool
    {
        if ($isActive !== null) {
            return $isActive === 0;
        }
        $s = strtolower(trim($status));

        return in_array($s, ['inactive', 'disabled', 'deleted', 'terminated', 'archived', '0'], true);
    }
}
