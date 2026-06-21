<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories;

use Ratib\ContactCenter\App\Core\Database;

final class ConversationRepository
{
    /** @return array<string, mixed>|null */
    public function findById(int $tenantId, int $conversationId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_conversations WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $conversationId]);
        $row = $stmt->fetch();
        return $row !== false ? $this->hydrate($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findOpenByIdentity(int $tenantId, string $identity): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM rcc_conversations
             WHERE tenant_id = :tid AND customer_identity = :ident AND status IN ('open','pending')
             ORDER BY last_message_at DESC LIMIT 1"
        );
        $stmt->execute(['tid' => $tenantId, 'ident' => $identity]);
        $row = $stmt->fetch();
        return $row !== false ? $this->hydrate($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findByCallId(int $tenantId, int $callId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_conversations WHERE tenant_id = :tid AND call_id = :cid LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'cid' => $callId]);
        $row = $stmt->fetch();
        return $row !== false ? $this->hydrate($row) : null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data): int
    {
        $channels = $data['channels'] ?? [];
        if (!is_array($channels)) {
            $channels = [$channels];
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_conversations
             (tenant_id, customer_id, customer_identity, assigned_agent_id, priority, status, sla_status,
              priority_score, last_channel, last_message, last_message_at, channels_json, call_id,
              ivr_session_id, metadata_json, unread_count)
             VALUES
             (:tid, :cust, :ident, :agent, :pri, :status, :sla, :ps, :ch, :msg, NOW(3), :channels, :call,
              :ivr, :meta, :unread)'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'cust' => $data['customer_id'] ?? null,
            'ident' => (string) ($data['customer_identity'] ?? 'unknown'),
            'agent' => $data['assigned_agent_id'] ?? null,
            'pri' => (string) ($data['priority'] ?? 'medium'),
            'status' => (string) ($data['status'] ?? 'open'),
            'sla' => (string) ($data['sla_status'] ?? 'green'),
            'ps' => $data['priority_score'] ?? null,
            'ch' => $data['last_channel'] ?? null,
            'msg' => $data['last_message'] ?? null,
            'channels' => json_encode(array_values(array_unique($channels)), JSON_UNESCAPED_UNICODE),
            'call' => $data['call_id'] ?? null,
            'ivr' => $data['ivr_session_id'] ?? null,
            'meta' => json_encode($data['metadata'] ?? [], JSON_UNESCAPED_UNICODE),
            'unread' => (int) ($data['unread_count'] ?? 1),
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string, mixed> $patch */
    public function update(int $tenantId, int $conversationId, array $patch): ?array
    {
        $existing = $this->findById($tenantId, $conversationId);
        if ($existing === null) {
            return null;
        }

        $channels = $existing['channels'];
        if (isset($patch['add_channel'])) {
            $channels[] = (string) $patch['add_channel'];
            $channels = array_values(array_unique($channels));
        }

        $stmt = Database::connection()->prepare(
            'UPDATE rcc_conversations SET
                customer_id = COALESCE(:cust, customer_id),
                assigned_agent_id = COALESCE(:agent, assigned_agent_id),
                priority = COALESCE(:pri, priority),
                status = COALESCE(:status, status),
                sla_status = COALESCE(:sla, sla_status),
                priority_score = COALESCE(:ps, priority_score),
                last_channel = COALESCE(:ch, last_channel),
                last_message = COALESCE(:msg, last_message),
                last_message_at = COALESCE(:msg_at, last_message_at, NOW(3)),
                channels_json = :channels,
                call_id = COALESCE(:call, call_id),
                ivr_session_id = COALESCE(:ivr, ivr_session_id),
                metadata_json = COALESCE(:meta, metadata_json),
                unread_count = COALESCE(:unread, unread_count),
                updated_at = NOW()
             WHERE tenant_id = :tid AND id = :id'
        );
        $stmt->execute([
            'cust' => $patch['customer_id'] ?? null,
            'agent' => $patch['assigned_agent_id'] ?? null,
            'pri' => $patch['priority'] ?? null,
            'status' => $patch['status'] ?? null,
            'sla' => $patch['sla_status'] ?? null,
            'ps' => $patch['priority_score'] ?? null,
            'ch' => $patch['last_channel'] ?? null,
            'msg' => $patch['last_message'] ?? null,
            'msg_at' => $patch['last_message_at'] ?? null,
            'channels' => json_encode($channels, JSON_UNESCAPED_UNICODE),
            'call' => $patch['call_id'] ?? null,
            'ivr' => $patch['ivr_session_id'] ?? null,
            'meta' => isset($patch['metadata']) ? json_encode($patch['metadata'], JSON_UNESCAPED_UNICODE) : null,
            'unread' => $patch['unread_count'] ?? null,
            'tid' => $tenantId,
            'id' => $conversationId,
        ]);

        return $this->findById($tenantId, $conversationId);
    }

    /** @return list<array<string, mixed>> */
    public function listForAgent(int $tenantId, int $agentId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM rcc_conversations
             WHERE tenant_id = :tid AND assigned_agent_id = :aid AND status != 'closed'
             ORDER BY last_message_at DESC LIMIT :lim"
        );
        $stmt->bindValue('tid', $tenantId, \PDO::PARAM_INT);
        $stmt->bindValue('aid', $agentId, \PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = $this->hydrate($row);
        }
        return $list;
    }

    public function linkCall(int $tenantId, int $callId, int $conversationId): void
    {
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE rcc_calls SET conversation_id = :conv WHERE id = :cid AND tenant_id = :tid'
            );
            $stmt->execute(['conv' => $conversationId, 'cid' => $callId, 'tid' => $tenantId]);
        } catch (\Throwable $e) {
            error_log('[RCC Conversation] linkCall: ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): array
    {
        $channels = $row['channels_json'] ?? '[]';
        if (is_string($channels)) {
            $decoded = json_decode($channels, true);
            $channels = is_array($decoded) ? $decoded : [];
        }
        $meta = $row['metadata_json'] ?? '{}';
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        return [
            'conversation_id' => (int) $row['id'],
            'tenant_id' => (int) $row['tenant_id'],
            'customer_id' => isset($row['customer_id']) ? (int) $row['customer_id'] : null,
            'customer_identity' => (string) $row['customer_identity'],
            'assigned_agent_id' => isset($row['assigned_agent_id']) ? (int) $row['assigned_agent_id'] : null,
            'priority' => (string) $row['priority'],
            'status' => (string) $row['status'],
            'sla_status' => (string) $row['sla_status'],
            'priority_score' => isset($row['priority_score']) ? (float) $row['priority_score'] : null,
            'last_channel' => $row['last_channel'] ?? null,
            'last_message' => $row['last_message'] ?? null,
            'last_message_at' => $row['last_message_at'] ?? null,
            'channels' => $channels,
            'call_id' => isset($row['call_id']) ? (int) $row['call_id'] : null,
            'ivr_session_id' => isset($row['ivr_session_id']) ? (int) $row['ivr_session_id'] : null,
            'unread_count' => (int) ($row['unread_count'] ?? 0),
            'metadata' => $meta,
        ];
    }
}
