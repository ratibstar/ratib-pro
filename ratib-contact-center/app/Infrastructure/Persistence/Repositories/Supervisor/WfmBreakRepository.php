<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Supervisor;

use Ratib\ContactCenter\App\Core\Database;

final class WfmBreakRepository
{
    /** @return array<string, mixed>|null */
    public function findOpenBreak(int $tenantId, int $agentId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_wfm_breaks WHERE tenant_id=:tid AND agent_id=:aid AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $agentId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function listActive(int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT b.*, a.display_name, a.extension
             FROM rcc_wfm_breaks b
             INNER JOIN rcc_agents a ON a.id = b.agent_id AND a.tenant_id = b.tenant_id
             WHERE b.tenant_id = :tid AND b.ended_at IS NULL
             ORDER BY b.started_at DESC'
        );
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listForAgent(int $tenantId, int $agentId, string $from, string $to): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_wfm_breaks
             WHERE tenant_id=:tid AND agent_id=:aid AND started_at BETWEEN :from AND :to
             ORDER BY started_at DESC'
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $agentId, 'from' => $from, 'to' => $to]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, mixed> */
    public function startBreak(int $tenantId, int $agentId, string $breakType, ?string $reason = null): array
    {
        $open = $this->findOpenBreak($tenantId, $agentId);
        if ($open !== null) {
            return $open;
        }
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'INSERT INTO rcc_wfm_breaks (tenant_id, agent_id, break_type, reason) VALUES (:tid, :aid, :type, :reason)'
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $agentId, 'type' => $breakType, 'reason' => $reason]);
        $id = (int) $pdo->lastInsertId();
        $row = $pdo->prepare('SELECT * FROM rcc_wfm_breaks WHERE id=:id');
        $row->execute(['id' => $id]);
        return $row->fetch() ?: [];
    }

    /** @return array<string, mixed>|null */
    public function endBreak(int $tenantId, int $agentId): ?array
    {
        $open = $this->findOpenBreak($tenantId, $agentId);
        if ($open === null) {
            return null;
        }
        $pdo = Database::connection();
        $now = gmdate('Y-m-d H:i:s');
        $upd = $pdo->prepare('UPDATE rcc_wfm_breaks SET ended_at=:end WHERE id=:id');
        $upd->execute(['end' => $now, 'id' => $open['id']]);
        $row = $pdo->prepare('SELECT * FROM rcc_wfm_breaks WHERE id=:id');
        $row->execute(['id' => $open['id']]);
        return $row->fetch() ?: null;
    }
}
