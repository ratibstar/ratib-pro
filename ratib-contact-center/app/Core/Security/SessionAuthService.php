<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Core\Security;

use Ratib\ContactCenter\App\Core\Database;

final class SessionAuthService
{
    public function authenticateSessionToken(string $token): bool
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 32) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'SELECT s.user_id, s.tenant_id, s.agent_id, s.expires_at, u.status AS user_status
             FROM rcc_sessions s
             INNER JOIN rcc_users u ON u.id = s.user_id
             WHERE s.session_token = :tok LIMIT 1'
        );
        $stmt->execute(['tok' => hash('sha256', $token)]);
        $row = $stmt->fetch();
        if ($row === false) {
            return false;
        }

        if ((string) $row['user_status'] !== 'active') {
            return false;
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            return false;
        }

        $permissions = $this->permissionsForUser((int) $row['user_id'], (int) $row['tenant_id']);
        AuthContext::set(
            (int) $row['tenant_id'],
            (int) $row['agent_id'],
            (int) $row['user_id'],
            $permissions,
            $token
        );

        $upd = Database::connection()->prepare(
            'UPDATE rcc_sessions SET last_activity_at = NOW() WHERE session_token = :tok'
        );
        $upd->execute(['tok' => hash('sha256', $token)]);

        return true;
    }

    public function authenticateApiToken(string $bearer): bool
    {
        $bearer = trim($bearer);
        if ($bearer === '') {
            return false;
        }

        $hash = hash('sha256', $bearer);
        $stmt = Database::connection()->prepare(
            'SELECT tenant_id, user_id, agent_id, scopes, expires_at
             FROM rcc_api_tokens WHERE token_hash = :h LIMIT 1'
        );
        $stmt->execute(['h' => $hash]);
        $row = $stmt->fetch();
        if ($row === false) {
            return false;
        }

        if ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) < time()) {
            return false;
        }

        $agentId = (int) ($row['agent_id'] ?? 0);
        if ($agentId < 1 && $row['user_id'] !== null) {
            $agentId = $this->agentIdForUser((int) $row['user_id'], (int) $row['tenant_id']);
        }
        if ($agentId < 1) {
            return false;
        }

        $scopes = $row['scopes'] ?? '[]';
        if (is_string($scopes)) {
            $decoded = json_decode($scopes, true);
            $permissions = is_array($decoded) ? array_map('strval', $decoded) : [];
        } else {
            $permissions = is_array($scopes) ? $scopes : [];
        }

        AuthContext::set((int) $row['tenant_id'], $agentId, isset($row['user_id']) ? (int) $row['user_id'] : null, $permissions);

        $upd = Database::connection()->prepare('UPDATE rcc_api_tokens SET last_used_at = NOW() WHERE token_hash = :h');
        $upd->execute(['h' => $hash]);

        return true;
    }

    /** @return list<string> */
    private function permissionsForUser(int $userId, int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT p.slug
             FROM rcc_permissions p
             INNER JOIN rcc_role_permissions rp ON rp.permission_id = p.id
             INNER JOIN rcc_user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :uid AND ur.tenant_id = :tid'
        );
        $stmt->execute(['uid' => $userId, 'tid' => $tenantId]);
        return array_map(static fn ($r) => (string) $r['slug'], $stmt->fetchAll() ?: []);
    }

    private function agentIdForUser(int $userId, int $tenantId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM rcc_agents WHERE user_id = :uid AND tenant_id = :tid AND status = \'active\' LIMIT 1'
        );
        $stmt->execute(['uid' => $userId, 'tid' => $tenantId]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : 0;
    }
}
