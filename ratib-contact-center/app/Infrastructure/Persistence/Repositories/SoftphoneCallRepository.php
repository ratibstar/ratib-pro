<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Domain\Softphone\Enums\SoftphoneCallStatus;
use Ratib\ContactCenter\App\Domain\Softphone\Enums\SoftphoneDirection;

final class SoftphoneCallRepository
{
    /** @return array<string, mixed>|null */
    public function findActiveByAgent(int $tenantId, int $agentId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM rcc_softphone_calls
             WHERE tenant_id = :tid AND agent_id = :aid
               AND status IN ('ringing','connected','held')
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['tid' => $tenantId, 'aid' => $agentId]);
        $row = $stmt->fetch();
        return $row !== false ? $this->mapRow($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_softphone_calls WHERE id = :id AND tenant_id = :tid LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'tid' => $tenantId]);
        $row = $stmt->fetch();
        return $row !== false ? $this->mapRow($row) : null;
    }

    /** @param array<string, mixed> $extra */
    public function create(
        int $tenantId,
        int $agentId,
        string $remoteNumber,
        SoftphoneDirection $direction,
        ?int $callId = null,
        ?int $queueId = null,
        ?string $sipCallId = null,
        array $extra = []
    ): array {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_softphone_calls
             (call_id, tenant_id, agent_id, queue_id, direction, status, remote_number, sip_call_id, state_json, started_at)
             VALUES (:cid, :tid, :aid, :qid, :dir, :status, :remote, :sip, :json, NOW(3))'
        );
        $stmt->execute([
            'cid' => $callId,
            'tid' => $tenantId,
            'aid' => $agentId,
            'qid' => $queueId,
            'dir' => $direction->value,
            'status' => SoftphoneCallStatus::Ringing->value,
            'remote' => $remoteNumber,
            'sip' => $sipCallId,
            'json' => json_encode($extra, JSON_UNESCAPED_UNICODE),
        ]);
        $id = (int) Database::connection()->lastInsertId();
        return $this->findById($id, $tenantId) ?? [];
    }

    public function updateStatus(
        int $id,
        int $tenantId,
        SoftphoneCallStatus $status,
        ?int $durationSeconds = null
    ): ?array {
        $extra = $durationSeconds !== null ? ', duration_seconds = :dur' : '';
        $params = ['id' => $id, 'tid' => $tenantId, 'status' => $status->value];
        if ($durationSeconds !== null) {
            $params['dur'] = $durationSeconds;
        }
        $connected = $status === SoftphoneCallStatus::Connected ? ', connected_at = COALESCE(connected_at, NOW(3))' : '';
        $ended = $status === SoftphoneCallStatus::Ended ? ', ended_at = NOW(3)' : '';

        $sql = 'UPDATE rcc_softphone_calls SET status = :status' . $extra . $connected . $ended . ' WHERE id = :id AND tenant_id = :tid';
        Database::connection()->prepare($sql)->execute($params);
        return $this->findById($id, $tenantId);
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): array
    {
        $started = strtotime((string) $row['started_at']);
        $duration = (int) ($row['duration_seconds'] ?? 0);
        if ($duration === 0 && $started > 0 && in_array($row['status'], ['connected', 'held'], true)) {
            $duration = max(0, time() - $started);
        }
        return [
            'id' => (int) $row['id'],
            'call_id' => isset($row['call_id']) ? (int) $row['call_id'] : null,
            'agent_id' => (int) $row['agent_id'],
            'tenant_id' => (int) $row['tenant_id'],
            'status' => (string) $row['status'],
            'direction' => (string) $row['direction'],
            'queue_id' => isset($row['queue_id']) ? (int) $row['queue_id'] : null,
            'remote_number' => (string) $row['remote_number'],
            'duration' => $duration,
            'started_at' => $row['started_at'],
            'connected_at' => $row['connected_at'] ?? null,
            'sip_call_id' => $row['sip_call_id'] ?? null,
        ];
    }
}
