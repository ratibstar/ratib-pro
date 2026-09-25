<?php
declare(strict_types=1);

namespace Rateb\App\CustomerPortal;

/**
 * Customer Portal bearer tokens: random, returned once, stored as SHA-256 only.
 */
final class CustomerPortalTokenService
{
    public const TOKEN_PREFIX = 'rcp_';
    public const TTL_SECONDS = 30 * 86400;
    public const TOUCH_INTERVAL_SECONDS = 300;

    public const REVOKE_LOGOUT = 'logout';
    public const REVOKE_PASSWORD_CHANGE = 'password_change';
    public const REVOKE_ACCOUNT_DISABLED = 'account_disabled';

    private CustomerPortalStore $store;

    public function __construct(?CustomerPortalStore $store = null)
    {
        $this->store = $store ?? new PdoCustomerPortalStore();
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function isWellFormed(string $plainToken): bool
    {
        return (bool) preg_match('/^' . self::TOKEN_PREFIX . '[a-f0-9]{64}$/', $plainToken);
    }

    /** @return array{id: int, token: string, expires_at: string} */
    public function issue(int $companyId, int $accountId, int $now): array
    {
        if ($companyId < 1 || $accountId < 1) {
            throw new \InvalidArgumentException('company and account are required');
        }
        $plain = self::TOKEN_PREFIX . bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', $now + self::TTL_SECONDS);
        $id = $this->store->insertToken($companyId, $accountId, self::hashToken($plain), $expiresAt);

        return ['id' => $id, 'token' => $plain, 'expires_at' => $expiresAt];
    }

    /**
     * Active (not revoked, not expired) token row for a plaintext token, or null.
     *
     * @return array<string, mixed>|null
     */
    public function findActive(string $plainToken, int $now): ?array
    {
        if (!self::isWellFormed($plainToken)) {
            return null;
        }
        $hash = self::hashToken($plainToken);
        $row = $this->store->findTokenByHash($hash);
        if ($row === null || !hash_equals((string) ($row['token_hash'] ?? ''), $hash)) {
            return null;
        }
        if (!empty($row['revoked_at'])) {
            return null;
        }
        $expires = strtotime((string) ($row['expires_at'] ?? ''));
        if ($expires === false || $expires <= $now) {
            return null;
        }

        return $row;
    }

    /** @param array<string, mixed> $tokenRow */
    public function touchIfStale(array $tokenRow, int $now): void
    {
        $last = strtotime((string) ($tokenRow['last_used_at'] ?? ''));
        if ($last !== false && ($now - $last) < self::TOUCH_INTERVAL_SECONDS) {
            return;
        }
        $this->store->touchToken((int) $tokenRow['id'], date('Y-m-d H:i:s', $now));
    }

    public function revoke(int $tokenId, string $reason, int $now): void
    {
        $this->store->revokeToken($tokenId, $reason, date('Y-m-d H:i:s', $now));
    }

    public function revokeAllForAccount(int $companyId, int $accountId, string $reason, int $now): int
    {
        return $this->store->revokeAllForAccount($companyId, $accountId, $reason, date('Y-m-d H:i:s', $now));
    }
}
