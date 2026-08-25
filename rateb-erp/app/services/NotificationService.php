<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Notification;
use Rateb\App\Models\SmsTemplate;

final class NotificationService
{
    private ?MobilePushOutboxService $pushOutbox;

    public function __construct(?MobilePushOutboxService $pushOutbox = null)
    {
        $this->pushOutbox = $pushOutbox;
    }

    public function notifyUser(int $userId, ?int $companyId, string $title, string $message, string $type = 'info', ?string $triggerType = null, ?string $entityType = null, ?int $entityId = null): int
    {
        $cid = $companyId !== null ? (int) $companyId : 0;
        $id = (int) (new Notification())->create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'trigger_type' => $triggerType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'is_read' => 0,
        ]);
        $this->enqueueMobilePush(
            $id,
            $cid,
            $userId,
            $title,
            $message,
            [
                'type' => $type,
                'trigger_type' => $triggerType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ]
        );

        return $id;
    }

    public function notifyCompany(?int $companyId, string $title, string $message, string $type = 'info', ?string $triggerType = null, ?string $entityType = null, ?int $entityId = null): int
    {
        $cid = (int) ($companyId ?? TenantContext::companyId() ?? 0);
        $id = (int) (new Notification())->create([
            'company_id' => $companyId ?? TenantContext::companyId(),
            'user_id' => null,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'trigger_type' => $triggerType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'is_read' => 0,
        ]);
        $this->enqueueMobilePush(
            $id,
            $cid,
            0,
            $title,
            $message,
            [
                'type' => $type,
                'trigger_type' => $triggerType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'broadcast' => true,
            ]
        );

        return $id;
    }

    /**
     * Feature-flagged push outbox only — never blocks in-app create; no email/SMS change.
     *
     * @param array<string,mixed> $data
     */
    private function enqueueMobilePush(
        int $notificationId,
        int $companyId,
        int $userId,
        string $title,
        string $body,
        array $data
    ): void {
        try {
            ($this->pushOutbox ?? new MobilePushOutboxService())->enqueueFromNotification(
                $notificationId,
                $companyId,
                $userId,
                $title,
                $body,
                $data
            );
        } catch (\Throwable $e) {
            // Push outbox is best-effort; in-app notification already committed.
        }
    }

    /**
     * ESS / in-app user visibility (tenant + own + company broadcast only).
     * Broadcast = user_id IS NULL within the same company_id.
     */
    public const VISIBLE_TO_USER_SQL = 'company_id = :cid AND (user_id = :uid OR user_id IS NULL)';

    /**
     * Notifications visible to the authenticated user (own + company broadcasts).
     * Never returns other employees' user-targeted notifications.
     *
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId, int $companyId): array
    {
        if ($userId < 1) {
            return [];
        }
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()
            && function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            return (new Notification())->query(
                'SELECT id, company_id, user_id, title, message, type, trigger_type, entity_type, entity_id, is_read, created_at
                 FROM rateb_notifications
                 WHERE user_id = :uid
                 ORDER BY id DESC LIMIT 100',
                ['uid' => $userId]
            );
        }
        if ($companyId < 1) {
            return [];
        }

        return (new Notification())->query(
            'SELECT id, company_id, user_id, title, message, type, trigger_type, entity_type, entity_id, is_read, created_at
             FROM rateb_notifications
             WHERE ' . self::VISIBLE_TO_USER_SQL . '
             ORDER BY id DESC LIMIT 50',
            ['uid' => $userId, 'cid' => $companyId]
        );
    }

    /** Unread count for ESS dashboard — no full list load. */
    public function countUnreadForUser(int $userId, int $companyId): int
    {
        if ($userId < 1 || $companyId < 1) {
            return 0;
        }
        $row = (new Notification())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_notifications
             WHERE ' . self::VISIBLE_TO_USER_SQL . ' AND (is_read = 0 OR is_read IS NULL)',
            ['uid' => $userId, 'cid' => $companyId]
        );

        return (int) ($row['c'] ?? 0);
    }

    /** Total visible notifications for ESS summary. */
    public function countVisibleForUser(int $userId, int $companyId): int
    {
        if ($userId < 1 || $companyId < 1) {
            return 0;
        }
        $row = (new Notification())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_notifications
             WHERE ' . self::VISIBLE_TO_USER_SQL,
            ['uid' => $userId, 'cid' => $companyId]
        );

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Recent notifications for ESS dashboard.
     *
     * @return list<array<string, mixed>>
     */
    public function listRecentForUser(int $userId, int $companyId, int $limit = 5): array
    {
        if ($userId < 1 || $companyId < 1) {
            return [];
        }
        $safeLimit = max(1, min(20, $limit));

        return (new Notification())->query(
            'SELECT id, company_id, user_id, title, message, type, trigger_type, entity_type, entity_id, is_read, created_at
             FROM rateb_notifications
             WHERE ' . self::VISIBLE_TO_USER_SQL . '
             ORDER BY id DESC LIMIT ' . $safeLimit,
            ['uid' => $userId, 'cid' => $companyId]
        );
    }

    public function markRead(int $id, int $userId): bool
    {
        if ($userId < 1 || $id < 1) {
            return false;
        }
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()
            && function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            $row = (new Notification())->queryOne(
                'SELECT id FROM rateb_notifications WHERE id = :id AND user_id = :uid LIMIT 1',
                ['id' => $id, 'uid' => $userId]
            );
            if (!$row) {
                return false;
            }
            $db = \Rateb\App\Core\Database::connection();
            $db->prepare('UPDATE rateb_notifications SET is_read = 1 WHERE id = :id AND user_id = :uid')
                ->execute(['id' => $id, 'uid' => $userId]);

            return true;
        }
        $companyId = (int) (TenantContext::companyId()
            ?? \Rateb\App\Core\SessionManager::get('rateb_company_id')
            ?? 0);
        if ($companyId < 1) {
            return false;
        }
        $row = (new Notification())->queryOne(
            'SELECT id FROM rateb_notifications
             WHERE id = :id AND ' . self::VISIBLE_TO_USER_SQL . ' LIMIT 1',
            ['id' => $id, 'uid' => $userId, 'cid' => $companyId]
        );
        if (!$row) {
            return false;
        }
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare('UPDATE rateb_notifications SET is_read = 1 WHERE id = :id AND company_id = :cid')
            ->execute(['id' => $id, 'cid' => $companyId]);
        return true;
    }

    /**
     * Unread in-app notifications for global flash alerts (user-targeted only).
     *
     * @param list<string> $excludeTriggers
     * @return list<array<string, mixed>>
     */
    public function listUnreadFlashForUser(int $userId, int $limit = 5, array $excludeTriggers = []): array
    {
        if ($userId < 1) {
            return [];
        }
        $safeLimit = max(1, min(10, $limit));
        $sql = 'SELECT id, company_id, user_id, title, message, type, trigger_type, entity_type, entity_id, is_read, created_at
                FROM rateb_notifications
                WHERE user_id = :uid AND (is_read = 0 OR is_read IS NULL)';
        $params = ['uid' => $userId];
        $excludeTriggers = array_values(array_filter(array_map('strval', $excludeTriggers)));
        if ($excludeTriggers !== []) {
            $parts = [];
            foreach ($excludeTriggers as $i => $trigger) {
                $key = 'ex' . $i;
                $parts[] = ':' . $key;
                $params[$key] = $trigger;
            }
            $sql .= ' AND (trigger_type IS NULL OR trigger_type NOT IN (' . implode(',', $parts) . '))';
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $safeLimit;

        return (new Notification())->query($sql, $params);
    }

    /**
     * Human-readable columns for the notifications index (company name, ticket no).
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function enrichRowsForDisplay(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $companyIds = [];
        $ticketIds = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['company_id'] ?? 0);
            if ($cid > 0) {
                $companyIds[$cid] = $cid;
            }
            if ((string) ($row['entity_type'] ?? '') === SupportTicketAlertService::ENTITY) {
                $tid = (int) ($row['entity_id'] ?? 0);
                if ($tid > 0) {
                    $ticketIds[$tid] = $tid;
                }
            }
        }

        $companyNames = [];
        if ($companyIds !== []) {
            $ids = array_values($companyIds);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            try {
                foreach ((new Notification())->query(
                    'SELECT id, name FROM rateb_companies WHERE id IN (' . $placeholders . ')',
                    $ids
                ) as $company) {
                    $companyNames[(int) ($company['id'] ?? 0)] = (string) ($company['name'] ?? '');
                }
            } catch (\Throwable $e) {
                $companyNames = [];
            }
        }

        $ticketNos = [];
        if ($ticketIds !== []) {
            $ids = array_values($ticketIds);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            try {
                foreach ((new Notification())->query(
                    'SELECT id, ticket_no FROM rateb_support_tickets WHERE id IN (' . $placeholders . ')',
                    $ids
                ) as $ticket) {
                    $ticketNos[(int) ($ticket['id'] ?? 0)] = (string) ($ticket['ticket_no'] ?? '');
                }
            } catch (\Throwable $e) {
                $ticketNos = [];
            }
        }

        foreach ($rows as &$row) {
            $cid = (int) ($row['company_id'] ?? 0);
            $row['company_display'] = $companyNames[$cid] ?? ($cid > 0 ? ('#' . $cid) : '—');
            $row['ticket_no'] = '—';
            if ((string) ($row['entity_type'] ?? '') === SupportTicketAlertService::ENTITY) {
                $tid = (int) ($row['entity_id'] ?? 0);
                if ($tid > 0 && isset($ticketNos[$tid]) && $ticketNos[$tid] !== '') {
                    $row['ticket_no'] = $ticketNos[$tid];
                }
            }
        }
        unset($row);

        return $rows;
    }

    /** Mark all notifications visible to the user as read (tenant-scoped). */
    public function markAllReadForUser(int $userId, int $companyId): int
    {
        if ($userId < 1 || $companyId < 1) {
            return 0;
        }
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare(
            'UPDATE rateb_notifications SET is_read = 1
             WHERE company_id = :cid AND is_read = 0 AND (user_id = :uid OR user_id IS NULL)'
        );
        $stmt->execute(['cid' => $companyId, 'uid' => $userId]);

        return (int) $stmt->rowCount();
    }

    public function queueEmail(string $recipient, string $subject, string $body, string $status = 'pending'): void
    {
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare(
            'INSERT INTO rateb_notification_queue (company_id, channel, recipient, subject, body, status, sent_at, next_retry_at)
             VALUES (:cid, :ch, :to, :sub, :body, :st, :sent, :next)'
        )->execute([
            'cid' => TenantContext::companyId(),
            'ch' => 'email',
            'to' => $recipient,
            'sub' => $subject,
            'body' => $body,
            'st' => $status === 'sent' ? 'sent' : ($status === 'failed' ? 'failed' : 'pending'),
            'sent' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
            'next' => $status === 'pending' ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public function sendSms(string $phone, string $slug, array $vars = []): bool
    {
        $tpl = (new SmsTemplate())->queryOne('SELECT body FROM rateb_sms_templates WHERE slug = :s AND is_active = 1 LIMIT 1', ['s' => $slug]);
        if (!$tpl) {
            return false;
        }
        $body = (string) $tpl['body'];
        foreach ($vars as $k => $v) {
            $body = str_replace('{' . $k . '}', (string) $v, $body);
        }
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare(
            'INSERT INTO rateb_notification_queue (company_id, channel, recipient, body, status, next_retry_at)
             VALUES (:cid, :ch, :to, :body, :st, NOW())'
        )->execute([
            'cid' => TenantContext::companyId(),
            'ch' => 'sms',
            'to' => $phone,
            'body' => $body,
            'st' => 'pending',
        ]);
        return true;
    }

    public function triggerLowStock(int $companyId, string $itemName, float $quantity): void
    {
        $title = __('low_stock_alert');
        $msg = __('low_stock_message', ['item' => $itemName, 'qty' => (string) $quantity]);
        $this->notifyCompany($companyId, $title, $msg, 'warning', 'low_stock');
        (new EmailAlertService())->sendLowStock($companyId, $itemName, $quantity);
    }

    public function triggerContractExpiry(int $companyId, string $contractNo, string $endDate, int $contractId = 0): void
    {
        $this->notifyCompany(
            $companyId,
            __('contract_expiry_alert'),
            __('contract_expiry_message', ['no' => $contractNo, 'date' => $endDate]),
            'warning',
            'contract_expiry',
            'contract',
            $contractId > 0 ? $contractId : null
        );
        (new EmailAlertService())->sendContractExpiry($companyId, $contractNo, $endDate);
    }

    public function triggerApproval(int $userId, int $companyId, string $entityType, int $entityId): void
    {
        $this->notifyUser($userId, $companyId, __('approval_required'), __('approval_required_message', ['type' => $entityType]), 'info', 'approval', $entityType, $entityId);
        (new EmailAlertService())->sendApprovalRequest($userId, $companyId, $entityType, $entityId);
    }

    /** Broadcast to platform super-admins when a record enters the oversight approval queue. */
    public function notifyOversightPending(int $companyId, string $entityLabel, string $entityType, int $entityId): void
    {
        if ($entityId < 1) {
            return;
        }
        try {
            $db = \Rateb\App\Core\Database::connection();
            $stmt = $db->query('SELECT id FROM rateb_users WHERE is_super_admin = 1 AND status = \'active\'');
            if ($stmt === false) {
                return;
            }
            $title = __('oversight_approval_pending');
            $message = __('oversight_approval_pending_message', ['entity' => $entityLabel]);
            while ($row = $stmt->fetch()) {
                $uid = (int) ($row['id'] ?? 0);
                if ($uid > 0) {
                    $this->notifyUser($uid, $companyId, $title, $message, 'warning', 'oversight_approval', $entityType, $entityId);
                }
            }
        } catch (\Throwable $e) {
            // Notifications are best-effort.
        }
    }

    public function triggerMaintenanceDue(int $companyId, string $deviceName, string $dueDate): void
    {
        $this->notifyCompany($companyId, __('maintenance_due_alert'), __('maintenance_due_message', ['device' => $deviceName, 'date' => $dueDate]), 'info', 'maintenance_due');
        (new EmailAlertService())->sendMaintenanceDue($companyId, $deviceName, $dueDate);
    }
}
