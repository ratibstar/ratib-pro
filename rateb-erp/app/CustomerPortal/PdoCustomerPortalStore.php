<?php
declare(strict_types=1);

namespace Rateb\App\CustomerPortal;

use PDO;
use Rateb\App\Core\Database;

final class PdoCustomerPortalStore implements CustomerPortalStore
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    public function currentDatabaseName(): string
    {
        $stmt = $this->pdo->query('SELECT DATABASE()');
        $name = $stmt ? $stmt->fetchColumn() : false;

        return is_string($name) ? $name : '';
    }

    public function listCompanies(int $limit): array
    {
        $limit = max(1, min($limit, 10));
        $stmt = $this->pdo->query('SELECT id, name, slug, status FROM rateb_companies ORDER BY id ASC LIMIT ' . $limit);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return is_array($rows) ? $rows : [];
    }

    public function findCompanyBySlug(string $slug): ?array
    {
        return $this->one('SELECT id, name, slug, status FROM rateb_companies WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
    }

    public function findCompanyById(int $companyId): ?array
    {
        return $this->one('SELECT id, name, slug, status FROM rateb_companies WHERE id = :id LIMIT 1', ['id' => $companyId]);
    }

    public function hasValidSubscription(int $companyId, int $now): bool
    {
        $sub = $this->one(
            'SELECT status, ends_at FROM rateb_subscriptions WHERE company_id = :cid ORDER BY id DESC LIMIT 1',
            ['cid' => $companyId]
        );
        if ($sub === null || !in_array((string) ($sub['status'] ?? ''), ['active', 'trial'], true)) {
            return false;
        }
        $ends = (string) ($sub['ends_at'] ?? '');
        if ($ends === '') {
            return true;
        }
        $endsTs = strtotime($ends);

        return $endsTs !== false && $endsTs >= strtotime('today', $now);
    }

    public function findAccountByEmail(int $companyId, string $portalType, string $email): ?array
    {
        return $this->one(
            'SELECT id, company_id, portal_type, email, password_hash, full_name, status, app_access_approved_at
             FROM rateb_website_portal_users
             WHERE company_id = :cid AND portal_type = :ptype AND email = :email LIMIT 1',
            ['cid' => $companyId, 'ptype' => $portalType, 'email' => $email]
        );
    }

    public function findAccountById(int $accountId): ?array
    {
        return $this->one(
            'SELECT id, company_id, portal_type, email, full_name, status, app_access_approved_at
             FROM rateb_website_portal_users WHERE id = :id LIMIT 1',
            ['id' => $accountId]
        );
    }

    public function insertToken(int $companyId, int $accountId, string $tokenHash, string $expiresAt): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rateb_customer_portal_tokens (company_id, portal_user_id, token_hash, expires_at)
             VALUES (:cid, :uid, :hash, :exp)'
        );
        $stmt->execute(['cid' => $companyId, 'uid' => $accountId, 'hash' => $tokenHash, 'exp' => $expiresAt]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findTokenByHash(string $tokenHash): ?array
    {
        return $this->one(
            'SELECT id, company_id, portal_user_id, token_hash, expires_at, last_used_at, revoked_at
             FROM rateb_customer_portal_tokens WHERE token_hash = :hash LIMIT 1',
            ['hash' => $tokenHash]
        );
    }

    public function touchToken(int $tokenId, string $usedAt): void
    {
        $this->pdo->prepare('UPDATE rateb_customer_portal_tokens SET last_used_at = :at WHERE id = :id')
            ->execute(['at' => $usedAt, 'id' => $tokenId]);
    }

    public function revokeToken(int $tokenId, string $reason, string $revokedAt): void
    {
        $this->pdo->prepare(
            'UPDATE rateb_customer_portal_tokens SET revoked_at = :at, revoke_reason = :reason
             WHERE id = :id AND revoked_at IS NULL'
        )->execute(['at' => $revokedAt, 'reason' => $reason, 'id' => $tokenId]);
    }

    public function revokeAllForAccount(int $companyId, int $accountId, string $reason, string $revokedAt): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rateb_customer_portal_tokens SET revoked_at = :at, revoke_reason = :reason
             WHERE company_id = :cid AND portal_user_id = :uid AND revoked_at IS NULL'
        );
        $stmt->execute(['at' => $revokedAt, 'reason' => $reason, 'cid' => $companyId, 'uid' => $accountId]);

        return $stmt->rowCount();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
