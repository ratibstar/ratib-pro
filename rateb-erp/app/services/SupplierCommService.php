<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\SupplierCommunication;

final class SupplierCommService
{
    /** @return array{total:int,this_month:int,pending_followups:int,by_supplier:int,distinct_suppliers:int} */
    public function companyStats(int $companyId, int $supplierId = 0): array
    {
        if ($companyId < 1) {
            return ['total' => 0, 'this_month' => 0, 'pending_followups' => 0, 'by_supplier' => 0, 'distinct_suppliers' => 0];
        }
        $model = new SupplierCommunication();
        $base = 'FROM rateb_supplier_communications WHERE company_id = :cid AND is_archived = 0';
        $params = ['cid' => $companyId];
        if ($supplierId > 0) {
            $base .= ' AND supplier_id = :sid';
            $params['sid'] = $supplierId;
        }
        $total = (int) ($model->queryOne('SELECT COUNT(*) AS c ' . $base, $params)['c'] ?? 0);
        $monthParams = $params;
        $monthSql = 'SELECT COUNT(*) AS c ' . $base . ' AND (
            (comm_date IS NOT NULL AND YEAR(comm_date) = YEAR(CURDATE()) AND MONTH(comm_date) = MONTH(CURDATE()))
            OR (comm_date IS NULL AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()))
        )';
        $thisMonth = (int) ($model->queryOne($monthSql, $monthParams)['c'] ?? 0);
        $followSql = 'SELECT COUNT(*) AS c ' . $base . ' AND follow_up_date IS NOT NULL
            AND follow_up_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND comm_status IN (\'new\', \'follow_up\')';
        $pendingFollowups = (int) ($model->queryOne($followSql, $params)['c'] ?? 0);
        $bySupplier = $supplierId > 0 ? $total : 0;
        $distinctSuppliers = (int) ($model->queryOne(
            'SELECT COUNT(DISTINCT supplier_id) AS c ' . $base,
            $params
        )['c'] ?? 0);
        return [
            'total' => $total,
            'this_month' => $thisMonth,
            'pending_followups' => $pendingFollowups,
            'by_supplier' => $bySupplier,
            'distinct_suppliers' => $distinctSuppliers,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function upcomingFollowUps(int $companyId, int $limit = 10): array
    {
        if ($companyId < 1) {
            return [];
        }
        return (new SupplierCommunication())->query(
            'SELECT c.*, s.name AS supplier_name
             FROM rateb_supplier_communications c
             LEFT JOIN rateb_suppliers s ON s.id = c.supplier_id
             WHERE c.company_id = :cid AND c.is_archived = 0
               AND c.follow_up_date IS NOT NULL
               AND c.follow_up_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               AND c.comm_status IN (\'new\', \'follow_up\')
             ORDER BY c.follow_up_date ASC, c.id DESC
             LIMIT ' . max(1, min(20, $limit)),
            ['cid' => $companyId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function historyForSupplier(int $companyId, int $supplierId, int $excludeId = 0, int $limit = 15): array
    {
        if ($companyId < 1 || $supplierId < 1) {
            return [];
        }
        $sql = 'SELECT c.id, c.subject, c.channel, c.comm_date, c.comm_time, c.comm_status,
                       c.follow_up_date, c.created_at, c.is_archived
                FROM rateb_supplier_communications c
                WHERE c.company_id = :cid AND c.supplier_id = :sid';
        $params = ['cid' => $companyId, 'sid' => $supplierId];
        if ($excludeId > 0) {
            $sql .= ' AND c.id != :xid';
            $params['xid'] = $excludeId;
        }
        $sql .= ' ORDER BY COALESCE(c.comm_date, DATE(c.created_at)) DESC, c.id DESC LIMIT ' . max(1, min(30, $limit));
        return (new SupplierCommunication())->query($sql, $params);
    }

    /** @return list<array{supplier_id:int,supplier_name:string,cnt:int}> */
    public function topSuppliersByComms(int $companyId, int $limit = 5): array
    {
        if ($companyId < 1) {
            return [];
        }
        return (new SupplierCommunication())->query(
            'SELECT c.supplier_id, s.name AS supplier_name, COUNT(*) AS cnt
             FROM rateb_supplier_communications c
             LEFT JOIN rateb_suppliers s ON s.id = c.supplier_id
             WHERE c.company_id = :cid AND c.is_archived = 0
             GROUP BY c.supplier_id, s.name
             ORDER BY cnt DESC
             LIMIT ' . max(1, min(10, $limit)),
            ['cid' => $companyId]
        );
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'closed' => 'secondary',
            'follow_up' => 'warning',
            default => 'info',
        };
    }

    public function priorityBadgeClass(string $priority): string
    {
        return match ($priority) {
            'high' => 'danger',
            'low' => 'secondary',
            default => 'primary',
        };
    }

    public function sendStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'sent' => 'success',
            'failed' => 'danger',
            'mailto' => 'info',
            default => 'secondary',
        };
    }

    /** @return list<array<string, mixed>> */
    public function timelineForComm(int $commId, int $companyId): array
    {
        if ($commId < 1 || $companyId < 1) {
            return [];
        }
        return (new SupplierCommunication())->query(
            'SELECT t.*, u.name AS user_name
             FROM rateb_supplier_comm_timeline t
             LEFT JOIN rateb_users u ON u.id = t.created_by
             WHERE t.comm_id = :cid AND t.company_id = :co
             ORDER BY t.id DESC
             LIMIT 50',
            ['cid' => $commId, 'co' => $companyId]
        );
    }

    public function logTimeline(
        int $commId,
        int $companyId,
        string $eventType,
        string $summary,
        string $details = '',
        ?int $userId = null
    ): void {
        if ($commId < 1 || $companyId < 1 || trim($summary) === '') {
            return;
        }
        if ($userId === null) {
            $userId = (int) (\Rateb\App\Core\SessionManager::get('rateb_user_id') ?? 0) ?: null;
        }
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare(
            'INSERT INTO rateb_supplier_comm_timeline (company_id, comm_id, event_type, summary, details, created_by)
             VALUES (:co, :cid, :et, :sum, :det, :uid)'
        )->execute([
            'co' => $companyId,
            'cid' => $commId,
            'et' => substr($eventType, 0, 40),
            'sum' => mb_substr($summary, 0, 255),
            'det' => $details !== '' ? $details : null,
            'uid' => $userId,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function supplierContactProfile(int $companyId, int $supplierId): ?array
    {
        if ($companyId < 1 || $supplierId < 1) {
            return null;
        }
        $row = (new \Rateb\App\Models\Supplier())->queryOne(
            'SELECT id, name, email, phone, address FROM rateb_suppliers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $supplierId, 'cid' => $companyId]
        );
        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function sendViaChannel(array $data, ?string $ccEmail = null, ?string $replyTo = null): array
    {
        $channel = (string) ($data['channel'] ?? '');
        if ($channel === 'email' || trim((string) ($data['supplier_email'] ?? '')) !== '') {
            return $this->sendEmail($data, $ccEmail, $replyTo);
        }
        if ($channel === 'sms' && trim((string) ($data['supplier_phone'] ?? '')) !== '') {
            return ['success' => true, 'status' => 'mailto', 'message' => __('comm_sms_queued')];
        }
        return ['success' => true, 'status' => 'not_sent', 'message' => ''];
    }

    /** @param array<string, mixed> $data */
    public function sendEmail(array $data, ?string $ccEmail = null, ?string $replyTo = null): array
    {
        $email = $this->normalizeRecipientEmail((string) ($data['supplier_email'] ?? ''));
        if ($email === '' || !\Rateb\App\Helpers\Str::isValidEmail($email)) {
            return ['success' => false, 'status' => 'failed', 'message' => __('comm_email_missing')];
        }
        $data['supplier_email'] = $email;
        $mail = new MailService();
        if (!$mail->isSmtpConfigured()) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => __('comm_email_smtp_required'),
                'recipient' => $email,
                'smtp_config_required' => true,
            ];
        }
        $subject = trim((string) ($data['subject'] ?? ''));
        $bodyText = trim((string) ($data['body'] ?? ''));
        $html = '<div dir="auto" style="font-family:Tajawal,sans-serif;line-height:1.6">'
            . nl2br(htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8'))
            . '</div>';
        $cfg = (new MailConfigService())->resolve();
        $fromEmail = trim((string) ($cfg['from_email'] ?? ''));
        $isExternal = $this->isExternalEmail($email);
        $cc = null;
        if (!$isExternal && $ccEmail !== null && $ccEmail !== '' && \Rateb\App\Helpers\Str::isValidEmail($ccEmail) && strcasecmp($ccEmail, $email) !== 0) {
            $cc = $this->normalizeRecipientEmail($ccEmail);
        }
        $reply = null;
        if ($isExternal) {
            $reply = ($fromEmail !== '' && \Rateb\App\Helpers\Str::isValidEmail($fromEmail)) ? $fromEmail : null;
        } elseif ($replyTo !== null && $replyTo !== '' && \Rateb\App\Helpers\Str::isValidEmail($replyTo)) {
            $reply = $this->normalizeRecipientEmail($replyTo);
        } elseif ($fromEmail !== '' && \Rateb\App\Helpers\Str::isValidEmail($fromEmail)) {
            $reply = $fromEmail;
        }

        $sendResult = $mail->sendDetailed(
            $email,
            $subject !== '' ? $subject : __('supplier_comms'),
            $html,
            $reply,
            $cc,
            null
        );
        $sent = (bool) ($sendResult['success'] ?? false);
        $smtpHost = (string) ($sendResult['smtp_host'] ?? $mail->lastSmtpHost() ?? '');

        if (!$sent && ($sendResult['error_code'] ?? '') === 'smtp_not_configured') {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => (string) ($sendResult['error'] ?? __('comm_email_smtp_required')),
                'recipient' => $email,
                'smtp_config_required' => true,
            ];
        }

        $msg = $sent
            ? __('comm_email_sent_to', ['email' => $email]) . ' — ' . __('comm_email_sent_spam')
            : ((string) ($sendResult['error'] ?? '') ?: __('comm_email_failed'));
        if ($sent && $isExternal && $smtpHost !== '') {
            $msg .= ' — SMTP: ' . $smtpHost;
        }
        if (!$sent && $isExternal && ($sendResult['error_code'] ?? '') === 'smtp_auth') {
            $msg .= ' — ' . __('mail_password_env_hint');
        }
        if ($sent && $cc !== null) {
            $msg .= ' — ' . __('comm_email_cc_you', ['email' => $cc]);
        }

        return [
            'success' => $sent,
            'status' => $sent ? 'sent' : 'failed',
            'message' => $msg,
            'recipient' => $email,
            'smtp_host' => $smtpHost,
        ];
    }

    private function isExternalEmail(string $email): bool
    {
        $domain = \Rateb\App\Helpers\Str::emailDomain($email);
        return $domain !== '' && $domain !== 'rateb.sa';
    }

    private function normalizeRecipientEmail(string $email): string
    {
        $email = strtolower(trim($email));
        $email = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $email) ?? $email;
        return trim($email);
    }

    /** Follow-up reminders + no-response alerts (cron). */
    public function processAutomations(): int
    {
        $count = 0;
        $model = new SupplierCommunication();
        $notifier = new NotificationService();
        $today = date('Y-m-d');

        $dueSoon = $model->query(
            "SELECT c.*, s.name AS supplier_name
             FROM rateb_supplier_communications c
             LEFT JOIN rateb_suppliers s ON s.id = c.supplier_id
             WHERE c.is_archived = 0
               AND c.follow_up_date IS NOT NULL
               AND c.follow_up_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)
               AND c.comm_status IN ('new', 'follow_up')
               AND (c.follow_up_reminded_at IS NULL OR c.follow_up_reminded_at < c.follow_up_date)"
        );
        foreach ($dueSoon as $row) {
            $companyId = (int) ($row['company_id'] ?? 0);
            $commId = (int) ($row['id'] ?? 0);
            $uid = (int) ($row['created_by'] ?? 0);
            $title = __('comm_followup_reminder_title');
            $msg = __('comm_followup_reminder_body', [
                'supplier' => (string) ($row['supplier_name'] ?? ''),
                'subject' => (string) ($row['subject'] ?? ''),
                'date' => (string) ($row['follow_up_date'] ?? ''),
            ]);
            if ($uid > 0) {
                $notifier->notifyUser($uid, $companyId, $title, $msg, 'warning', 'comm_followup', 'supplier_communication', $commId);
            } else {
                $notifier->notifyCompany($companyId, $title, $msg, 'warning', 'comm_followup', 'supplier_communication', $commId);
            }
            $model->update($commId, ['follow_up_reminded_at' => $today]);
            $this->logTimeline($commId, $companyId, 'reminder', $title, $msg, $uid > 0 ? $uid : null);
            $count++;
        }

        $overdue = $model->query(
            "SELECT c.*, s.name AS supplier_name
             FROM rateb_supplier_communications c
             LEFT JOIN rateb_suppliers s ON s.id = c.supplier_id
             WHERE c.is_archived = 0
               AND c.follow_up_date IS NOT NULL
               AND c.follow_up_date < CURDATE()
               AND c.comm_status IN ('new', 'follow_up')
               AND c.no_response_notified_at IS NULL"
        );
        foreach ($overdue as $row) {
            $companyId = (int) ($row['company_id'] ?? 0);
            $commId = (int) ($row['id'] ?? 0);
            $uid = (int) ($row['created_by'] ?? 0);
            $title = __('comm_no_response_title');
            $msg = __('comm_no_response_body', [
                'supplier' => (string) ($row['supplier_name'] ?? ''),
                'subject' => (string) ($row['subject'] ?? ''),
            ]);
            if ($uid > 0) {
                $notifier->notifyUser($uid, $companyId, $title, $msg, 'danger', 'comm_no_response', 'supplier_communication', $commId);
            } else {
                $notifier->notifyCompany($companyId, $title, $msg, 'danger', 'comm_no_response', 'supplier_communication', $commId);
            }
            $model->update($commId, [
                'no_response_notified_at' => date('Y-m-d H:i:s'),
                'comm_status' => 'follow_up',
            ]);
            $this->logTimeline($commId, $companyId, 'no_response', $title, $msg, $uid > 0 ? $uid : null);
            $count++;
        }

        return $count;
    }
}
