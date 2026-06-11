<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Company;

final class CronService
{
    /** @return array<string, int> */
    public function runAll(): array
    {
        $stats = [
            'queue' => (new QueueWorkerService())->processPending(100),
            'inventory_alerts' => 0,
            'contract_alerts' => 0,
            'password_resets_cleaned' => $this->cleanupPasswordResets(),
        ];
        $companies = (new Company())->query(
            "SELECT id FROM rateb_companies WHERE status = 'active' ORDER BY id"
        );
        $invSvc = new InventoryWorkflowService();
        $ctrSvc = new ContractWorkflowService();
        foreach ($companies as $company) {
            $cid = (int) ($company['id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            TenantContext::setCompanyId($cid);
            $stats['inventory_alerts'] += $invSvc->processExpiryAlerts($cid);
            $stats['contract_alerts'] += $ctrSvc->processExpiryAlerts($cid);
        }
        TenantContext::setCompanyId(null);
        Logger::info('Cron completed', $stats);
        return $stats;
    }

    public function cleanupPasswordResets(): int
    {
        $db = Database::connection();
        $stmt = $db->prepare('DELETE FROM rateb_password_resets WHERE expires_at < NOW() OR used_at IS NOT NULL');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
