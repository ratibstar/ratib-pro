<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Notification;
use Rateb\App\Models\SmsTemplate;

final class NotificationService
{
    public function notifyUser(int $userId, ?int $companyId, string $title, string $message, string $type = 'info', ?string $triggerType = null, ?string $entityType = null, ?int $entityId = null): int
    {
        return (new Notification())->create([
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
    }

    public function notifyCompany(?int $companyId, string $title, string $message, string $type = 'info', ?string $triggerType = null, ?string $entityType = null, ?int $entityId = null): int
    {
        return (new Notification())->create([
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
        if ($userId < 1 || $companyId < 1) {
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
        $companyId = (int) (TenantContext::companyId()
            ?? \Rateb\App\Core\SessionManager::get('rateb_company_id')
            ?? 0);
        if ($companyId < 1 || $userId < 1 || $id < 1) {
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
