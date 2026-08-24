<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\SupportTicket;
use Rateb\App\Models\SupportTicketReply;

final class SupportTicketReplyService
{
    /** @return list<array<string, mixed>> */
    public function listForTicket(int $ticketId): array
    {
        if ($ticketId < 1) {
            return [];
        }
        try {
            return (new SupportTicketReply())->query(
                'SELECT r.*, u.name AS user_name, u.email AS user_email
                 FROM rateb_support_ticket_replies r
                 LEFT JOIN rateb_users u ON u.id = r.user_id
                 WHERE r.ticket_id = :tid
                 ORDER BY r.id ASC',
                ['tid' => $ticketId]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Platform/agency staff reply — notifies the submitting company.
     *
     * @param array<string, mixed> $ticket
     */
    public function addStaffReply(int $ticketId, int $staffUserId, string $body, array $ticket): int
    {
        $body = trim($body);
        if ($ticketId < 1 || $body === '') {
            return 0;
        }

        $companyId = (int) ($ticket['company_id'] ?? 0);
        $replyId = (int) (new SupportTicketReply())->create([
            'ticket_id' => $ticketId,
            'company_id' => $companyId > 0 ? $companyId : null,
            'user_id' => $staffUserId > 0 ? $staffUserId : null,
            'is_staff' => 1,
            'body' => $body,
        ]);

        if ($replyId < 1) {
            return 0;
        }

        $status = (string) ($ticket['status'] ?? 'open');
        if ($status === 'open') {
            try {
                Database::connection()->prepare(
                    'UPDATE rateb_support_tickets SET status = :st, updated_at = NOW() WHERE id = :id'
                )->execute(['st' => 'in_progress', 'id' => $ticketId]);
            } catch (\Throwable $e) {
                // best-effort
            }
            $ticket['status'] = 'in_progress';
        }

        (new SupportTicketAlertService())->notifyOnReply($ticketId, $ticket, $body);

        return $replyId;
    }

    /** @return array{original: array<string, mixed>, replies: list<array<string, mixed>>} */
    public function conversation(int $ticketId, array $ticket): array
    {
        $original = [
            'is_staff' => 0,
            'body' => (string) ($ticket['message'] ?? ''),
            'user_id' => (int) ($ticket['user_id'] ?? 0),
            'created_at' => (string) ($ticket['created_at'] ?? ''),
            'user_name' => $this->resolveUserName((int) ($ticket['user_id'] ?? 0)),
        ];

        return [
            'original' => $original,
            'replies' => $this->listForTicket($ticketId),
        ];
    }

    private function resolveUserName(int $userId): string
    {
        if ($userId < 1) {
            return '';
        }
        try {
            $row = (new SupportTicket())->queryOne(
                'SELECT name, email FROM rateb_users WHERE id = :id LIMIT 1',
                ['id' => $userId]
            );
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                return $name;
            }

            return trim((string) ($row['email'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }
}
