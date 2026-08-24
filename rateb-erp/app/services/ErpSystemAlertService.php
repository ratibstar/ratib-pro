<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Global ERP flash alerts for open support tickets (all layout pages).
 *
 * Only support tickets appear here — subscription, approval, and other notifications
 * stay on their dedicated pages/banners to avoid a cluttered flash stack.
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
 *   icon:string
 * }
 */
final class ErpSystemAlertService
{
    /** @return list<ErpFlashAlert> */
    public function alertsForLayout(): array
    {
        $supportSvc = new SupportTicketAlertService();
        $tickets = $supportSvc->listOpenTicketsForViewer(5);
        if ($tickets === []) {
            return [];
        }

        $alerts = [];
        $listUrl = $supportSvc->supportTicketsListUrl();
        foreach ($tickets as $ticket) {
            $ticketId = (int) ($ticket['id'] ?? 0);
            if ($ticketId < 1) {
                continue;
            }
            $ticketNo = (string) ($ticket['ticket_no'] ?? ('#' . $ticketId));
            $companyName = (string) ($ticket['company_name'] ?? '—');
            $subject = trim((string) ($ticket['subject'] ?? ''));

            $alerts[] = [
                'key' => 'support_ticket_' . $ticketId,
                'severity' => 'warning',
                'title' => (string) __('support_ticket_flash_title', [
                    'ticket' => $ticketNo,
                    'company' => $companyName,
                ]),
                'message' => (string) __('support_ticket_flash_body', [
                    'subject' => $subject !== '' ? $subject : '—',
                ]),
                'url' => $listUrl,
                'action_label' => (string) __('support_ticket_banner_action'),
                'persistent' => true,
                'pulse' => true,
                'icon' => 'fa-life-ring',
            ];
        }

        return $alerts;
    }
}
