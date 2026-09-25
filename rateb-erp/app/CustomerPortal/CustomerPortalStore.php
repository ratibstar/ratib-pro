<?php
declare(strict_types=1);

namespace Rateb\App\CustomerPortal;

/**
 * Persistence boundary for Customer Portal app authentication.
 * Never touches rateb_users / rateb_api_tokens (staff identity).
 */
interface CustomerPortalStore
{
    public function currentDatabaseName(): string;

    /** @return list<array<string, mixed>> at most $limit rows (id, name, slug, status) */
    public function listCompanies(int $limit): array;

    /** @return array<string, mixed>|null */
    public function findCompanyBySlug(string $slug): ?array;

    /** @return array<string, mixed>|null */
    public function findCompanyById(int $companyId): ?array;

    public function hasValidSubscription(int $companyId, int $now): bool;

    /** @return array<string, mixed>|null */
    public function findAccountByEmail(int $companyId, string $portalType, string $email): ?array;

    /** @return array<string, mixed>|null */
    public function findAccountById(int $accountId): ?array;

    public function insertToken(int $companyId, int $accountId, string $tokenHash, string $expiresAt): int;

    /** @return array<string, mixed>|null */
    public function findTokenByHash(string $tokenHash): ?array;

    public function touchToken(int $tokenId, string $usedAt): void;

    public function revokeToken(int $tokenId, string $reason, string $revokedAt): void;

    public function revokeAllForAccount(int $companyId, int $accountId, string $reason, string $revokedAt): int;
}
