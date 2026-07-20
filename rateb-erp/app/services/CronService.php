<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Company;
use Rateb\App\Models\Subscription;

final class CronService
{
    /** @return array<string, int> */
    public function runAll(): array
    {
        $stats = [
            'queue' => (new QueueWorkerService())->processPending(100),
            'mobile_push' => (new PushQueueWorker())->processPending(50),
            'inventory_alerts' => 0,
            'low_stock_alerts' => 0,
            'contract_alerts' => 0,
            'password_resets_cleaned' => $this->cleanupPasswordResets(),
            'lockouts_unlocked' => (new AccountLockoutService())->unlockExpired(),
            'subscriptions_expired' => (new SaaSAutomationService())->processSubscriptionExpiry(),
            'trial_converted' => (new SaaSAutomationService())->processTrialConversion(),
            'trial_reminders' => (new SaaSAutomationService())->processTrialReminders(),
            'subscription_reminders' => (new SaaSAutomationService())->processSubscriptionReminders(),
            'workflow_escalations' => (new WorkflowSlaService())->processEscalations(),
            'supplier_kpi_updated' => (new SupplierAutomationService())->updateAllKpis(),
            'supplier_alerts' => (new SupplierAutomationService())->processAlerts(),
            'supplier_comm_automations' => (new SupplierCommService())->processAutomations(),
            'contract_status' => (new ContractAutomationService())->processStatusUpdates(),
            'contract_renewal_reminders' => (new ContractAutomationService())->processRenewalReminders(),
            'asset_maintenance' => (new AssetDeviceAutomationService())->processAssetMaintenanceReminders(),
            'device_maintenance' => (new AssetDeviceAutomationService())->processDeviceMaintenanceReminders(),
            'warranty_alerts' => (new AssetDeviceAutomationService())->processWarrantyExpiryAlerts(),
            'spare_part_alerts' => (new AssetDeviceAutomationService())->processSparePartsLowStock(),
            'batch_expiry_alerts' => 0,
            'pos_sync_batches' => 0,
            'invoice_overdue_marked' => (new BillingAutomationService())->markOverdueInvoices(),
            'invoice_due_reminders' => (new BillingAutomationService())->processDueReminders(),
            'cms_pages_published' => 0,
            'cms_articles_published' => 0,
        ];

        $cmsPublish = (new CmsCronService())->publishScheduled();
        $stats['cms_pages_published'] = $cmsPublish['pages'];
        $stats['cms_articles_published'] = $cmsPublish['articles'];

        $companies = (new Company())->query(
            "SELECT id FROM rateb_companies WHERE status = 'active' ORDER BY id"
        );
        $invSvc = new InventoryWorkflowService();
        foreach ($companies as $company) {
            $cid = (int) ($company['id'] ?? 0);
            if ($cid < 1) {
                continue;
            }
            TenantContext::setCompanyId($cid);
            $stats['inventory_alerts'] += $invSvc->processExpiryAlerts($cid);
            $stats['low_stock_alerts'] += $invSvc->processLowStockAlerts($cid);
            $stats['batch_expiry_alerts'] += $invSvc->processBatchExpiryAlerts($cid);
            $stats['pos_sync_batches'] += $this->processPosSyncBatch($cid);
        }
        TenantContext::setCompanyId(null);

        (new AutomationHealthService())->recordCronRun('erp-cron', $stats, 15);
        (new AutomationHealthService())->checkLateJobs();
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

    private function processPosSyncBatch(int $companyId): int
    {
        if ($companyId < 1 || !class_exists(\Rateb\App\Pos\Services\PosSyncBatchProcessorService::class)) {
            return 0;
        }

        $result = (new \Rateb\App\Pos\Services\PosSyncBatchProcessorService())->processPending($companyId, 50);

        return (int) ($result['synced'] ?? 0);
    }
}
