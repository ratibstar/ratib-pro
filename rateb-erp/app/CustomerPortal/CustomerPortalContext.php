<?php
declare(strict_types=1);

namespace Rateb\App\CustomerPortal;

/**
 * Request-scoped identity of an authenticated Customer Portal account.
 * Deliberately separate from TenantContext::apiUserId() (staff identity).
 */
final class CustomerPortalContext
{
    /** @var array{account: array<string, mixed>, company: array<string, mixed>, token_id: int, token_expires_at: string, mode: string}|null */
    private static ?array $current = null;

    /** @param array{account: array<string, mixed>, company: array<string, mixed>, token_id: int, token_expires_at: string, mode: string} $context */
    public static function set(array $context): void
    {
        self::$current = $context;
    }

    /** @return array{account: array<string, mixed>, company: array<string, mixed>, token_id: int, token_expires_at: string, mode: string}|null */
    public static function current(): ?array
    {
        return self::$current;
    }

    public static function accountId(): int
    {
        return (int) (self::$current['account']['id'] ?? 0);
    }

    public static function companyId(): int
    {
        return (int) (self::$current['company']['id'] ?? 0);
    }

    public static function tokenId(): int
    {
        return (int) (self::$current['token_id'] ?? 0);
    }

    public static function clear(): void
    {
        self::$current = null;
    }
}
