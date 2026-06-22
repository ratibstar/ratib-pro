<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Ops;

use Ratib\ContactCenter\App\Core\Database;

final class PbxServerRepository
{
    /** @return list<array<string, mixed>> */
    public function listByTenant(int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, tenant_id, name, ami_host, ami_port, ami_username, ami_secret_ref,
                    sip_domain, wss_uri, rtp_start, rtp_end, dialplan_package, status,
                    last_health_at, last_health_status, config_json, created_at, updated_at
             FROM rcc_pbx_servers WHERE tenant_id = :tid ORDER BY id ASC'
        );
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_pbx_servers WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findActive(int $tenantId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM rcc_pbx_servers WHERE tenant_id = :tid AND status = 'active' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function save(int $tenantId, array $data, ?int $id = null): int
    {
        $pdo = Database::connection();
        if ($id !== null && $id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE rcc_pbx_servers SET
                    name = :name, ami_host = :host, ami_port = :port, ami_username = :user,
                    ami_secret_ref = :secret_ref, sip_domain = :domain, wss_uri = :wss,
                    rtp_start = :rtp_start, rtp_end = :rtp_end, dialplan_package = :pkg,
                    status = :status, config_json = :cfg, updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tid'
            );
            $stmt->execute([
                'name' => (string) $data['name'],
                'host' => (string) $data['ami_host'],
                'port' => (int) $data['ami_port'],
                'user' => (string) $data['ami_username'],
                'secret_ref' => (string) $data['ami_secret_ref'],
                'domain' => (string) $data['sip_domain'],
                'wss' => $data['wss_uri'] ?? null,
                'rtp_start' => $data['rtp_start'] ?? null,
                'rtp_end' => $data['rtp_end'] ?? null,
                'pkg' => (string) ($data['dialplan_package'] ?? 'deploy/asterisk'),
                'status' => (string) ($data['status'] ?? 'draft'),
                'cfg' => isset($data['config_json']) ? json_encode($data['config_json'], JSON_UNESCAPED_UNICODE) : null,
                'id' => $id,
                'tid' => $tenantId,
            ]);
            return $id;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO rcc_pbx_servers
             (tenant_id, name, ami_host, ami_port, ami_username, ami_secret_ref, sip_domain, wss_uri,
              rtp_start, rtp_end, dialplan_package, status, config_json)
             VALUES (:tid, :name, :host, :port, :user, :secret_ref, :domain, :wss, :rtp_start, :rtp_end, :pkg, :status, :cfg)'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'name' => (string) $data['name'],
            'host' => (string) $data['ami_host'],
            'port' => (int) $data['ami_port'],
            'user' => (string) $data['ami_username'],
            'secret_ref' => (string) $data['ami_secret_ref'],
            'domain' => (string) $data['sip_domain'],
            'wss' => $data['wss_uri'] ?? null,
            'rtp_start' => $data['rtp_start'] ?? null,
            'rtp_end' => $data['rtp_end'] ?? null,
            'pkg' => (string) ($data['dialplan_package'] ?? 'deploy/asterisk'),
            'status' => (string) ($data['status'] ?? 'draft'),
            'cfg' => isset($data['config_json']) ? json_encode($data['config_json'], JSON_UNESCAPED_UNICODE) : null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    public function setHealth(int $tenantId, int $id, string $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE rcc_pbx_servers SET last_health_at = NOW(), last_health_status = :st, updated_at = NOW()
             WHERE tenant_id = :tid AND id = :id'
        );
        $stmt->execute(['st' => $status, 'tid' => $tenantId, 'id' => $id]);
    }

    public function activate(int $tenantId, int $id): void
    {
        $pdo = Database::connection();
        $pdo->prepare("UPDATE rcc_pbx_servers SET status = 'disabled', updated_at = NOW() WHERE tenant_id = :tid")
            ->execute(['tid' => $tenantId]);
        $pdo->prepare("UPDATE rcc_pbx_servers SET status = 'active', updated_at = NOW() WHERE tenant_id = :tid AND id = :id")
            ->execute(['tid' => $tenantId, 'id' => $id]);
    }
}
