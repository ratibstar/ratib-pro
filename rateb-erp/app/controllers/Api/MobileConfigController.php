<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Database;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\MobileAppConfigService;

/**
 * Thin adapter: tenant branding + feature flags for Workforce / HR Mobile clients.
 * company_id MUST come from TenantContext (API token) — never from client input.
 */
final class MobileConfigController extends Controller
{
    public function config(): void
    {
        // Reject client-supplied company overrides if present.
        if (isset($_GET['company_id']) || isset($_POST['company_id'])) {
            Response::json([
                'success' => false,
                'message' => 'company_id must not be supplied by the client',
            ], 400);
            return;
        }

        // ESS login calls this before device register — create registry if migrations lag.
        $this->ensureMobileDeviceRegistry();

        $companyId = (int) (TenantContext::companyId() ?? 0);
        $result = (new MobileAppConfigService())->apiConfigForCompany($companyId);
        Response::json($result['body'], (int) $result['status']);
    }

    private function ensureMobileDeviceRegistry(): void
    {
        try {
            if (class_exists(\Rateb\App\Services\MobileDeviceSchemaBootstrap::class)) {
                \Rateb\App\Services\MobileDeviceSchemaBootstrap::ensure();
                return;
            }
            $pdo = Database::connection();
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS rateb_mobile_devices (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    company_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    client_app VARCHAR(32) NOT NULL,
                    platform VARCHAR(16) NOT NULL DEFAULT \'other\',
                    device_id VARCHAR(64) NOT NULL,
                    push_token VARCHAR(512) NULL,
                    push_provider VARCHAR(16) NOT NULL DEFAULT \'none\',
                    locale VARCHAR(16) NULL,
                    app_version VARCHAR(64) NULL,
                    last_seen_at DATETIME NULL,
                    status ENUM(\'active\', \'inactive\', \'revoked\') NOT NULL DEFAULT \'active\',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_mobile_device_identity (company_id, client_app, device_id),
                    KEY idx_mobile_device_user (company_id, user_id, status),
                    KEY idx_mobile_device_seen (company_id, last_seen_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable $e) {
            error_log('Mobile device registry ensure failed: ' . $e->getMessage());
        }
    }
}
