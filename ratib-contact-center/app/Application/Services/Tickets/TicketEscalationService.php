<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Tickets;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Tickets\TicketRepository;

final class TicketEscalationService
{
    public function __construct(
        private readonly TicketRepository $tickets = new TicketRepository(),
        private readonly TicketSlaService $sla = new TicketSlaService(),
        private readonly TicketAssignmentEngine $assignment = new TicketAssignmentEngine(),
        private readonly RccAuditService $audit = new RccAuditService()
    ) {
    }

    public function escalate(int $tenantId, int $ticketId, ?int $supervisorAgentId, ?int $userId): array
    {
        $ticket = $this->tickets->find($tenantId, $ticketId);
        if ($ticket === null) {
            throw new \RuntimeException('Ticket not found', 404);
        }
        $this->tickets->updateStatus($tenantId, $ticketId, 'pending');
        if ($supervisorAgentId !== null && $supervisorAgentId > 0) {
            $this->assignment->assign($tenantId, $ticketId, $supervisorAgentId, $userId);
        }
        $this->tickets->addComment($tenantId, $ticketId, 'Ticket escalated to supervisor queue.', $userId, null, true);
        $this->audit->log($tenantId, 'ticket.escalate', $userId, 'ticket', $ticketId);
        EventBus::instance()->emit([
            'type' => EventType::TICKET_ESCALATED,
            'tenant_id' => $tenantId,
            'payload' => ['ticket_id' => $ticketId],
        ]);
        return $this->tickets->find($tenantId, $ticketId) ?? [];
    }

    public function processSlaEscalations(int $tenantId): int
    {
        $breached = $this->sla->evaluateBreaches($tenantId);
        return $breached;
    }
}
