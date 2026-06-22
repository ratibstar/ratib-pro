<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Tickets;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Tickets\TicketRepository;

final class TicketAssignmentEngine
{
    public function __construct(
        private readonly TicketRepository $tickets = new TicketRepository(),
        private readonly RccAuditService $audit = new RccAuditService()
    ) {
    }

    public function assign(int $tenantId, int $ticketId, int $agentId, ?int $byUserId): array
    {
        $this->tickets->assign($tenantId, $ticketId, $agentId, $byUserId);
        $this->audit->log($tenantId, 'ticket.assign', $byUserId, 'ticket', $ticketId, ['agent_id' => $agentId]);
        EventBus::instance()->emit([
            'type' => EventType::TICKET_ASSIGNED,
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            'payload' => ['ticket_id' => $ticketId],
        ]);
        return $this->tickets->find($tenantId, $ticketId) ?? [];
    }

    public function autoAssign(int $tenantId, int $ticketId, ?int $byUserId): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT a.id FROM rcc_agents a
             INNER JOIN rcc_agent_live_state als ON als.agent_id = a.id AND als.tenant_id = a.tenant_id
             WHERE a.tenant_id = :tid AND a.status = 'active' AND als.status IN ('ready','wrapup')
             ORDER BY (SELECT COUNT(*) FROM rcc_tickets t WHERE t.tenant_id = a.tenant_id AND t.assigned_agent_id = a.id AND t.status IN ('open','in_progress','pending')) ASC, a.id ASC
             LIMIT 1"
        );
        $stmt->execute(['tid' => $tenantId]);
        $agentId = $stmt->fetchColumn();
        if ($agentId === false) {
            return null;
        }
        return $this->assign($tenantId, $ticketId, (int) $agentId, $byUserId);
    }
}
