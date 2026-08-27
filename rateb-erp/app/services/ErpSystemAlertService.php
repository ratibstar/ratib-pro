<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Global ERP flash alerts for open support tickets (all layout pages).
 *
 * @phpstan-type ErpFlashPreviewItem array{ticket_no:string,company:string,subject:string}
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
 *   ticket_ids?:list<int>,
 *   preview_items?:list<ErpFlashPreviewItem>
 * }
 */
final class ErpSystemAlertService
{
    /** @return list<ErpFlashAlert> */
    public function alertsForLayout(): array
    {
        $alerts = [];
        $svc = new SupportTicketAlertService();
        $count = $svc->unreadOpenCountForViewer();
        if ($count > 0) {
            $alert = $this->buildSupportTicketAlert($count, $svc->listUnreadOpenTicketsForViewer(5));
            if ($alert !== null) {
                $alerts[] = $alert;
            }
        }
        foreach ($this->buildUnreadReplyAlerts(8) as $replyAlert) {
            $alerts[] = $replyAlert;
        }

        return $alerts;
    }

    /**
     * Persistent reply flashes (platform ↔ agency) until the ticket is opened / marked read.
     *
     * @return list<ErpFlashAlert>
     */
    public function buildUnreadReplyAlerts(int $limit = 8): array
    {
        $userId = (int) (\Rateb\App\Core\SessionManager::get('rateb_user_id', 0));
        if ($userId < 1) {
            return [];
        }

        $rows = [];
        try {
            $rows = (new NotificationService())->listUnreadByTriggers(
                $userId,
                [SupportTicketAlertService::TRIGGER_REPLY],
                max(1, min(10, $limit))
            );
        } catch (\Throwable $e) {
            return [];
        }

        $supportSvc = new SupportTicketAlertService();
        $out = [];
        foreach ($rows as $row) {
            $trigger = (string) ($row['trigger_type'] ?? '');
            $entity = (string) ($row['entity_type'] ?? '');
            if ($trigger !== SupportTicketAlertService::TRIGGER_REPLY) {
                continue;
            }
            if ($entity !== '' && $entity !== SupportTicketAlertService::ENTITY) {
                continue;
            }
            $ticketId = (int) ($row['entity_id'] ?? 0);
            $notifId = (int) ($row['id'] ?? 0);
            if ($ticketId < 1 || $notifId < 1) {
                continue;
            }
            $out[] = [
                'key' => 'support_ticket_reply_' . $notifId,
                'severity' => 'info',
                'title' => (string) ($row['title'] ?? __('support_ticket_alert_reply_title', ['ticket' => '#' . $ticketId])),
                'message' => (string) ($row['message'] ?? ''),
                'url' => $supportSvc->ticketEditUrl($ticketId),
                'action_label' => (string) __('support_ticket_banner_action'),
                'persistent' => true,
                'pulse' => true,
                'icon' => 'fa-comments',
                'count' => 1,
                'ticket_ids' => [$ticketId],
                'notification_id' => $notifId,
            ];
        }

        return $out;
    }

    /**
     * Single aggregate alert with structured ticket previews.
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
        $previewItems = [];
        $ticketIds = [];
        foreach ($tickets as $ticket) {
            $ticketId = (int) ($ticket['id'] ?? 0);
            if ($ticketId < 1) {
                continue;
            }
            $ticketIds[] = $ticketId;
            $previewItems[] = [
                'ticket_no' => (string) ($ticket['ticket_no'] ?? ('#' . $ticketId)),
                'company' => (string) ($ticket['company_name'] ?? '—'),
                'subject' => trim((string) ($ticket['subject'] ?? '')) ?: '—',
            ];
        }

        return [
            'key' => 'support_tickets_unread',
            'severity' => 'warning',
            'title' => (string) __('support_ticket_flash_aggregate_title', ['count' => (string) $count]),
            'message' => '',
            'preview_items' => $previewItems,
            'more_count' => max(0, $count - count($previewItems)),
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
