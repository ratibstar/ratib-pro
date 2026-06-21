<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\WebRTC;

use Ratib\ContactCenter\App\Core\Database;

/**
 * Tenant-isolated SIP/WebRTC credential gateway (signaling config only — no media).
 */
final class SipGateway
{
    /** @return array<string, mixed> */
    public function getCredentialsForAgent(int $tenantId, int $agentId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT e.extension, e.sip_username, e.sip_password_ref, e.sip_domain, e.wss_uri, e.webrtc_enabled,
                    a.extension AS agent_extension
             FROM rcc_sip_extensions e
             LEFT JOIN rcc_agents a ON a.id = e.agent_id AND a.tenant_id = e.tenant_id
             WHERE e.tenant_id = :tid AND (e.agent_id = :aid OR a.id = :aid2)
               AND e.status = \'active\' AND e.webrtc_enabled = 1
             ORDER BY e.agent_id IS NULL ASC
             LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $agentId, 'aid2' => $agentId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return $this->fallbackCredentials($tenantId, $agentId);
        }

        $config = $this->loadSipConfig();
        $password = $this->resolvePassword((string) $row['sip_password_ref']);

        return [
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'extension' => (string) ($row['extension'] ?: $row['agent_extension'] ?: $agentId),
            'username' => (string) $row['sip_username'],
            'password' => $password,
            'domain' => (string) ($row['sip_domain'] ?: $config['default_domain']),
            'wss_uri' => (string) ($row['wss_uri'] ?: $config['default_wss_uri']),
            'ice_servers' => $config['ice_servers'],
            'register_expires' => (int) $config['register_expires'],
            'uri' => sprintf(
                'sip:%s@%s',
                (string) $row['sip_username'],
                (string) ($row['sip_domain'] ?: $config['default_domain'])
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function buildWebRtcConfig(int $tenantId, int $agentId): array
    {
        $creds = $this->getCredentialsForAgent($tenantId, $agentId);
        return [
            'server' => $creds['wss_uri'],
            'aor' => $creds['uri'],
            'authorizationUsername' => $creds['username'],
            'authorizationPassword' => $creds['password'],
            'iceServers' => $creds['ice_servers'],
            'registerExpires' => $creds['register_expires'],
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
        ];
    }

    public function assertTenantAccess(int $tenantId, int $agentId): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM rcc_agents WHERE tenant_id = :tid AND id = :aid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $agentId]);
        if ($stmt->fetch() === false) {
            throw new \RuntimeException('Agent not found for tenant.');
        }
    }

    private function resolvePassword(string $ref): string
    {
        if ($ref === '') {
            return '';
        }
        $fromEnv = getenv($ref);
        if ($fromEnv !== false && $fromEnv !== '') {
            return (string) $fromEnv;
        }
        if (strpos($ref, 'RCC_SIP_PASS_') === 0) {
            return '';
        }
        return $ref;
    }

    /** @return array<string, mixed> */
    private function fallbackCredentials(int $tenantId, int $agentId): array
    {
        $config = $this->loadSipConfig();
        $username = 'tenant' . $tenantId . '-agent' . $agentId;
        return [
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'extension' => (string) (1000 + $agentId),
            'username' => $username,
            'password' => getenv('RCC_SIP_DEFAULT_PASS') ?: '',
            'domain' => $config['default_domain'],
            'wss_uri' => $config['default_wss_uri'],
            'ice_servers' => $config['ice_servers'],
            'register_expires' => (int) $config['register_expires'],
            'uri' => 'sip:' . $username . '@' . $config['default_domain'],
        ];
    }

    /** @return array<string, mixed> */
    private function loadSipConfig(): array
    {
        $path = dirname(__DIR__, 3) . '/config/sip.php';
        return is_file($path) ? (array) require $path : [];
    }
}
