<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories;

use Ratib\ContactCenter\App\Application\Contracts\IvrSessionRepositoryInterface;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Domain\IVR\IvrSession;
use Ratib\ContactCenter\App\Domain\IVR\Enums\IvrSessionStatus;

final class IvrSessionRepository implements IvrSessionRepositoryInterface
{
    public function findById(int $sessionId, int $tenantId): ?IvrSession
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_ivr_sessions WHERE id = :id AND tenant_id = :tid LIMIT 1'
        );
        $stmt->execute(['id' => $sessionId, 'tid' => $tenantId]);
        $row = $stmt->fetch();
        return $row !== false ? IvrSession::fromRow($row) : null;
    }

    public function findActiveByCallId(int $callId, int $tenantId): ?IvrSession
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM rcc_ivr_sessions
             WHERE call_id = :cid AND tenant_id = :tid
               AND status IN ('active','waiting_input')
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['cid' => $callId, 'tid' => $tenantId]);
        $row = $stmt->fetch();
        return $row !== false ? IvrSession::fromRow($row) : null;
    }

    public function findActiveByChannelId(string $channelId, int $tenantId): ?IvrSession
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM rcc_ivr_sessions
             WHERE channel_id = :ch AND tenant_id = :tid
               AND status IN ('active','waiting_input')
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['ch' => $channelId, 'tid' => $tenantId]);
        $row = $stmt->fetch();
        return $row !== false ? IvrSession::fromRow($row) : null;
    }

    /** @param array<string, mixed> $state */
    public function create(
        int $callId,
        ?string $callUuid,
        int $tenantId,
        int $flowId,
        ?int $currentNodeId,
        array $state,
        ?string $channelId,
        string $locale
    ): IvrSession {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_ivr_sessions
             (call_id, call_uuid, tenant_id, flow_id, current_node_id, state, status, channel_id, locale, retry_count, started_at)
             VALUES
             (:cid, :uuid, :tid, :fid, :nid, :state, :status, :ch, :loc, 0, NOW())'
        );
        $stmt->execute([
            'cid' => $callId,
            'uuid' => $callUuid,
            'tid' => $tenantId,
            'fid' => $flowId,
            'nid' => $currentNodeId,
            'state' => json_encode($state, JSON_UNESCAPED_UNICODE),
            'status' => IvrSessionStatus::Active->value,
            'ch' => $channelId,
            'loc' => in_array($locale, ['en', 'ar'], true) ? $locale : 'ar',
        ]);
        $id = (int) Database::connection()->lastInsertId();
        $session = $this->findById($id, $tenantId);
        if ($session === null) {
            throw new \RuntimeException('Failed to create IVR session.');
        }
        return $session;
    }

    /** @param array<string, mixed> $state */
    public function persist(
        int $sessionId,
        int $tenantId,
        ?int $currentNodeId,
        array $state,
        IvrSessionStatus $status,
        int $retryCount
    ): void {
        $stmt = Database::connection()->prepare(
            'UPDATE rcc_ivr_sessions
             SET current_node_id = :nid,
                 state = :state,
                 status = :status,
                 retry_count = :retry,
                 updated_at = NOW()
             WHERE id = :id AND tenant_id = :tid'
        );
        $stmt->execute([
            'nid' => $currentNodeId,
            'state' => json_encode($state, JSON_UNESCAPED_UNICODE),
            'status' => $status->value,
            'retry' => $retryCount,
            'id' => $sessionId,
            'tid' => $tenantId,
        ]);
    }

    public function finalize(int $sessionId, int $tenantId, IvrSessionStatus $status): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE rcc_ivr_sessions
             SET status = :status, completed_at = NOW(), updated_at = NOW()
             WHERE id = :id AND tenant_id = :tid'
        );
        $stmt->execute([
            'status' => $status->value,
            'id' => $sessionId,
            'tid' => $tenantId,
        ]);
    }
}
