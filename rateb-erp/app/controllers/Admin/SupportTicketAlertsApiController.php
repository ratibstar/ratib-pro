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
        if ($ticketId > 0 && function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            try {
                (new SupportTicketPlatformMirrorService())->pullPlatformUpdatesIntoAgencyTicket($ticketId);
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        $count = $svc->unreadOpenCountForViewer();
        $tickets = $svc->listUnreadOpenTicketsForViewer(5);
        $alert = (new ErpSystemAlertService())->buildSupportTicketAlert($count, $tickets);

        $payload = [
            'ok' => true,
            'count' => $count,
            'alert' => $alert,
            'activity_token' => $this->globalActivityToken($count),
            'notifications' => $this->notificationsPayload(),
            'ticket' => null,
            'server_time' => date('c'),
        ];

        if ($ticketId > 0) {
            $payload['ticket'] = (new SupportTicketReplyService())->liveSnapshot($ticketId);
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

        (new SupportTicketAlertService())->markTicketSeen($ticketId);
        $svc = new SupportTicketAlertService();
        $count = $svc->unreadOpenCountForViewer();

        Response::json([
            'ok' => true,
            'count' => $count,
            'alert' => (new ErpSystemAlertService())->buildSupportTicketAlert(
                $count,
                $svc->listUnreadOpenTicketsForViewer(5)
            ),
            'activity_token' => $this->globalActivityToken($count),
            'notifications' => $this->notificationsPayload(),
        ]);
    }

    private function runBackgroundSync(): void
    {
        // Platform SA: keep agency mirrors fresh.
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()
            && function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()
            && (!function_exists('rateb_is_agency_erp_host') || !rateb_is_agency_erp_host())) {
            $lastPull = (int) SessionManager::get('rateb_support_ticket_agency_pull_at', 0);
            if ($lastPull < 1 || (time() - $lastPull) >= 3) {
                try {
                    (new SupportTicketPlatformMirrorService())->pullOpenTicketsFromAgencies(15);
                    SessionManager::set('rateb_support_ticket_agency_pull_at', time());
                } catch (\Throwable $e) {
                    error_log('support ticket alerts poll pull: ' . $e->getMessage());
                }
            }
        }

        // Agency: pull Super Admin replies/status into local open tickets.
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            $lastInbound = (int) SessionManager::get('rateb_support_ticket_platform_pull_at', 0);
            if ($lastInbound < 1 || (time() - $lastInbound) >= 3) {
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
        try {
            $t = (new SupportTicket())->queryOne(
                'SELECT MAX(id) AS m FROM rateb_support_tickets'
            );
            $maxTicketId = (int) ($t['m'] ?? 0);
        } catch (\Throwable $e) {
            $maxTicketId = 0;
        }
        try {
            $r = (new SupportTicket())->queryOne(
                'SELECT MAX(id) AS m FROM rateb_support_ticket_replies'
            );
            $maxReplyId = (int) ($r['m'] ?? 0);
        } catch (\Throwable $e) {
            $maxReplyId = 0;
        }

        return $openCount . ':' . $maxTicketId . ':' . $maxReplyId;
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
            $rows = $notifier->listUnreadFlashForUser($userId, 5);
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
