<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Company;
use Rateb\App\Models\Subscription;
use Rateb\App\Models\User;

final class SaaSAutomationService
{
    public function processSubscriptionExpiry(): int
    {
        $count = 0;
        $subs = (new Subscription())->query(
            "SELECT s.*, c.name AS company_name FROM rateb_subscriptions s
             JOIN rateb_companies c ON c.id = s.company_id
             WHERE s.status IN ('active','trial') AND s.ends_at IS NOT NULL AND s.ends_at < CURDATE()"
        );
        foreach ($subs as $sub) {
            $subId = (int) $sub['id'];
            $companyId = (int) $sub['company_id'];
            $admin = (new User())->queryOne(
                'SELECT email, name FROM rateb_users WHERE company_id = :cid AND is_super_admin = 0 AND status = \'active\' ORDER BY id LIMIT 1',
                ['cid' => $companyId]
            );
            Database::connection()->prepare(
                "UPDATE rateb_subscriptions SET status = 'expired' WHERE id = :id"
            )->execute(['id' => $subId]);
            (new Company())->update($companyId, ['status' => 'suspended']);
            TenantContext::setCompanyId($companyId);
            (new NotificationService())->notifyCompany(
                $companyId,
                __('subscription_expired'),
                __('subscription_expired_message', ['date' => (string) $sub['ends_at']]),
                'danger',
                'subscription_expiry',
                'company',
                $companyId
            );
            $email = (string) ($admin['email'] ?? '');
            if ($email !== '') {
                (new MailService())->sendTemplateAsync($email, 'subscription_expiring', [
                    'name' => (string) ($admin['name'] ?? ''),
                    'date' => (string) $sub['ends_at'],
                ]);
            }
            (new AuditService())->log('subscription_expired', 'company', $companyId, ['subscription_id' => $subId]);
            $count++;
        }
        return $count;
    }

    public function processTrialReminders(): int
    {
        $days = AutomationSettings::getInt('trial_reminder_days', 7);
        return $this->sendExpiryReminders('trial', $days, 'trial_expiring');
    }

    public function processSubscriptionReminders(): int
    {
        $days = AutomationSettings::getInt('subscription_reminder_days', 14);
        return $this->sendExpiryReminders('active', $days, 'subscription_expiring');
    }

    private function sendExpiryReminders(string $status, int $days, string $template): int
    {
        $count = 0;
        $rows = (new Subscription())->query(
            "SELECT s.*, u.email, u.name FROM rateb_subscriptions s
             LEFT JOIN rateb_users u ON u.company_id = s.company_id AND u.is_super_admin = 0 AND u.status = 'active'
             WHERE s.status = :st AND s.ends_at IS NOT NULL
               AND s.ends_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :d DAY)
             GROUP BY s.id",
            ['st' => $status, 'd' => $days]
        );
        $notifier = new NotificationService();
        foreach ($rows as $row) {
            $companyId = (int) $row['company_id'];
            $exists = (new \Rateb\App\Models\Notification())->queryOne(
                'SELECT id FROM rateb_notifications WHERE company_id = :cid AND trigger_type = :tt
                 AND entity_type = :et AND entity_id = :eid AND created_at >= DATE_SUB(NOW(), INTERVAL 3 DAY) LIMIT 1',
                ['cid' => $companyId, 'tt' => $status . '_reminder', 'et' => 'subscription', 'eid' => (int) $row['id']]
            );
            if ($exists) {
                continue;
            }
            $notifier->notifyCompany(
                $companyId,
                __('subscription_reminder'),
                __('subscription_reminder_message', ['date' => (string) $row['ends_at']]),
                'warning',
                $status . '_reminder',
                'subscription',
                (int) $row['id']
            );
            $email = (string) ($row['email'] ?? '');
            if ($email !== '') {
                (new MailService())->sendTemplateAsync($email, $template, [
                    'name' => (string) ($row['name'] ?? ''),
                    'date' => (string) $row['ends_at'],
                ]);
            }
            $count++;
        }
        return $count;
    }

    public function processTrialConversion(): int
    {
        $count = 0;
        $rows = (new Subscription())->query(
            "SELECT * FROM rateb_subscriptions WHERE status = 'trial' AND ends_at IS NOT NULL AND ends_at < CURDATE()"
        );
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            Database::connection()->prepare(
                "UPDATE rateb_subscriptions SET status = 'expired' WHERE id = :id"
            )->execute(['id' => $id]);
            $companyId = (int) $row['company_id'];
            (new Company())->update($companyId, ['status' => 'suspended']);
            (new AuditService())->log('trial_expired', 'company', $companyId);
            $count++;
        }
        return $count;
    }
}
