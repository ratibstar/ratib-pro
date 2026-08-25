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
