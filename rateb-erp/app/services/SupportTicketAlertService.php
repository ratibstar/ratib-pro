<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\SupportTicket;

/**
 * Support ticket counts, cross-company platform listing, and alert notifications.
 */
final class SupportTicketAlertService
{
    public const TRIGGER_OPEN = 'support_ticket_open';
    public const TRIGGER_RESPONDED = 'support_ticket_responded';
    public const ENTITY = 'support_ticket';

    /** @var list<string> */
    private const OPEN_STATUSES = ['open'];

    public function platformListAllTickets(): bool
    {
        if (!function_exists('rateb_is_super_admin') || !rateb_is_super_admin()) {
            return false;
        }
        if (!function_exists('rateb_is_platform_oversight_host') || !rateb_is_platform_oversight_host()) {
            return false;
        }
        if (array_key_exists('company_id', $_GET)) {
            return (int) ($_GET['company_id'] ?? 0) === 0;
        }

        return true;
    }

    public function openCountForViewer(): int
    {
        if ($this->platformListAllTickets()) {
            return $this->countOpenGlobally();
        }
        if (function_exists('rateb_can') && rateb_can('settings.manage')) {
            $companyId = $this->resolvedCompanyId();
            if ($companyId > 0) {
                return $this->countOpenForCompany($companyId);
            }
        }
        $userId = (int) SessionManager::get('rateb_user_id', 0);
        if ($userId > 0) {
            return $this->countOpenForUser($userId);
        }

        return 0;
    }

    public function supportTicketsListUrl(): string
    {
        $route = function_exists('rateb_app_route') ? rateb_app_route('support-tickets') : 'admin/support-tickets';
        if ($this->platformListAllTickets() || (
            function_exists('rateb_is_platform_oversight_host')
            && rateb_is_platform_oversight_host()
            && function_exists('rateb_is_super_admin')
            && rateb_is_super_admin()
        )) {
            return rateb_url($route . '?company_id=0');
        }

        return function_exists('rateb_app_url') ? rateb_app_url('support-tickets') : rateb_url($route);
    }

    /** @return list<array<string, mixed>> */
    public function listTickets(int $limit, int $offset, string $search = ''): array
    {
        if ($this->platformListAllTickets()) {
            return $this->listAllScoped($limit, $offset, $search, null);
        }
        $model = new SupportTicket();

        return $model->all($limit, $offset, [], $search);
    }

    public function countTickets(string $search = ''): int
    {
        if ($this->platformListAllTickets()) {
            return $this->countAllScoped($search, null);
        }

        return (new SupportTicket())->count([], $search);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAllScoped(int $limit, int $offset, string $search, ?int $companyId): array
    {
        [$sql, $params] = $this->baseListSql($search, $companyId);
        $safeLimit = max(1, min(500, $limit));
        $safeOffset = max(0, $offset);
        $sql .= ' ORDER BY id DESC LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset;

        return (new SupportTicket())->query($sql, $params);
    }

    public function countAllScoped(string $search, ?int $companyId): int
    {
        [$sql, $params] = $this->baseListSql($search, $companyId, true);

        return (int) ((new SupportTicket())->queryOne($sql, $params)['c'] ?? 0);
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function baseListSql(string $search, ?int $companyId, bool $countOnly = false): array
    {
        $params = [];
        if ($countOnly) {
            $sql = 'SELECT COUNT(*) AS c FROM rateb_support_tickets WHERE 1=1';
        } else {
            $sql = 'SELECT * FROM rateb_support_tickets WHERE 1=1';
        }
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }
        $search = trim($search);
        if ($search !== '') {
            $sql .= ' AND (ticket_no LIKE :q OR subject LIKE :q OR message LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        return [$sql, $params];
    }

    public function countOpenGlobally(): int
    {
        return $this->countOpenScoped(null);
    }

    public function countOpenForCompany(int $companyId): int
    {
        if ($companyId < 1) {
            return 0;
        }

        return $this->countOpenScoped($companyId);
    }

    public function countOpenForUser(int $userId): int
    {
        if ($userId < 1) {
            return 0;
        }
        try {
            $row = (new SupportTicket())->queryOne(
                'SELECT COUNT(*) AS c FROM rateb_support_tickets
                 WHERE user_id = :uid AND status IN (\'open\')',
                ['uid' => $userId]
            );
        } catch (\Throwable $e) {
            return 0;
        }

        return (int) ($row['c'] ?? 0);
    }

    private function countOpenScoped(?int $companyId): int
    {
        $params = [];
        $sql = 'SELECT COUNT(*) AS c FROM rateb_support_tickets WHERE status IN (\'open\')';
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }
        try {
            $row = (new SupportTicket())->queryOne($sql, $params);
        } catch (\Throwable $e) {
            return 0;
        }

        return (int) ($row['c'] ?? 0);
    }

    /** @param array<string, mixed> $ticket */
    public function notifyOnCreate(int $ticketId, array $ticket): void
    {
        if ($ticketId < 1) {
            return;
        }
        $companyId = (int) ($ticket['company_id'] ?? 0);
        $ticketNo = (string) ($ticket['ticket_no'] ?? ('#' . $ticketId));
        $subject = (string) ($ticket['subject'] ?? '');
        $title = __('support_ticket_alert_new_title');
        $message = __('support_ticket_alert_new_body', [
            'ticket' => $ticketNo,
            'subject' => $subject,
        ]);
        $notifier = new NotificationService();

        foreach ($this->superAdminUserIds() as $adminId) {
            if ($this->hasOpenNotification($adminId, $ticketId)) {
                continue;
            }
            $notifier->notifyUser(
                $adminId,
                $companyId > 0 ? $companyId : null,
                $title,
                $message,
                'warning',
                self::TRIGGER_OPEN,
                self::ENTITY,
                $ticketId
            );
        }

        $creatorId = (int) ($ticket['user_id'] ?? 0);
        if ($creatorId > 0 && !$this->hasOpenNotification($creatorId, $ticketId)) {
            $notifier->notifyUser(
                $creatorId,
                $companyId > 0 ? $companyId : null,
                $title,
                __('support_ticket_alert_created_body', ['ticket' => $ticketNo]),
                'info',
                self::TRIGGER_OPEN,
                self::ENTITY,
                $ticketId
            );
        }
    }

    /** @param array<string, mixed>|null $old @param array<string, mixed> $data */
    public function handleStatusChange(int $ticketId, ?array $old, array $data): void
    {
        $oldStatus = (string) ($old['status'] ?? '');
        $newStatus = (string) ($data['status'] ?? $oldStatus);
        if ($ticketId < 1 || $newStatus === '' || $oldStatus === $newStatus) {
            return;
        }
        $wasOpen = in_array($oldStatus, self::OPEN_STATUSES, true);
        $isOpen = in_array($newStatus, self::OPEN_STATUSES, true);
        if (!$wasOpen || $isOpen) {
            return;
        }

        $this->markEntityNotificationsRead($ticketId);

        $creatorId = (int) ($old['user_id'] ?? $data['user_id'] ?? 0);
        $companyId = (int) ($old['company_id'] ?? $data['company_id'] ?? 0);
        $ticketNo = (string) ($data['ticket_no'] ?? $old['ticket_no'] ?? ('#' . $ticketId));
        if ($creatorId < 1) {
            return;
        }

        (new NotificationService())->notifyUser(
            $creatorId,
            $companyId > 0 ? $companyId : null,
            __('support_ticket_alert_responded_title'),
            __('support_ticket_alert_responded_body', [
                'ticket' => $ticketNo,
                'status' => __($newStatus),
            ]),
            'success',
            self::TRIGGER_RESPONDED,
            self::ENTITY,
            $ticketId
        );
    }

    public function markEntityNotificationsRead(int $ticketId): void
    {
        if ($ticketId < 1) {
            return;
        }
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'UPDATE rateb_notifications SET is_read = 1
                 WHERE entity_type = :et AND entity_id = :eid AND (is_read = 0 OR is_read IS NULL)'
            );
            $stmt->execute(['et' => self::ENTITY, 'eid' => $ticketId]);
        } catch (\Throwable $e) {
            // best-effort
        }
    }

    private function hasOpenNotification(int $userId, int $ticketId): bool
    {
        try {
            $row = (new SupportTicket())->queryOne(
                'SELECT id FROM rateb_notifications
                 WHERE user_id = :uid AND trigger_type = :tt AND entity_type = :et AND entity_id = :eid
                 LIMIT 1',
                [
                    'uid' => $userId,
                    'tt' => self::TRIGGER_OPEN,
                    'et' => self::ENTITY,
                    'eid' => $ticketId,
                ]
            );
        } catch (\Throwable $e) {
            return false;
        }

        return $row !== null;
    }

    /** @return list<int> */
    private function superAdminUserIds(): array
    {
        try {
            $stmt = Database::connection()->query(
                "SELECT id FROM rateb_users WHERE is_super_admin = 1 AND status = 'active' ORDER BY id ASC LIMIT 50"
            );
            if ($stmt === false) {
                return [];
            }
            $ids = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }

            return $ids;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function resolvedCompanyId(): int
    {
        $companyId = (int) (TenantContext::companyId() ?? 0);
        if ($companyId > 0) {
            return $companyId;
        }
        if (function_exists('rateb_resolve_ops_company_id')) {
            return (int) rateb_resolve_ops_company_id();
        }

        return 0;
    }
}
