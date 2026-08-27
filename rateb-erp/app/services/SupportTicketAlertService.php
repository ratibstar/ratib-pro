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
    private const TICKET_NO_PREFIX = 'ST-';

    public const TRIGGER_OPEN = 'support_ticket_open';
    public const TRIGGER_RESPONDED = 'support_ticket_responded';
    public const TRIGGER_REPLY = 'support_ticket_reply';
    public const ENTITY = 'support_ticket';
    private const SEEN_SESSION_KEY = 'rateb_support_ticket_seen_ids';

    /** @var list<string> */
    private const OPEN_STATUSES = ['open'];

    public function platformListAllTickets(): bool
    {
        if (!function_exists('rateb_is_super_admin') || !rateb_is_super_admin()) {
            return false;
        }
        if (array_key_exists('company_id', $_GET)) {
            return (int) ($_GET['company_id'] ?? 0) === 0;
        }
        if (function_exists('rateb_resolve_ops_company_id')) {
            return (int) rateb_resolve_ops_company_id() === 0;
        }

        return true;
    }

    public function superAdminViewsAllTickets(): bool
    {
        return $this->shouldListAllTickets();
    }

    /** Super Admin on rateb.sa lists all tickets (matches global flash alerts). */
    public function shouldListAllTickets(): bool
    {
        // Platform Super Admin always sees every ticket (including mirrored agency messages),
        // even when an ops company is selected in the top bar.
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()
            && function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            return true;
        }

        return $this->platformListAllTickets() || $this->alertsUseGlobalScope();
    }

    public function openCountForViewer(): int
    {
        return $this->unreadOpenCountForViewer();
    }

    public function unreadOpenCountForViewer(): int
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            if ($this->alertsUseGlobalScope()) {
                return $this->countUnreadOpenScoped(null);
            }
            if ($this->superAdminViewsAllTickets()) {
                return $this->countUnreadOpenScoped(null);
            }
            $companyId = $this->resolvedCompanyId();
            if ($companyId > 0) {
                return $this->countUnreadOpenScoped($companyId);
            }

            return $this->countUnreadOpenScoped(null);
        }
        if (function_exists('rateb_can') && rateb_can('settings.manage')) {
            $companyId = $this->resolvedCompanyId();
            if ($companyId > 0) {
                return $this->countUnreadOpenScoped($companyId);
            }
        }
        $userId = (int) SessionManager::get('rateb_user_id', 0);
        if ($userId > 0) {
            return $this->countUnreadOpenForUser($userId);
        }

        return 0;
    }

    /**
     * Open tickets not yet opened/read by the current viewer.
     *
     * @return list<array<string, mixed>>
     */
    public function listUnreadOpenTicketsForViewer(int $limit = 5): array
    {
        $open = $this->listOpenTicketsForViewer(max($limit, 5) * 3);
        $seen = $this->seenTicketIds();
        $unread = [];
        foreach ($open as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $unread[] = $row;
            if (count($unread) >= $limit) {
                break;
            }
        }

        return $unread;
    }

    public function markTicketSeen(int $ticketId): void
    {
        if ($ticketId < 1) {
            return;
        }
        $seen = SessionManager::get(self::SEEN_SESSION_KEY, []);
        if (!is_array($seen)) {
            $seen = [];
        }
        $seen[$ticketId] = $ticketId;
        SessionManager::set(self::SEEN_SESSION_KEY, $seen);
        // Opening a ticket clears persistent reply/open notifications for that ticket.
        $this->markEntityNotificationsRead($ticketId);
    }

    /** Flash alerts for platform super-admin always span all companies. */
    public function alertsUseGlobalScope(): bool
    {
        return function_exists('rateb_is_super_admin') && rateb_is_super_admin()
            && function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host();
    }

    /**
     * Open support tickets visible to the current viewer (for flash alerts).
     *
     * @return list<array<string, mixed>>
     */
    public function listOpenTicketsForViewer(int $limit = 5): array
    {
        $limit = max(1, min(8, $limit));
        $params = [];
        $sql = 'SELECT id, ticket_no, subject, company_id, user_id, status, created_at
                FROM rateb_support_tickets
                WHERE status IN (\'open\')';

        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            if (!$this->alertsUseGlobalScope() && !$this->superAdminViewsAllTickets()) {
                $companyId = $this->resolvedCompanyId();
                if ($companyId > 0) {
                    $sql .= ' AND company_id = :company_id';
                    $params['company_id'] = $companyId;
                }
            }
        } elseif (function_exists('rateb_can') && rateb_can('settings.manage')) {
            $companyId = $this->resolvedCompanyId();
            if ($companyId > 0) {
                $sql .= ' AND company_id = :company_id';
                $params['company_id'] = $companyId;
            }
        } else {
            $userId = (int) SessionManager::get('rateb_user_id', 0);
            if ($userId < 1) {
                return [];
            }
            $sql .= ' AND user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

        try {
            $rows = $this->runUnscopedSelect($sql, $params);
        } catch (\Throwable $e) {
            return [];
        }

        return $this->enrichWithCompanyNames($rows);
    }

    public function supportTicketsListUrl(): string
    {
        $base = function_exists('rateb_app_url')
            ? rateb_app_url('support-tickets')
            : rateb_url(function_exists('rateb_app_route') ? rateb_app_route('support-tickets') : 'admin/support-tickets');
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return function_exists('rateb_url_set_query_param')
                ? rateb_url_set_query_param($base, 'company_id', '0')
                : $base;
        }

        return $base;
    }

    public function ticketEditUrl(int $ticketId): string
    {
        $ticketId = max(0, $ticketId);
        $path = 'support-tickets/' . $ticketId . '/edit';
        if (function_exists('rateb_app_url')) {
            return rateb_app_url($path);
        }
        $prefix = function_exists('rateb_app_route') ? rateb_app_route('support-tickets') : 'admin/support-tickets';

        return rateb_url(rtrim($prefix, '/') . '/' . $ticketId . '/edit');
    }

    /** @return list<array<string, mixed>> */
    public function listTickets(int $limit, int $offset, string $search = ''): array
    {
        if ($this->shouldListAllTickets()) {
            return $this->listAllScoped($limit, $offset, $search, null);
        }
        $model = new SupportTicket();

        return $model->all($limit, $offset, [], $search);
    }

    public function countTickets(string $search = ''): int
    {
        if ($this->shouldListAllTickets()) {
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

        // Raw PDO: Super Admin global list must not inherit Model tenant/admin company filters.
        return $this->runUnscopedSelect($sql, $params);
    }

    public function countAllScoped(string $search, ?int $companyId): int
    {
        [$sql, $params] = $this->baseListSql($search, $companyId, true);
        $rows = $this->runUnscopedSelect($sql, $params);

        return (int) (($rows[0]['c'] ?? 0));
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function runUnscopedSelect(string $sql, array $params): array
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log('SupportTicketAlertService unscoped select: ' . $e->getMessage());

            return [];
        }
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
            $sql .= ' AND (ticket_no LIKE :q1 OR subject LIKE :q2 OR message LIKE :q3)';
            $like = '%' . $search . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
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
            $rows = $this->runUnscopedSelect($sql, $params);
        } catch (\Throwable $e) {
            return 0;
        }

        return (int) (($rows[0]['c'] ?? 0));
    }

    private function countUnreadOpenScoped(?int $companyId): int
    {
        $params = [];
        $sql = 'SELECT id FROM rateb_support_tickets WHERE status IN (\'open\')';
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }
        try {
            $rows = $this->runUnscopedSelect($sql, $params);
        } catch (\Throwable $e) {
            return 0;
        }
        $seen = $this->seenTicketIds();
        $count = 0;
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && !isset($seen[$id])) {
                ++$count;
            }
        }

        return $count;
    }

    private function countUnreadOpenForUser(int $userId): int
    {
        if ($userId < 1) {
            return 0;
        }
        try {
            $rows = (new SupportTicket())->query(
                'SELECT id FROM rateb_support_tickets
                 WHERE user_id = :uid AND status IN (\'open\')',
                ['uid' => $userId]
            );
        } catch (\Throwable $e) {
            return 0;
        }
        $seen = $this->seenTicketIds();
        $count = 0;
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0 && !isset($seen[$id])) {
                ++$count;
            }
        }

        return $count;
    }

    /** @return array<int, int> */
    private function seenTicketIds(): array
    {
        $seen = SessionManager::get(self::SEEN_SESSION_KEY, []);
        if (!is_array($seen)) {
            return [];
        }
        $out = [];
        foreach ($seen as $key => $value) {
            $id = is_int($key) ? $key : (int) $value;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $ticket */
    public function notifyOnCreate(int $ticketId, array $ticket): void
    {
        if ($ticketId < 1) {
            return;
        }
        $companyId = (int) ($ticket['company_id'] ?? 0);
        $senderCompanyId = $this->resolveSenderCompanyId($ticket);
        if ($senderCompanyId > 0) {
            $companyId = $senderCompanyId;
        }
        $companyName = (string) ($ticket['company_name'] ?? $this->resolveCompanyName($companyId));
        $ticketNo = (string) ($ticket['ticket_no'] ?? ('#' . $ticketId));
        $subject = (string) ($ticket['subject'] ?? '');
        $title = __('support_ticket_alert_new_title', [
            'ticket' => $ticketNo,
            'company' => $companyName,
        ]);
        $message = __('support_ticket_alert_new_body', [
            'ticket' => $ticketNo,
            'company' => $companyName,
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

        // Agency / dedicated ERP → mirror into platform rateb.sa for Super Admin alerts.
        try {
            (new SupportTicketPlatformMirrorService())->mirrorNewTicketFromAgency($ticketId, array_merge($ticket, [
                'company_name' => $companyName,
                'ticket_no' => $ticketNo,
                'subject' => $subject,
            ]));
        } catch (\Throwable $e) {
            error_log('support ticket platform mirror: ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $ticket */
    public function notifyOnReply(int $ticketId, array $ticket, string $replyBody): void
    {
        if ($ticketId < 1) {
            return;
        }
        $companyId = (int) ($ticket['company_id'] ?? 0);
        $senderCompanyId = $this->resolveSenderCompanyId($ticket);
        if ($senderCompanyId > 0) {
            $companyId = $senderCompanyId;
        }
        $companyName = (string) ($ticket['company_name'] ?? $this->resolveCompanyName($companyId));
        $ticketNo = (string) ($ticket['ticket_no'] ?? ('#' . $ticketId));
        $preview = mb_strlen($replyBody) > 120 ? (mb_substr($replyBody, 0, 117) . '…') : $replyBody;
        $title = __('support_ticket_alert_reply_title', ['ticket' => $ticketNo]);
        $message = __('support_ticket_alert_reply_body', [
            'ticket' => $ticketNo,
            'company' => $companyName,
            'preview' => $preview,
        ]);
        $notifier = new NotificationService();

        $creatorId = (int) ($ticket['user_id'] ?? 0);
        if ($creatorId > 0) {
            $notifier->notifyUser(
                $creatorId,
                $companyId > 0 ? $companyId : null,
                $title,
                $message,
                'info',
                self::TRIGGER_REPLY,
                self::ENTITY,
                $ticketId
            );
        }

        if ($companyId > 0) {
            $notifier->notifyCompany(
                $companyId,
                $title,
                $message,
                'info',
                self::TRIGGER_REPLY,
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
        $this->markTicketSeen($ticketId);

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

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    public function enrichTicketRows(array $rows): array
    {
        return $this->enrichWithSenderCompanyNames($rows);
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function enrichWithCompanyNames(array $rows): array
    {
        return $this->enrichWithSenderCompanyNames($rows);
    }

    /**
     * Prefer the submitting user's company (الشركة المرسلة) over ticket.company_id.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function enrichWithSenderCompanyNames(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $companyIds = [];
        $userIds = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['company_id'] ?? 0);
            if ($cid > 0) {
                $companyIds[$cid] = $cid;
            }
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0) {
                $userIds[$uid] = $uid;
            }
        }

        $companyNames = $this->loadCompanyNameMap(array_values($companyIds));
        $userSenderCompanies = [];
        if ($userIds !== []) {
            $uids = array_values($userIds);
            $userPh = implode(',', array_fill(0, count($uids), '?'));
            try {
                foreach ((new SupportTicket())->query(
                    'SELECT u.id AS user_id, u.company_id, c.name AS company_name
                     FROM rateb_users u
                     LEFT JOIN rateb_companies c ON c.id = u.company_id
                     WHERE u.id IN (' . $userPh . ')',
                    $uids
                ) as $userRow) {
                    $uid = (int) ($userRow['user_id'] ?? 0);
                    $ucid = (int) ($userRow['company_id'] ?? 0);
                    $uname = trim((string) ($userRow['company_name'] ?? ''));
                    if ($uid > 0 && $ucid > 0) {
                        $userSenderCompanies[$uid] = [
                            'company_id' => $ucid,
                            'company_name' => $uname !== '' ? $uname : ('#' . $ucid),
                        ];
                        $companyNames[$ucid] = $uname !== '' ? $uname : ('#' . $ucid);
                    }
                }
            } catch (\Throwable $e) {
                $userSenderCompanies = [];
            }
        }

        foreach ($rows as &$row) {
            $uid = (int) ($row['user_id'] ?? 0);
            $cid = (int) ($row['company_id'] ?? 0);
            if ($uid > 0 && isset($userSenderCompanies[$uid])) {
                $row['sender_company_id'] = $userSenderCompanies[$uid]['company_id'];
                $row['company_name'] = $userSenderCompanies[$uid]['company_name'];
            } else {
                $row['sender_company_id'] = $cid > 0 ? $cid : 0;
                $name = $companyNames[$cid] ?? ($cid > 0 ? ('#' . $cid) : '');
                if ($name === '' || $name === '—') {
                    $fromMsg = $this->companyNameFromMirroredMessage((string) ($row['message'] ?? ''));
                    if ($fromMsg !== '') {
                        $name = $fromMsg;
                    }
                }
                $row['company_name'] = $name !== '' ? $name : '—';
            }
        }
        unset($row);

        return $rows;
    }

    /** Pull "الشركة: …" from agency-mirrored ticket body when company_id is null. */
    private function companyNameFromMirroredMessage(string $message): string
    {
        if ($message === '') {
            return '';
        }
        if (preg_match('/الشركة:\s*([^\n\r—\-]+)/u', $message, $m)) {
            return trim((string) ($m[1] ?? ''));
        }
        if (preg_match('/Company:\s*([^\n\r]+)/i', $message, $m)) {
            return trim((string) ($m[1] ?? ''));
        }

        return '';
    }

    /** @param list<int> $ids @return array<int, string> */
    private function loadCompanyNameMap(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }
        $names = [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        try {
            foreach ((new SupportTicket())->query(
                'SELECT id, name FROM rateb_companies WHERE id IN (' . $placeholders . ')',
                $ids
            ) as $company) {
                $id = (int) ($company['id'] ?? 0);
                $name = trim((string) ($company['name'] ?? ''));
                if ($id > 0) {
                    $names[$id] = $name !== '' ? $name : ('#' . $id);
                }
            }
        } catch (\Throwable $e) {
            return [];
        }

        return $names;
    }

    /** @param array<string, mixed> $ticket */
    private function resolveSenderCompanyId(array $ticket): int
    {
        $userId = (int) ($ticket['user_id'] ?? 0);
        if ($userId > 0) {
            try {
                $row = (new SupportTicket())->queryOne(
                    'SELECT company_id FROM rateb_users WHERE id = :id LIMIT 1',
                    ['id' => $userId]
                );
                $userCompanyId = (int) ($row['company_id'] ?? 0);
                if ($userCompanyId > 0) {
                    return $userCompanyId;
                }
            } catch (\Throwable $e) {
                // fall through
            }
        }

        return (int) ($ticket['company_id'] ?? 0);
    }

    private function resolveCompanyName(int $companyId): string
    {
        if ($companyId < 1) {
            return '—';
        }
        try {
            $row = (new SupportTicket())->queryOne(
                'SELECT name FROM rateb_companies WHERE id = :id LIMIT 1',
                ['id' => $companyId]
            );
            $name = trim((string) ($row['name'] ?? ''));

            return $name !== '' ? $name : ('#' . $companyId);
        } catch (\Throwable $e) {
            return '#' . $companyId;
        }
    }

    /** ticket_no is globally unique — allocate ST- numbers across all companies. */
    public function generateNextTicketNo(): string
    {
        $prefix = self::TICKET_NO_PREFIX;
        $startPos = strlen($prefix) + 1;
        try {
            $row = (new SupportTicket())->queryOne(
                sprintf(
                    'SELECT MAX(CAST(SUBSTRING(ticket_no, %d) AS UNSIGNED)) AS m
                     FROM rateb_support_tickets WHERE ticket_no LIKE :like',
                    $startPos
                ),
                ['like' => $prefix . '%']
            );
            $next = (int) ($row['m'] ?? 0) + 1;

            for ($attempt = 0; $attempt < 20; $attempt++) {
                $candidate = $prefix . str_pad((string) ($next + $attempt), 4, '0', STR_PAD_LEFT);
                $exists = (new SupportTicket())->queryOne(
                    'SELECT id FROM rateb_support_tickets WHERE ticket_no = :no LIMIT 1',
                    ['no' => $candidate]
                );
                if ($exists === null) {
                    return $candidate;
                }
            }

            return $prefix . str_pad((string) ($next + 20), 4, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            return $prefix . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        }
    }

    public function deleteTicket(int $ticketId): bool
    {
        if ($ticketId < 1) {
            return false;
        }
        $pdo = Database::connection();
        try {
            $pdo->prepare('DELETE FROM rateb_support_ticket_replies WHERE ticket_id = :id')
                ->execute(['id' => $ticketId]);
        } catch (\Throwable $e) {
            // replies table may not exist yet on older deployments
        }
        try {
            $pdo->prepare('DELETE FROM rateb_notifications WHERE entity_type = :et AND entity_id = :id')
                ->execute(['et' => self::ENTITY, 'id' => $ticketId]);
        } catch (\Throwable $e) {
            // best-effort
        }
        $stmt = $pdo->prepare('DELETE FROM rateb_support_tickets WHERE id = :id');
        $stmt->execute(['id' => $ticketId]);

        return $stmt->rowCount() > 0;
    }
}
