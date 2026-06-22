<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Core\Security;

final class ApiAuthMiddleware
{
    private SessionAuthService $sessions;

    public function __construct(?SessionAuthService $sessions = null)
    {
        $this->sessions = $sessions ?? new SessionAuthService();
    }

    /**
     * @param list<string> $requiredPermissions
     */
    public function authenticate(array $requiredPermissions = ['rcc.access']): void
    {
        AuthContext::clear();

        if ($this->tryBearer()) {
            $this->assertPermissions($requiredPermissions);
            return;
        }

        if ($this->trySessionCookie()) {
            $this->assertPermissions($requiredPermissions);
            return;
        }

        if ($this->tryControlPanelBridge()) {
            $this->assertPermissions($requiredPermissions);
            return;
        }

        throw new \RuntimeException('Authentication required.', 401);
    }

    /** @param list<string> $requiredPermissions */
    private function assertPermissions(array $requiredPermissions): void
    {
        foreach ($requiredPermissions as $perm) {
            if (!AuthContext::can($perm)) {
                throw new \RuntimeException('Permission denied.', 403);
            }
        }
    }

    private function tryBearer(): bool
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        if (stripos($header, 'Bearer ') !== 0) {
            $apiKey = (string) ($_SERVER['HTTP_X_RCC_API_TOKEN'] ?? getenv('EXTERNAL_API_TOKEN') ?: '');
            if ($apiKey !== '' && isset($_GET['api_token']) && hash_equals($apiKey, (string) $_GET['api_token'])) {
                return $this->sessions->authenticateApiToken($apiKey);
            }
            return false;
        }
        $token = trim(substr($header, 7));
        return $this->sessions->authenticateApiToken($token);
    }

    private function trySessionCookie(): bool
    {
        $token = (string) ($_COOKIE['rcc_session'] ?? '');
        if ($token === '') {
            $token = (string) ($_SERVER['HTTP_X_RCC_SESSION'] ?? '');
        }
        if ($token === '') {
            return false;
        }
        return $this->sessions->authenticateSessionToken($token);
    }

    private function tryControlPanelBridge(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['control_logged_in'])) {
            return false;
        }

        $tenantId = $this->resolveTenantForControlUser();
        if ($tenantId < 1) {
            return false;
        }

        $agentId = (int) ($_SESSION['rcc_agent_id'] ?? 0);
        if ($agentId < 1) {
            $agentId = $this->resolveAgentForControlUser($tenantId);
        }

        $userId = $this->resolveRccUserIdForControlUser($tenantId);
        $permissions = $this->permissionsForControlBridge($tenantId, $userId);

        if ($agentId < 1
            && !in_array('rcc.ops.view', $permissions, true)
            && !in_array('rcc.supervisor.view', $permissions, true)
            && !in_array('rcc.supervisor.dashboard', $permissions, true)
        ) {
            return false;
        }

        AuthContext::set($tenantId, max(0, $agentId), $userId, $permissions);
        return true;
    }

    /** @return list<string> */
    private function permissionsForControlBridge(int $tenantId, ?int $userId): array
    {
        if (!empty($_SESSION['control_is_admin'])) {
            return $this->adminPermissionBundle();
        }

        if ($userId !== null && $userId > 0) {
            $fromDb = $this->permissionsForUser($userId, $tenantId);
            if ($fromDb !== []) {
                return array_values(array_unique(array_merge(['rcc.access'], $fromDb)));
            }
        }

        return ['rcc.access', 'rcc.agent.desktop', 'rcc.inbox.manage', 'rcc.calls.manage'];
    }

    /** @return list<string> */
    private function adminPermissionBundle(): array
    {
        return [
            'rcc.access',
            'rcc.agent.desktop',
            'rcc.inbox.manage',
            'rcc.calls.manage',
            'rcc.admin.settings',
            'rcc.supervisor.dashboard',
            'rcc.supervisor.view',
            'rcc.supervisor.wallboard',
            'rcc.supervisor.queues',
            'rcc.supervisor.agents',
            'rcc.supervisor.sla',
            'rcc.supervisor.wfm',
            'rcc.supervisor.shifts',
            'rcc.supervisor.attendance',
            'rcc.supervisor.breaks',
            'rcc.supervisor.alerts',
            'rcc.supervisor.reports',
            'rcc.reports.view',
            'rcc.reports.export',
            'rcc.ops.view',
            'rcc.ops.pbx',
            'rcc.ops.sip',
            'rcc.ops.queues',
            'rcc.ops.ivr',
            'rcc.ops.agents',
            'rcc.ops.diagnostics',
            'rcc.ops.hub',
            'rcc.ops.golive',
            'rcc.tenants.manage',
        ];
    }

    /** @return list<string> */
    private function permissionsForUser(int $userId, int $tenantId): array
    {
        try {
            $stmt = \Ratib\ContactCenter\App\Core\Database::connection()->prepare(
                'SELECT DISTINCT p.slug
                 FROM rcc_permissions p
                 INNER JOIN rcc_role_permissions rp ON rp.permission_id = p.id
                 INNER JOIN rcc_user_roles ur ON ur.role_id = rp.role_id
                 WHERE ur.user_id = :uid AND ur.tenant_id = :tid'
            );
            $stmt->execute(['uid' => $userId, 'tid' => $tenantId]);
            return array_map(static fn ($r) => (string) $r['slug'], $stmt->fetchAll() ?: []);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function resolveTenantForControlUser(): int
    {
        $fromSession = (int) ($_SESSION['rcc_tenant_id'] ?? 0);
        if ($fromSession > 0) {
            return $fromSession;
        }
        try {
            $stmt = \Ratib\ContactCenter\App\Core\Database::connection()->query(
                "SELECT id FROM rcc_tenants WHERE status = 'active' ORDER BY id ASC LIMIT 1"
            );
            $id = $stmt ? $stmt->fetchColumn() : false;
            return $id !== false ? (int) $id : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function resolveRccUserIdForControlUser(int $tenantId): ?int
    {
        $email = (string) ($_SESSION['control_user_email'] ?? $_SESSION['control_email'] ?? '');
        if ($email === '') {
            return null;
        }
        try {
            $stmt = \Ratib\ContactCenter\App\Core\Database::connection()->prepare(
                'SELECT id FROM rcc_users WHERE tenant_id = :tid AND email = :email AND status = \'active\' LIMIT 1'
            );
            $stmt->execute(['tid' => $tenantId, 'email' => $email]);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveAgentForControlUser(int $tenantId): int
    {
        $sessionAgentId = (int) ($_SESSION['rcc_agent_id'] ?? 0);
        if ($sessionAgentId > 0) {
            return $sessionAgentId;
        }

        $email = (string) ($_SESSION['control_user_email'] ?? $_SESSION['control_email'] ?? '');
        if ($email === '') {
            return 0;
        }
        try {
            $stmt = \Ratib\ContactCenter\App\Core\Database::connection()->prepare(
                'SELECT a.id FROM rcc_agents a
                 INNER JOIN rcc_users u ON u.id = a.user_id AND u.tenant_id = a.tenant_id
                 WHERE a.tenant_id = :tid AND u.email = :email AND a.status = \'active\'
                 LIMIT 1'
            );
            $stmt->execute(['tid' => $tenantId, 'email' => $email]);
            $id = $stmt->fetchColumn();
            return $id !== false ? (int) $id : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
