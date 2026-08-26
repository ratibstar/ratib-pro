<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Response;
use Rateb\App\Services\ErpSystemAlertService;
use Rateb\App\Services\SupportTicketAlertService;

/** JSON poll + mark-seen for live support-ticket flash alerts. */
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

        // Platform SA: keep agency mirrors fresh so flash + list stay in sync without waiting for index visit.
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()
            && function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()
            && (!function_exists('rateb_is_agency_erp_host') || !rateb_is_agency_erp_host())) {
            $lastPull = (int) \Rateb\App\Core\SessionManager::get('rateb_support_ticket_agency_pull_at', 0);
            if ($lastPull < 1 || (time() - $lastPull) >= 8) {
                try {
                    (new \Rateb\App\Services\SupportTicketPlatformMirrorService())->pullOpenTicketsFromAgencies(15);
                    \Rateb\App\Core\SessionManager::set('rateb_support_ticket_agency_pull_at', time());
                } catch (\Throwable $e) {
                    error_log('support ticket alerts poll pull: ' . $e->getMessage());
                }
            }
        }

        // Agency: pull Super Admin replies/status into local open tickets (reverse sync backup).
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            $lastInbound = (int) \Rateb\App\Core\SessionManager::get('rateb_support_ticket_platform_pull_at', 0);
            if ($lastInbound < 1 || (time() - $lastInbound) >= 8) {
                try {
                    $mirror = new \Rateb\App\Services\SupportTicketPlatformMirrorService();
                    $rows = (new \Rateb\App\Models\SupportTicket())->query(
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
                    \Rateb\App\Core\SessionManager::set('rateb_support_ticket_platform_pull_at', time());
                } catch (\Throwable $e) {
                    error_log('support ticket alerts agency inbound: ' . $e->getMessage());
                }
            }
        }

        $count = $svc->unreadOpenCountForViewer();
        $tickets = $svc->listUnreadOpenTicketsForViewer(5);
        $alert = (new ErpSystemAlertService())->buildSupportTicketAlert($count, $tickets);

        Response::json([
            'ok' => true,
            'count' => $count,
            'alert' => $alert,
        ]);
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

        Response::json([
            'ok' => true,
            'count' => $svc->unreadOpenCountForViewer(),
            'alert' => (new ErpSystemAlertService())->buildSupportTicketAlert(
                $svc->unreadOpenCountForViewer(),
                $svc->listUnreadOpenTicketsForViewer(5)
            ),
        ]);
    }
}
