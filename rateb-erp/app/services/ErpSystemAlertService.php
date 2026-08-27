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
     * Persistent reply flashes — one compact card per ticket (grouped), until opened/read.
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
            // Fetch enough rows then collapse by ticket so old stacked notifs become one card.
            $rows = (new NotificationService())->listUnreadByTriggers(
                $userId,
                [SupportTicketAlertService::TRIGGER_REPLY],
                40
            );
        } catch (\Throwable $e) {
            return [];
        }

        $supportSvc = new SupportTicketAlertService();
        /** @var array<int, array{ticket_id:int,notif_ids:list<int>,count:int,title:string,message:string,ticket_no:string}> $byTicket */
        $byTicket = [];
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
            if (!isset($byTicket[$ticketId])) {
                $msg = (string) ($row['message'] ?? '');
                $ticketNo = '';
                if (preg_match('/^(ST-[\w-]+|A\d+-ST-[\w-]+|#[0-9]+)/u', $msg, $m)) {
                    $ticketNo = (string) $m[1];
                }
                $byTicket[$ticketId] = [
                    'ticket_id' => $ticketId,
                    'notif_ids' => [$notifId],
                    'count' => 1,
                    'title' => (string) ($row['title'] ?? ''),
                    'message' => $msg,
                    'ticket_no' => $ticketNo !== '' ? $ticketNo : ('#' . $ticketId),
                ];
            } else {
                $byTicket[$ticketId]['notif_ids'][] = $notifId;
                $byTicket[$ticketId]['count']++;
                // Keep newest message/title (rows are DESC by id).
            }
        }

        $out = [];
        $i = 0;
        foreach ($byTicket as $group) {
            if ($i >= $limit) {
                break;
            }
            $i++;
            $ticketId = (int) $group['ticket_id'];
            $count = max(1, (int) $group['count']);
            $ticketNo = (string) $group['ticket_no'];
            $title = $count > 1
                ? (string) __('support_ticket_flash_replies_title', [
                    'count' => (string) $count,
                    'ticket' => $ticketNo,
                ])
                : (string) __('support_ticket_flash_reply_single', ['ticket' => $ticketNo]);
            $out[] = [
                'key' => 'support_ticket_reply_ticket_' . $ticketId,
                'severity' => 'info',
                'title' => $title,
                'message' => (string) $group['message'],
                'url' => $supportSvc->ticketEditUrl($ticketId),
                'action_label' => (string) __('support_ticket_banner_open_ticket'),
                'persistent' => true,
                'pulse' => true,
                'icon' => 'fa-comments',
                'count' => $count,
                'ticket_ids' => [$ticketId],
                'notification_id' => (int) ($group['notif_ids'][0] ?? 0),
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
