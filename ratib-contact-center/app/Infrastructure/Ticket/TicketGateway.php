<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Ticket;

use Ratib\ContactCenter\App\Application\Contracts\TicketGatewayInterface;
use Ratib\ContactCenter\App\Core\Database;

final class TicketGateway implements TicketGatewayInterface
{
    /** @param array<string, mixed> $context */
    public function createFromIvr(int $tenantId, int $callId, string $subject, string $description, array $context = []): int
    {
        $pdo = Database::connection();
        $ticketNo = 'IVR-' . date('Ymd') . '-' . str_pad((string) $callId, 6, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare(
            'INSERT INTO rcc_tickets
             (tenant_id, ticket_no, subject, description, call_id, channel, priority, status, created_at)
             VALUES
             (:tid, :no, :sub, :desc, :cid, \'phone\', \'normal\', \'open\', NOW())'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'no' => $ticketNo,
            'sub' => $subject,
            'desc' => $description . "\n\n" . json_encode($context, JSON_UNESCAPED_UNICODE),
            'cid' => $callId,
        ]);

        return (int) $pdo->lastInsertId();
    }
}
