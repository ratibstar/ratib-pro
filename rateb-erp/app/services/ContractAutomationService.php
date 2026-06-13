<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Contract;

final class ContractAutomationService
{
    public function processStatusUpdates(): int
    {
        $db = Database::connection();
        $expired = $db->exec(
            "UPDATE rateb_contracts SET status = 'expired'
             WHERE status IN ('active','draft') AND end_date IS NOT NULL AND end_date < CURDATE()"
        );
        $activated = $db->exec(
            "UPDATE rateb_contracts SET status = 'active'
             WHERE status = 'draft' AND start_date IS NOT NULL AND start_date <= CURDATE()
               AND (end_date IS NULL OR end_date >= CURDATE())"
        );
        return (int) $expired + (int) $activated;
    }

    public function processRenewalReminders(): int
    {
        $count = 0;
        $companies = (new \Rateb\App\Models\Company())->query(
            "SELECT id FROM rateb_companies WHERE status = 'active'"
        );
        foreach ($companies as $c) {
            $cid = (int) $c['id'];
            TenantContext::setCompanyId($cid);
            $contracts = (new Contract())->query(
                "SELECT * FROM rateb_contracts
                 WHERE company_id = :cid AND status IN ('active','draft')
                   AND end_date IS NOT NULL
                   AND end_date <= DATE_ADD(CURDATE(), INTERVAL COALESCE(alert_days, 30) DAY)",
                ['cid' => $cid]
            );
            foreach ($contracts as $contract) {
                $alertDays = (int) ($contract['alert_days'] ?? 30);
                $endDate = (string) ($contract['end_date'] ?? '');
                if ($endDate === '') {
                    continue;
                }
                $daysLeft = (int) floor((strtotime($endDate) - strtotime('today')) / 86400);
                if ($daysLeft > $alertDays) {
                    continue;
                }
                $id = (int) $contract['id'];
                $exists = (new \Rateb\App\Models\Notification())->queryOne(
                    'SELECT id FROM rateb_notifications WHERE company_id = :cid AND trigger_type = :tt
                     AND entity_type = :et AND entity_id = :eid AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) LIMIT 1',
                    ['cid' => $cid, 'tt' => 'contract_expiry', 'et' => 'contract', 'eid' => $id]
                );
                if ($exists) {
                    continue;
                }
                (new NotificationService())->triggerContractExpiry(
                    $cid,
                    (string) ($contract['contract_no'] ?? ''),
                    $endDate,
                    $id
                );
                (new EmailAlertService())->sendContractExpiry(
                    $cid,
                    (string) ($contract['contract_no'] ?? ''),
                    $endDate
                );
                $count++;
            }
        }
        TenantContext::setCompanyId(null);
        return $count;
    }

    public function appendSignatureEvent(int $contractId, string $actor, string $action): void
    {
        $contract = (new Contract())->find($contractId);
        if (!$contract) {
            return;
        }
        $trail = json_decode((string) ($contract['signature_trail'] ?? '[]'), true);
        if (!is_array($trail)) {
            $trail = [];
        }
        $trail[] = [
            'actor' => $actor,
            'action' => $action,
            'at' => date('c'),
            'placeholder' => true,
        ];
        $status = count($trail) > 0 ? 'partial' : 'pending';
        (new Contract())->update($contractId, [
            'signature_trail' => json_encode($trail, JSON_UNESCAPED_UNICODE),
            'signature_status' => $status,
        ]);
    }
}
