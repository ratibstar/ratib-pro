<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Tickets;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Tickets\TicketRepository;

final class TicketWorkflowService
{
    public function __construct(
        private readonly TicketRepository $tickets = new TicketRepository(),
        private readonly RccAuditService $audit = new RccAuditService()
    ) {
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $userId): array
    {
        $id = $this->tickets->create($tenantId, $data);
        $this->initSla($tenantId, $id, (string) ($data['priority'] ?? 'normal'));
        $this->audit->log($tenantId, 'ticket.create', $userId, 'ticket', $id, $data);
        EventBus::instance()->emit([
            'type' => EventType::TICKET_CREATED,
            'tenant_id' => $tenantId,
            'payload' => ['ticket_id' => $id],
        ]);
        return $this->tickets->find($tenantId, $id) ?? [];
    }

    public function reopen(int $tenantId, int $ticketId, ?int $userId): array
    {
        $this->tickets->updateStatus($tenantId, $ticketId, 'open');
        $this->audit->log($tenantId, 'ticket.reopen', $userId, 'ticket', $ticketId);
        EventBus::instance()->emit([
            'type' => EventType::TICKET_REOPENED,
            'tenant_id' => $tenantId,
            'payload' => ['ticket_id' => $ticketId],
        ]);
        return $this->tickets->find($tenantId, $ticketId) ?? [];
    }

    public function resolve(int $tenantId, int $ticketId, ?int $userId): array
    {
        $this->tickets->updateStatus($tenantId, $ticketId, 'resolved');
        $this->audit->log($tenantId, 'ticket.resolve', $userId, 'ticket', $ticketId);
        EventBus::instance()->emit([
            'type' => EventType::TICKET_RESOLVED,
            'tenant_id' => $tenantId,
            'payload' => ['ticket_id' => $ticketId],
        ]);
        return $this->tickets->find($tenantId, $ticketId) ?? [];
    }

    public function merge(int $tenantId, int $sourceId, int $targetId, ?int $userId): void
    {
        $this->tickets->merge($tenantId, $sourceId, $targetId);
        $this->tickets->addComment($tenantId, $targetId, 'Merged ticket #' . $sourceId, $userId, null, true);
        $this->audit->log($tenantId, 'ticket.merge', $userId, 'ticket', $targetId, ['source_id' => $sourceId]);
        EventBus::instance()->emit([
            'type' => EventType::TICKET_MERGED,
            'tenant_id' => $tenantId,
            'payload' => ['source_id' => $sourceId, 'target_id' => $targetId],
        ]);
    }

    /** @return array<string, mixed> */
    public function split(int $tenantId, int $ticketId, string $subject, string $description, ?int $userId): array
    {
        $parent = $this->tickets->find($tenantId, $ticketId);
        if ($parent === null) {
            throw new \RuntimeException('Ticket not found', 404);
        }
        $child = $this->create($tenantId, [
            'subject' => $subject,
            'description' => $description,
            'contact_id' => $parent['contact_id'] ?? null,
            'conversation_id' => $parent['conversation_id'] ?? null,
            'channel' => $parent['channel'] ?? 'phone',
            'priority' => $parent['priority'] ?? 'normal',
            'parent_ticket_id' => $ticketId,
            'source' => 'split',
        ], $userId);
        $this->tickets->addComment($tenantId, $ticketId, 'Split child ticket #' . ($child['id'] ?? ''), $userId, null, true);
        $this->audit->log($tenantId, 'ticket.split', $userId, 'ticket', (int) ($child['id'] ?? 0), ['parent_id' => $ticketId]);
        EventBus::instance()->emit([
            'type' => EventType::TICKET_SPLIT,
            'tenant_id' => $tenantId,
            'payload' => ['parent_id' => $ticketId, 'child_id' => $child['id'] ?? null],
        ]);
        return $child;
    }

    public function addComment(int $tenantId, int $ticketId, string $body, ?int $userId, ?int $agentId, bool $internal): int
    {
        $id = $this->tickets->addComment($tenantId, $ticketId, $body, $userId, $agentId, $internal);
        if (!$internal) {
            $pdo = Database::connection();
            $pdo->prepare(
                'UPDATE rcc_tickets SET first_response_at = COALESCE(first_response_at, NOW()), updated_at = NOW() WHERE tenant_id = :tid AND id = :id'
            )->execute(['tid' => $tenantId, 'id' => $ticketId]);
        }
        $this->audit->log($tenantId, 'ticket.comment', $userId, 'ticket_comment', $id);
        EventBus::instance()->emit([
            'type' => EventType::TICKET_COMMENT_ADDED,
            'tenant_id' => $tenantId,
            'payload' => ['ticket_id' => $ticketId, 'comment_id' => $id],
        ]);
        return $id;
    }

    private function initSla(int $tenantId, int $ticketId, string $priority): void
    {
        $hours = match ($priority) {
            'urgent' => 4,
            'high' => 8,
            'low' => 72,
            default => 24,
        };
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_ticket_sla (tenant_id, ticket_id, first_response_due, resolution_due)
             VALUES (:tid, :id, DATE_ADD(NOW(), INTERVAL :fr HOUR), DATE_ADD(NOW(), INTERVAL :res HOUR))'
        );
        $fr = max(1, (int) ($hours / 4));
        $stmt->execute(['tid' => $tenantId, 'id' => $ticketId, 'fr' => $fr, 'res' => $hours]);
    }
}
