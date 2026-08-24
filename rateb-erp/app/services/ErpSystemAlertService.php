<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Global ERP flash alerts for open support tickets (all layout pages).
 *
 * @phpstan-type ErpFlashAlert array{
 *   key:string,
 *   severity:string,
 *   title:string,
 *   message:string,
 *   url:string,
 *   action_label:string,
 *   persistent:bool,
 *   pulse:bool,
 *   icon:string,
 *   count?:int,
 *   ticket_ids?:list<int>
 * }
 */
final class ErpSystemAlertService
{
    /** @return list<ErpFlashAlert> */
    public function alertsForLayout(): array
    {
        $svc = new SupportTicketAlertService();
        $count = $svc->unreadOpenCountForViewer();
        if ($count < 1) {
            return [];
        }
        $alert = $this->buildSupportTicketAlert($count, $svc->listUnreadOpenTicketsForViewer(5));

        return $alert !== null ? [$alert] : [];
    }

    /**
     * Single aggregate alert (count + preview lines).
     *
     * @param list<array<string, mixed>> $tickets
     * @return ErpFlashAlert|null
     */
    public function buildSupportTicketAlert(int $count, array $tickets): ?array
    {
        if ($count < 1) {
            return null;
        }

        $supportSvc = new SupportTicketAlertService();
        $lines = [];
        $ticketIds = [];
        foreach ($tickets as $ticket) {
            $ticketId = (int) ($ticket['id'] ?? 0);
            if ($ticketId < 1) {
                continue;
            }
            $ticketIds[] = $ticketId;
            $ticketNo = (string) ($ticket['ticket_no'] ?? ('#' . $ticketId));
            $companyName = (string) ($ticket['company_name'] ?? '—');
            $subject = trim((string) ($ticket['subject'] ?? ''));
            $lines[] = (string) __('support_ticket_flash_line', [
                'ticket' => $ticketNo,
                'company' => $companyName,
                'subject' => $subject !== '' ? $subject : '—',
            ]);
        }
        if ($count > count($lines)) {
            $remaining = $count - count($lines);
            $lines[] = (string) __('support_ticket_flash_more', ['count' => (string) $remaining]);
        }

        return [
            'key' => 'support_tickets_unread',
            'severity' => 'warning',
            'title' => (string) __('support_ticket_flash_aggregate_title', ['count' => (string) $count]),
            'message' => implode("\n", $lines),
            'url' => $supportSvc->supportTicketsListUrl(),
            'action_label' => (string) __('support_ticket_banner_action'),
            'persistent' => true,
            'pulse' => true,
            'icon' => 'fa-life-ring',
            'count' => $count,
            'ticket_ids' => $ticketIds,
        ];
    }
}
