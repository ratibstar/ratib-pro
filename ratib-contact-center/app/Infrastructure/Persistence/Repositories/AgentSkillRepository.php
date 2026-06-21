<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories;

use Ratib\ContactCenter\App\Core\Database;

final class AgentSkillRepository
{
    /** @return list<array{agent_id: int, skill: string, level: int}> */
    public function listByTenant(int $tenantId): array
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT agent_id, skill, level FROM rcc_agent_skills
                 WHERE tenant_id = :tid ORDER BY agent_id ASC, skill ASC'
            );
            $stmt->execute(['tid' => $tenantId]);
            $rows = [];
            foreach ($stmt->fetchAll() as $row) {
                $rows[] = [
                    'agent_id' => (int) $row['agent_id'],
                    'skill' => (string) $row['skill'],
                    'level' => (int) $row['level'],
                ];
            }
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return list<int> */
    public function agentIdsForQueue(int $tenantId, int $queueId): array
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT agent_id FROM rcc_queue_members
                 WHERE tenant_id = :tid AND queue_id = :qid AND is_paused = 0'
            );
            $stmt->execute(['tid' => $tenantId, 'qid' => $queueId]);
            return array_map(static fn ($row) => (int) $row['agent_id'], $stmt->fetchAll() ?: []);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function seniorAgentsReady(int $tenantId, int $queueId): array
    {
        try {
            $stmt = Database::connection()->prepare(
                "SELECT a.id AS agent_id, a.extension, ls.status
                 FROM rcc_agents a
                 INNER JOIN rcc_queue_members qm
                   ON qm.agent_id = a.id AND qm.tenant_id = a.tenant_id AND qm.queue_id = :qid
                 LEFT JOIN rcc_agent_live_state ls
                   ON ls.agent_id = a.id AND ls.tenant_id = a.tenant_id
                 WHERE a.tenant_id = :tid AND a.is_senior = 1
                   AND COALESCE(ls.status, 'offline') = 'ready'
                 ORDER BY a.id ASC"
            );
            $stmt->execute(['tid' => $tenantId, 'qid' => $queueId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
