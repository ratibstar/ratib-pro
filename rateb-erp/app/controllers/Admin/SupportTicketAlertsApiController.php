<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\ErpSystemAlertService;
use Rateb\App\Services\NotificationService;
use Rateb\App\Services\SupportTicketAlertService;
use Rateb\App\Services\SupportTicketPlatformMirrorService;
use Rateb\App\Services\SupportTicketReplyService;
use Rateb\App\Models\SupportTicket;

/** JSON poll + mark-seen for live support-ticket flash alerts and in-page live sync. */
final class SupportTicketAlertsApiController
{
    public function poll(): void
    {
        if (!Auth::check()) {
            Response::json(['ok' => false, 'error' => 'unauthorized'], 401);

            return;
        }
        $canPoll = (function_exists('rateb_is_super_admin') && rateb_is_super_admin())
            || (function_exists('rateb_nav_can') && rateb_nav_can('settings.manage'));
        if (!$canPoll) {
            Response::json(['ok' => true, 'count' => 0, 'alert' => null]);

            return;
        }

        $svc = new SupportTicketAlertService();
        $this->runBackgroundSync();

        $ticketId = (int) ($_GET['ticket_id'] ?? 0);
        if ($ticketId > 0) {
            try {
                $mirror = new SupportTicketPlatformMirrorService();
                if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
                    $mirror->pullPlatformUpdatesIntoAgencyTicket($ticketId);
                } elseif (function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
                    // Platform edit page: pull latest agency replies/status into this mirror now.
                    $mirror->pullSingleMirroredTicketFromAgency($ticketId);
                }
            } catch (\Throwable $e) {
                error_log('support ticket live ticket sync: ' . $e->getMessage());
            }
        }

        // Viewing a ticket counts as reading — clear persistent reply flashes for it.
        if ($ticketId > 0) {
            $svc->markTicketSeen($ticketId);
        }

        $count = $svc->unreadOpenCountForViewer();
        $tickets = $svc->listUnreadOpenTicketsForViewer(5);
        $flashSvc = new ErpSystemAlertService();
        $alert = $flashSvc->buildSupportTicketAlert($count, $tickets);
        $alerts = $flashSvc->alertsForLayout();

        $payload = [
            'ok' => true,
            'count' => $count,
            'alert' => $alert,
            'alerts' => $alerts,
            'activity_token' => $this->globalActivityToken($count),
            'notifications' => $this->notificationsPayload(),
            'tickets_table' => $this->ticketsTablePayload(),
            'ticket' => null,
            'server_time' => date('c'),
        ];

        if ($ticketId > 0) {
            $payload['ticket'] = (new SupportTicketReplyService())->liveSnapshot($ticketId);
        }

        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
        }

        Response::json($payload);
    }

    public function markSeen(): void
    {
        if (!Auth::check()) {
            Response::json(['ok' => false, 'error' => 'unauthorized'], 401);

            return;
        }

        $ticketId = (int) ($_POST['ticket_id'] ?? $_GET['ticket_id'] ?? 0);
        if ($ticketId < 1) {
            Response::json(['ok' => false, 'error' => 'invalid_ticket'], 422);

            return;
        }

        $svc = new SupportTicketAlertService();
        $svc->markTicketSeen($ticketId);
        $count = $svc->unreadOpenCountForViewer();
        $flashSvc = new ErpSystemAlertService();

        Response::json([
            'ok' => true,
            'count' => $count,
            'alert' => $flashSvc->buildSupportTicketAlert(
                $count,
                $svc->listUnreadOpenTicketsForViewer(5)
            ),
            'alerts' => $flashSvc->alertsForLayout(),
            'activity_token' => $this->globalActivityToken($count),
            'notifications' => $this->notificationsPayload(),
            'tickets_table' => $this->ticketsTablePayload(),
        ]);
    }

    /**
     * Live table rows for in-place status/priority badge updates on the index.
     *
     * @return list<array<string, mixed>>
     */
    private function ticketsTablePayload(): array
    {
        try {
            $rows = (new SupportTicket())->query(
                'SELECT id, ticket_no, status, priority
                 FROM rateb_support_tickets
                 ORDER BY id DESC
                 LIMIT 80'
            );
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $status = (string) ($row['status'] ?? '');
            $priority = (string) ($row['priority'] ?? '');
            $out[] = [
                'id' => $id,
                'ticket_no' => (string) ($row['ticket_no'] ?? ''),
                'status' => $status,
                'priority' => $priority,
                'status_label' => $this->translateTicketEnum($status),
                'priority_label' => $this->translateTicketEnum($priority),
                'status_badge' => $this->statusBadgeTone($status),
                'priority_badge' => $this->priorityBadgeTone($priority),
            ];
        }

        return $out;
    }

    private function translateTicketEnum(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        $label = __($key);

        return $label !== $key ? (string) $label : $key;
    }

    private function statusBadgeTone(string $status): string
    {
        return match ($status) {
            'open', 'pending' => 'primary',
            'in_progress' => 'warning',
            'resolved' => 'success',
            'closed' => 'secondary',
            default => 'info',
        };
    }

    private function priorityBadgeTone(string $priority): string
    {
        return match ($priority) {
            'low' => 'success',
            'medium' => 'info',
            'high' => 'warning',
            'urgent' => 'danger',
            default => 'secondary',
        };
    }

    private function runBackgroundSync(): void
    {
        // Platform SA: keep agency mirrors fresh.
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()
            && function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()
            && (!function_exists('rateb_is_agency_erp_host') || !rateb_is_agency_erp_host())) {
            $lastPull = (int) SessionManager::get('rateb_support_ticket_agency_pull_at', 0);
            if ($lastPull < 1 || (time() - $lastPull) >= 1) {
                try {
                    $mirror = new SupportTicketPlatformMirrorService();
                    $mirror->pullOpenTicketsFromAgencies(15);
                    // Also reconcile recently touched mirrors so status/priority land in the list ASAP.
                    $recent = (new SupportTicket())->query(
                        'SELECT id FROM rateb_support_tickets
                         ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
                         LIMIT 25'
                    );
                    foreach ($recent as $row) {
                        $id = (int) ($row['id'] ?? 0);
                        if ($id > 0) {
                            $mirror->pullSingleMirroredTicketFromAgency($id);
                        }
                    }
                    SessionManager::set('rateb_support_ticket_agency_pull_at', time());
                } catch (\Throwable $e) {
                    error_log('support ticket alerts poll pull: ' . $e->getMessage());
                }
            }
        }

        // Agency: pull Super Admin replies/status into local open tickets.
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            $lastInbound = (int) SessionManager::get('rateb_support_ticket_platform_pull_at', 0);
            if ($lastInbound < 1 || (time() - $lastInbound) >= 1) {
                try {
                    $mirror = new SupportTicketPlatformMirrorService();
                    $rows = (new SupportTicket())->query(
                        'SELECT id FROM rateb_support_tickets
                         WHERE status IN (\'open\', \'in_progress\')
                         ORDER BY id DESC LIMIT 20'
                    );
                    foreach ($rows as $row) {
                        $id = (int) ($row['id'] ?? 0);
                        if ($id > 0) {
                            $mirror->pullPlatformUpdatesIntoAgencyTicket($id);
                        }
                    }
                    SessionManager::set('rateb_support_ticket_platform_pull_at', time());
                } catch (\Throwable $e) {
                    error_log('support ticket alerts agency inbound: ' . $e->getMessage());
                }
            }
        }
    }

    private function globalActivityToken(int $openCount): string
    {
        $maxTicketId = 0;
        $maxReplyId = 0;
        $statusFp = '0';
        try {
            $t = (new SupportTicket())->queryOne(
                'SELECT MAX(id) AS m,
                        SUM(CASE WHEN status = \'open\' THEN 1 ELSE 0 END) AS c_open,
                        SUM(CASE WHEN status = \'in_progress\' THEN 1 ELSE 0 END) AS c_ip,
                        SUM(CASE WHEN status = \'resolved\' THEN 1 ELSE 0 END) AS c_res,
                        SUM(CASE WHEN status = \'closed\' THEN 1 ELSE 0 END) AS c_cl,
                        SUM(CASE WHEN priority = \'urgent\' THEN 1 ELSE 0 END) AS c_urg,
                        SUM(CASE WHEN priority = \'high\' THEN 1 ELSE 0 END) AS c_hi
                 FROM rateb_support_tickets'
            );
            $maxTicketId = (int) ($t['m'] ?? 0);
            $statusFp = ((int) ($t['c_open'] ?? 0))
                . '-' . ((int) ($t['c_ip'] ?? 0))
                . '-' . ((int) ($t['c_res'] ?? 0))
                . '-' . ((int) ($t['c_cl'] ?? 0))
                . '-' . ((int) ($t['c_urg'] ?? 0))
                . '-' . ((int) ($t['c_hi'] ?? 0));
        } catch (\Throwable $e) {
            $maxTicketId = 0;
            $statusFp = '0';
        }
        try {
            $r = (new SupportTicket())->queryOne(
                'SELECT MAX(id) AS m FROM rateb_support_ticket_replies'
            );
            $maxReplyId = (int) ($r['m'] ?? 0);
        } catch (\Throwable $e) {
            $maxReplyId = 0;
        }

        // Include status/priority fingerprint so list soft-refresh fires on status-only edits.
        return $openCount . ':' . $maxTicketId . ':' . $maxReplyId . ':' . $statusFp;
    }

    /** @return array{unread:int,items:list<array<string,mixed>>} */
    private function notificationsPayload(): array
    {
        $userId = (int) SessionManager::get('rateb_user_id', 0);
        if ($userId < 1) {
            return ['unread' => 0, 'items' => []];
        }
        $notifier = new NotificationService();
        $items = [];
        try {
            // Open/reply ticket notices stay in persistent flash stack until read — not toasts.
            $rows = $notifier->listUnreadFlashForUser($userId, 5, [
                SupportTicketAlertService::TRIGGER_OPEN,
                SupportTicketAlertService::TRIGGER_REPLY,
            ]);
            foreach ($rows as $row) {
                $items[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'title' => (string) ($row['title'] ?? ''),
                    'message' => (string) ($row['message'] ?? ''),
                    'type' => (string) ($row['type'] ?? 'info'),
                    'trigger_type' => (string) ($row['trigger_type'] ?? ''),
                    'entity_type' => (string) ($row['entity_type'] ?? ''),
                    'entity_id' => (int) ($row['entity_id'] ?? 0),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            $items = [];
        }

        return [
            'unread' => count($items),
            'items' => $items,
        ];
    }
}
