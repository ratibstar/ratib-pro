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

        $tenantId = (int) ($_SESSION['rcc_tenant_id'] ?? 1);
        $agentId = (int) ($_SESSION['rcc_agent_id'] ?? 0);
        if ($agentId < 1) {
            $agentId = $this->resolveAgentForControlUser($tenantId);
        }
        if ($agentId < 1) {
            return false;
        }

        $permissions = ['rcc.access', 'rcc.agent.desktop', 'rcc.inbox.manage', 'rcc.calls.manage'];
        if (!empty($_SESSION['control_is_admin'])) {
            $permissions[] = 'rcc.admin.settings';
            $permissions[] = 'rcc.supervisor.dashboard';
            $permissions[] = 'rcc.reports.view';
            $permissions[] = 'rcc.reports.export';
        }

        AuthContext::set($tenantId, $agentId, null, $permissions);
        return true;
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
