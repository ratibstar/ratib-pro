<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Tickets;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class TicketSlaService
{
    /** @return array<string, mixed> */
    public function status(int $tenantId, int $ticketId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.*, t.status AS ticket_status, t.first_response_at
             FROM rcc_ticket_sla s
             INNER JOIN rcc_tickets t ON t.id = s.ticket_id AND t.tenant_id = s.tenant_id
             WHERE s.tenant_id = :tid AND s.ticket_id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $ticketId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return ['breached' => false];
        }
        $breached = false;
        if (!empty($row['resolution_due']) && empty($row['resolution_met']) && strtotime((string) $row['resolution_due']) < time()) {
            $breached = true;
        }
        return ['sla' => $row, 'breached' => $breached];
    }

    public function evaluateBreaches(int $tenantId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT s.ticket_id FROM rcc_ticket_sla s
             INNER JOIN rcc_tickets t ON t.id = s.ticket_id AND t.tenant_id = s.tenant_id
             WHERE s.tenant_id = :tid AND s.breached_at IS NULL AND s.resolution_due < NOW()
               AND t.status NOT IN (\'resolved\', \'closed\')'
        );
        $stmt->execute(['tid' => $tenantId]);
        $count = 0;
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $ticketId = (int) $row['ticket_id'];
            Database::connection()->prepare(
                'UPDATE rcc_ticket_sla SET breached_at = NOW(), resolution_met = 0 WHERE tenant_id = :tid AND ticket_id = :id'
            )->execute(['tid' => $tenantId, 'id' => $ticketId]);
            EventBus::instance()->emit([
                'type' => EventType::TICKET_SLA_BREACHED,
                'tenant_id' => $tenantId,
                'payload' => ['ticket_id' => $ticketId],
            ]);
            $count++;
        }
        return $count;
    }
}
