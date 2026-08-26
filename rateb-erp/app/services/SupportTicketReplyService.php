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

    /** Strip internal sync markers from reply body for display. */
    public static function displayBody(string $body): string
    {
        $clean = preg_replace(
            '/\n*\s*\[rateb_platform_reply:\d+:\d+\]\s*$/u',
            '',
            $body
        );
        $clean = preg_replace(
            '/\n*\s*\[rateb_agency_reply:\d+:\d+\]\s*$/u',
            '',
            (string) ($clean ?? $body)
        );

        return trim((string) ($clean ?? $body));
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

        $ticket['id'] = $ticketId;
        try {
            if (!isset($ticket['message']) || trim((string) $ticket['message']) === ''
                || !isset($ticket['ticket_no']) || trim((string) ($ticket['ticket_no'] ?? '')) === '') {
                $full = (new SupportTicket())->findByIdUnscoped($ticketId);
                if (is_array($full)) {
                    $ticket = array_merge($full, $ticket);
                }
            }
        } catch (\Throwable $e) {
            // keep partial ticket
        }

        $mirror = new SupportTicketPlatformMirrorService();
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            // Agency staff reply → platform Super Admin thread
            try {
                $mirror->mirrorAgencyReplyToPlatform($ticketId, $replyId, $body, $ticket);
            } catch (\Throwable $e) {
                error_log('support ticket mirror agency reply: ' . $e->getMessage());
            }
        } else {
            // Platform Super Admin reply → agency ticket thread
            try {
                $ok = $mirror->pushStaffReplyToAgency($ticketId, $ticket, $body, $replyId);
                if (!$ok) {
                    SessionManager::flash(
                        'warning',
                        __('support_ticket_agency_sync_failed')
                    );
                }
            } catch (\Throwable $e) {
                error_log('support ticket push reply to agency: ' . $e->getMessage());
                SessionManager::flash(
                    'warning',
                    __('support_ticket_agency_sync_failed')
                );
            }
        }

        return $replyId;
    }

    /** @return array{original: array<string, mixed>, replies: list<array<string, mixed>>} */
    public function conversation(int $ticketId, array $ticket): array
    {
        $rawMessage = (string) ($ticket['message'] ?? '');
        $displayMessage = trim((string) (preg_replace(
            '/\n*\s*\[rateb_agency_ticket:\d+:\d+\]\s*$/u',
            '',
            $rawMessage
        ) ?? $rawMessage));
        // Drop agency header lines added during platform mirror (الوكالة: …).
        if (str_contains($displayMessage, "\n\n")) {
            $parts = explode("\n\n", $displayMessage, 2);
            if (isset($parts[0]) && (str_starts_with($parts[0], 'الوكالة:') || str_starts_with($parts[0], 'Agency:'))) {
                $displayMessage = trim((string) ($parts[1] ?? $displayMessage));
            }
        }

        $original = [
            'is_staff' => 0,
            'body' => $displayMessage,
            'user_id' => (int) ($ticket['user_id'] ?? 0),
            'created_at' => (string) ($ticket['created_at'] ?? ''),
            'user_name' => $this->resolveUserName((int) ($ticket['user_id'] ?? 0)),
        ];

        $replies = [];
        foreach ($this->listForTicket($ticketId) as $row) {
            $row['body'] = self::displayBody((string) ($row['body'] ?? ''));
            $replies[] = $row;
        }

        return [
            'original' => $original,
            'replies' => $replies,
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
