<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Tickets;

use Ratib\ContactCenter\App\Core\Database;

final class TicketRepository
{
    /** @return list<array<string, mixed>> */
    public function list(int $tenantId, ?string $status = null, ?int $assignedAgentId = null, int $limit = 100): array
    {
        $sql = 'SELECT t.*, c.full_name AS contact_name, a.display_name AS assignee_name
                FROM rcc_tickets t
                LEFT JOIN rcc_contacts c ON c.id = t.contact_id AND c.tenant_id = t.tenant_id
                LEFT JOIN rcc_agents a ON a.id = t.assigned_agent_id AND a.tenant_id = t.tenant_id
                WHERE t.tenant_id = :tid AND t.merged_into_id IS NULL';
        $params = ['tid' => $tenantId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND t.status = :status';
            $params['status'] = $status;
        }
        if ($assignedAgentId !== null && $assignedAgentId > 0) {
            $sql .= ' AND t.assigned_agent_id = :aid';
            $params['aid'] = $assignedAgentId;
        }
        $sql .= ' ORDER BY t.updated_at DESC, t.id DESC LIMIT ' . max(1, min(500, $limit));
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_tickets WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data): int
    {
        $no = (string) ($data['ticket_no'] ?? ('TKT-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)))));
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_tickets (tenant_id, ticket_no, subject, description, conversation_id, call_id, contact_id, category_id, channel, priority, status, source, auto_created, assigned_agent_id, resolution_due)
             VALUES (:tid, :no, :sub, :desc, :conv, :call, :contact, :cat, :ch, :pri, :status, :src, :auto, :agent, :due)'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'no' => $no,
            'sub' => (string) ($data['subject'] ?? 'Ticket'),
            'desc' => (string) ($data['description'] ?? ''),
            'conv' => $data['conversation_id'] ?? null,
            'call' => $data['call_id'] ?? null,
            'contact' => $data['contact_id'] ?? null,
            'cat' => $data['category_id'] ?? null,
            'ch' => (string) ($data['channel'] ?? 'phone'),
            'pri' => (string) ($data['priority'] ?? 'normal'),
            'status' => (string) ($data['status'] ?? 'open'),
            'src' => $data['source'] ?? 'manual',
            'auto' => !empty($data['auto_created']) ? 1 : 0,
            'agent' => $data['assigned_agent_id'] ?? null,
            'due' => $data['resolution_due'] ?? null,
        ]);
        $newId = (int) Database::connection()->lastInsertId();
        if (!empty($data['parent_ticket_id'])) {
            Database::connection()->prepare(
                'UPDATE rcc_tickets SET parent_ticket_id = :pid WHERE tenant_id = :tid AND id = :id'
            )->execute(['tid' => $tenantId, 'id' => $newId, 'pid' => (int) $data['parent_ticket_id']]);
        }
        return $newId;
    }

    public function updateStatus(int $tenantId, int $ticketId, string $status): void
    {
        $extra = '';
        if ($status === 'resolved') {
            $extra = ', resolved_at = COALESCE(resolved_at, NOW())';
        } elseif ($status === 'closed') {
            $extra = ', closed_at = COALESCE(closed_at, NOW())';
        }
        $stmt = Database::connection()->prepare(
            "UPDATE rcc_tickets SET status = :status, updated_at = NOW() $extra WHERE tenant_id = :tid AND id = :id"
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $ticketId, 'status' => $status]);
    }

    public function assign(int $tenantId, int $ticketId, int $agentId, ?int $byUserId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE rcc_tickets SET assigned_agent_id = :aid, assigned_by_user_id = :uid, status = IF(status = \'open\', \'in_progress\', status), updated_at = NOW()
             WHERE tenant_id = :tid AND id = :id'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $ticketId, 'aid' => $agentId, 'uid' => $byUserId]);
    }

    public function merge(int $tenantId, int $sourceId, int $targetId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE rcc_tickets SET merged_into_id = :target, status = \'closed\', closed_at = NOW(), updated_at = NOW()
             WHERE tenant_id = :tid AND id = :id'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $sourceId, 'target' => $targetId]);
    }

    public function addComment(int $tenantId, int $ticketId, string $body, ?int $userId, ?int $agentId, bool $internal): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_ticket_comments (tenant_id, ticket_id, author_user_id, author_agent_id, body, is_internal)
             VALUES (:tid, :tid2, :uid, :aid, :body, :internal)'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'tid2' => $ticketId,
            'uid' => $userId,
            'aid' => $agentId,
            'body' => $body,
            'internal' => $internal ? 1 : 0,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function comments(int $tenantId, int $ticketId, bool $includeInternal): array
    {
        $sql = 'SELECT * FROM rcc_ticket_comments WHERE tenant_id = :tid AND ticket_id = :id';
        if (!$includeInternal) {
            $sql .= ' AND is_internal = 0';
        }
        $sql .= ' ORDER BY created_at ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['tid' => $tenantId, 'id' => $ticketId]);
        return $stmt->fetchAll() ?: [];
    }
}
