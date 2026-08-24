<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;

/**
 * Global ERP flash alerts (support tickets + unread notifications) for all layout pages.
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
        $alerts = [];
        $supportSvc = new SupportTicketAlertService();
        $openTickets = $supportSvc->openCountForViewer();
        if ($openTickets > 0) {
            $isPlatform = function_exists('rateb_is_super_admin') && rateb_is_super_admin();
            $alerts[] = [
                'key' => 'support_tickets_open',
                'severity' => 'info',
                'title' => (string) __('support_ticket_banner_title', ['count' => (string) $openTickets]),
                'message' => (string) ($isPlatform
                    ? __('support_ticket_banner_platform_hint')
                    : __('support_ticket_banner_tenant_hint')),
                'url' => $supportSvc->supportTicketsListUrl(),
                'action_label' => (string) __('support_ticket_banner_action'),
                'persistent' => true,
                'pulse' => true,
                'icon' => 'fa-life-ring',
            ];
        }

        $userId = (int) SessionManager::get('rateb_user_id', 0);
        if ($userId > 0) {
            $exclude = $openTickets > 0 ? [SupportTicketAlertService::TRIGGER_OPEN] : [];
            $rows = (new NotificationService())->listUnreadFlashForUser($userId, 4, $exclude);
            foreach ($rows as $row) {
                $alerts[] = $this->notificationToAlert($row, $supportSvc);
            }
        }

        return $alerts;
    }

    /** @param array<string, mixed> $row @return ErpFlashAlert */
    private function notificationToAlert(array $row, SupportTicketAlertService $supportSvc): array
    {
        $trigger = (string) ($row['trigger_type'] ?? '');
        $entityType = (string) ($row['entity_type'] ?? '');
        $type = (string) ($row['type'] ?? 'info');
        $severity = match ($type) {
            'danger', 'error' => 'danger',
            'warning' => 'warning',
            'success' => 'success',
            default => 'info',
        };
        if ($trigger === SupportTicketAlertService::TRIGGER_OPEN) {
            $severity = 'warning';
        }

        $persistent = in_array($trigger, [
            SupportTicketAlertService::TRIGGER_OPEN,
        ], true);

        return [
            'key' => 'notif_' . (int) ($row['id'] ?? 0),
            'severity' => $severity,
            'title' => (string) ($row['title'] ?? ''),
            'message' => (string) ($row['message'] ?? ''),
            'url' => $this->resolveNotificationUrl($row, $supportSvc),
            'action_label' => (string) __('system_flash_alert_view'),
            'persistent' => $persistent,
            'pulse' => true,
            'icon' => $entityType === SupportTicketAlertService::ENTITY ? 'fa-life-ring' : 'fa-bell',
        ];
    }

    /** @param array<string, mixed> $row */
    private function resolveNotificationUrl(array $row, SupportTicketAlertService $supportSvc): string
    {
        $entityType = (string) ($row['entity_type'] ?? '');
        if ($entityType === SupportTicketAlertService::ENTITY) {
            return $supportSvc->supportTicketsListUrl();
        }

        return function_exists('rateb_app_url') ? rateb_app_url('notifications') : rateb_url('admin/notifications');
    }
}
