<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Portal;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Security\PortalAuthContext;

final class CustomerPortalAuthService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    /** @return array{ok:bool,token?:string,tenant_id?:int,error?:string} */
    public function login(int $tenantId, string $email, string $password): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, contact_id, password_hash, status FROM rcc_portal_users WHERE tenant_id = :tid AND email = :email LIMIT 1"
        );
        $stmt->execute(['tid' => $tenantId, 'email' => strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user || $user['status'] !== 'active' || !password_verify($password, (string) $user['password_hash'])) {
            return ['ok' => false, 'error' => 'Invalid credentials'];
        }
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        Database::connection()->prepare(
            'INSERT INTO rcc_portal_sessions (tenant_id, portal_user_id, session_token, ip_address, user_agent, expires_at)
             VALUES (:tid, :uid, :tok, :ip, :ua, :exp)'
        )->execute([
            'tid' => $tenantId,
            'uid' => $user['id'],
            'tok' => $token,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            'exp' => $expires,
        ]);
        Database::connection()->prepare('UPDATE rcc_portal_users SET last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => $user['id']]);
        $this->audit->log($tenantId, 'portal.login', null, 'portal_user', (int) $user['id']);
        return ['ok' => true, 'token' => $token, 'tenant_id' => $tenantId, 'contact_id' => (int) $user['contact_id']];
    }

    public function authenticateToken(string $token): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.tenant_id, s.portal_user_id, u.contact_id
             FROM rcc_portal_sessions s
             INNER JOIN rcc_portal_users u ON u.id = s.portal_user_id AND u.tenant_id = s.tenant_id
             WHERE s.session_token = :tok AND s.revoked_at IS NULL AND s.expires_at > NOW() AND u.status = \'active\'
             LIMIT 1'
        );
        $stmt->execute(['tok' => $token]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        PortalAuthContext::set((int) $row['tenant_id'], (int) $row['portal_user_id'], (int) $row['contact_id'], $token);
        return true;
    }

    public function logout(string $token): void
    {
        Database::connection()->prepare(
            'UPDATE rcc_portal_sessions SET revoked_at = NOW() WHERE session_token = :tok'
        )->execute(['tok' => $token]);
        PortalAuthContext::clear();
    }

    public function register(int $tenantId, int $contactId, string $email, string $password): array
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        Database::connection()->prepare(
            'INSERT INTO rcc_portal_users (tenant_id, contact_id, email, password_hash) VALUES (:tid, :cid, :email, :hash)'
        )->execute(['tid' => $tenantId, 'cid' => $contactId, 'email' => strtolower(trim($email)), 'hash' => $hash]);
        $id = (int) Database::connection()->lastInsertId();
        $this->audit->log($tenantId, 'portal.user.registered', null, 'portal_user', $id);
        return ['portal_user_id' => $id];
    }
}
