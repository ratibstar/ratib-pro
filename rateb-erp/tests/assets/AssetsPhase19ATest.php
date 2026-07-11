<?php

declare(strict_types=1);

/**
 * Phase 19A — Enterprise Assets & Maintenance Platform (ONLINE) gate tests.
 *
 * Run: php tests/assets/run-assets-phase19a-tests.php
 */
final class AssetsPhase19ATest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testBaselineUntouched();
        $this->testNoOfflineAssets();
        $this->testMigrationExists();
        $this->testDomainServicesPresent();
        $this->testDistinctFromLegacyAssets();
        $this->testWorkflowMaps();
        $this->testUuidHelper();
        $this->testControllersAndViews();
        $this->testRoutesRegistered();
        $this->testRbacConfig();
        $this->testSidebarAndLang();
        $this->testArchitectureDoc();
        $this->testOfflineReadinessDoc();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function testBaselineUntouched(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = preg_match('/DB_VERSION\s*=\s*2/', $schema)
            && str_contains($sdk, "version: '14.2.0'");
        $this->record('Enterprise Baseline / Offline Foundation markers intact', $ok);
    }

    private function testNoOfflineAssets(): void
    {
        // Phase 19A asserted no offline Assets; Phase 19B adds it — soft check that ONLINE
        // domain services remain free of offline queue coupling.
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/AssetDomainServices.php');
        $activity = (string) file_get_contents(RATEB_ROOT . '/app/services/AssetActivityServices.php');
        $ok = !str_contains($domain, 'OfflineQueueService')
            && !str_contains($activity, 'OfflineQueueService')
            && !str_contains($domain, 'offline.assets');
        $this->record('No Offline Assets in 19A (online foundation only)', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/185_assets_platform_enterprise.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_eam_assets')
            && str_contains($sql, 'rateb_eam_asset_categories')
            && str_contains($sql, 'rateb_eam_locations')
            && str_contains($sql, 'rateb_eam_asset_assignments')
            && str_contains($sql, 'rateb_eam_asset_transfers')
            && str_contains($sql, 'rateb_eam_maintenance_plans')
            && str_contains($sql, 'rateb_eam_maintenance_requests')
            && str_contains($sql, 'rateb_eam_work_orders')
            && str_contains($sql, 'rateb_eam_inspections')
            && str_contains($sql, 'rateb_eam_checklists')
            && str_contains($sql, 'rateb_eam_meter_readings')
            && str_contains($sql, 'rateb_eam_warranties')
            && str_contains($sql, 'rateb_eam_insurance')
            && str_contains($sql, 'rateb_eam_parts_consumption')
            && str_contains($sql, 'rateb_eam_document_meta')
            && str_contains($sql, 'rateb_eam_timeline')
            && str_contains($sql, 'rateb_eam_activities')
            && str_contains($sql, 'rateb_eam_comments')
            && str_contains($sql, 'rateb_eam_status_history')
            && str_contains($sql, 'assets.view')
            && str_contains($sql, 'assets.maintenance')
            && str_contains($sql, 'assets.inspection')
            && str_contains($sql, 'assets.admin')
            && str_contains($sql, 'public_uuid')
            && str_contains($sql, 'deleted_at')
            && str_contains($sql, 'version')
            && !str_contains($sql, 'ALTER TABLE rateb_assets');
        $this->record('migration 185 schema + permissions', $ok);
    }

    private function testDomainServicesPresent(): void
    {
        $ok = class_exists(\Rateb\App\Services\AssetSupport::class)
            && class_exists(\Rateb\App\Services\AssetService::class)
            && class_exists(\Rateb\App\Services\AssetCategoryService::class)
            && class_exists(\Rateb\App\Services\AssetLocationService::class)
            && class_exists(\Rateb\App\Services\AssetAssignmentService::class)
            && class_exists(\Rateb\App\Services\AssetTransferService::class)
            && class_exists(\Rateb\App\Services\AssetMaintenanceService::class)
            && class_exists(\Rateb\App\Services\MaintenanceRequestService::class)
            && class_exists(\Rateb\App\Services\MaintenancePlanService::class)
            && class_exists(\Rateb\App\Services\WorkOrderService::class)
            && class_exists(\Rateb\App\Services\InspectionService::class)
            && class_exists(\Rateb\App\Services\ChecklistService::class)
            && class_exists(\Rateb\App\Services\MeterReadingService::class)
            && class_exists(\Rateb\App\Services\WarrantyService::class)
            && class_exists(\Rateb\App\Services\InsuranceService::class)
            && class_exists(\Rateb\App\Services\AssetTimelineService::class)
            && class_exists(\Rateb\App\Services\AssetActivityService::class)
            && class_exists(\Rateb\App\Services\AssetCommentService::class)
            && class_exists(\Rateb\App\Services\AssetWorkflowService::class)
            && class_exists(\Rateb\App\Services\AssetDocumentMetaService::class);
        $this->record('domain services present', $ok);
    }

    private function testDistinctFromLegacyAssets(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/AssetDomainServices.php');
        $ok = class_exists(\Rateb\App\Models\EamAsset::class)
            && class_exists(\Rateb\App\Models\Asset::class)
            && is_file(RATEB_ROOT . '/app/services/AssetDeviceWorkflowService.php')
            && !preg_match('/\bFROM\s+rateb_assets\b|\bINTO\s+rateb_assets\b|\bUPDATE\s+rateb_assets\b/i', $domain)
            && str_contains($domain, 'rateb_eam_assets');
        $this->record('EAM distinct from legacy rateb_assets / AssetDevice*', $ok);
    }

    private function testWorkflowMaps(): void
    {
        $a = \Rateb\App\Services\AssetWorkflowService::assetStatuses();
        $r = \Rateb\App\Services\AssetWorkflowService::requestStatuses();
        $am = \Rateb\App\Services\AssetWorkflowService::allowedAssetTransitions();
        $rm = \Rateb\App\Services\AssetWorkflowService::allowedRequestTransitions();
        $ok = $a === ['draft', 'registered', 'active', 'maintenance', 'retired', 'disposed', 'archived']
            && $r === ['new', 'approved', 'scheduled', 'in_progress', 'completed', 'closed']
            && in_array('registered', $am['draft'], true)
            && in_array('active', $am['registered'], true)
            && in_array('maintenance', $am['active'], true)
            && $am['archived'] === []
            && in_array('approved', $rm['new'], true)
            && in_array('closed', $rm['completed'], true)
            && $rm['closed'] === [];
        $this->record('asset + request workflow maps', $ok, implode(',', $a) . ' | ' . implode(',', $r));
    }

    private function testUuidHelper(): void
    {
        $u = \Rateb\App\Services\AssetSupport::uuidV4();
        $ok = is_string($u) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $u);
        $this->record('UUID helper', $ok, $u);
    }

    private function testControllersAndViews(): void
    {
        $ok = class_exists(\Rateb\App\Controllers\Company\EamDashboardController::class)
            && class_exists(\Rateb\App\Controllers\Company\EamAssetsController::class)
            && class_exists(\Rateb\App\Controllers\Company\EamWorkOrdersController::class)
            && is_file(RATEB_ROOT . '/views/company/eam/dashboard.php')
            && is_file(RATEB_ROOT . '/views/company/eam/assets/index.php')
            && is_file(RATEB_ROOT . '/views/company/eam/assets/show.php')
            && is_file(RATEB_ROOT . '/views/company/eam/maintenance/index.php')
            && is_file(RATEB_ROOT . '/views/company/eam/work-orders/index.php')
            && is_file(RATEB_ROOT . '/views/company/eam/requests/index.php')
            && is_file(RATEB_ROOT . '/views/company/eam/calendar.php')
            && is_file(RATEB_ROOT . '/views/company/eam/assignments.php')
            && is_file(RATEB_ROOT . '/views/company/eam/timeline.php')
            && is_file(RATEB_ROOT . '/views/company/eam/reports.php')
            && is_file(RATEB_ROOT . '/views/company/eam/inspections.php');
        $this->record('controllers + views present', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($routes, "eam/assets")
            && str_contains($routes, "eam/work-orders")
            && str_contains($routes, "eam/calendar")
            && str_contains($routes, "eam/requests")
            && str_contains($routes, "eam/inspections")
            && str_contains($routes, 'rateb_erp_mw(\'assets\'')
            && str_contains($routes, 'Phase 19A')
            && str_contains($routes, 'AssetsController');
        $this->record('routes registered (eam/* + legacy assets preserved)', $ok);
    }

    private function testRbacConfig(): void
    {
        $perms = require RATEB_ROOT . '/config/permissions-system.php';
        $entities = require RATEB_ROOT . '/config/entity-permissions.php';
        $labels = require RATEB_ROOT . '/config/permission-labels-en.php';
        $modules = $perms['company_modules'] ?? [];
        $implies = $perms['permission_implies'] ?? [];
        $ok = in_array('assets', $modules, true)
            && isset($implies['assets.manage'], $implies['assets.admin'])
            && isset($entities['assets'], $entities['eam'])
            && ($entities['assets']['view'] ?? '') === 'assets.view'
            && isset($labels['assets.view'], $labels['assets.maintenance'], $labels['assets.inspection'], $labels['assets.transfer']);
        $this->record('RBAC module + implies + labels', $ok);
    }

    private function testSidebarAndLang(): void
    {
        $nav = (string) file_get_contents(RATEB_ROOT . '/views/partials/sidebar-ops-nav.php');
        $en = (string) file_get_contents(RATEB_ROOT . '/config/lang/en.php');
        $ar = (string) file_get_contents(RATEB_ROOT . '/config/lang/ar.php');
        $ok = str_contains($nav, "'eam'")
            && str_contains($nav, 'eam/work-orders')
            && str_contains($nav, "'assets'")
            && str_contains($en, "'eam_platform' =>")
            && str_contains($ar, "'eam_platform' =>")
            && str_contains($en, "'eam_work_orders' =>")
            && str_contains($ar, "'eam_work_orders' =>");
        $this->record('sidebar + EN/AR translations', $ok);
    }

    private function testArchitectureDoc(): void
    {
        $ok = is_file(RATEB_ROOT . '/docs/PHASE_19A_ASSETS_ONLINE.md');
        $this->record('architecture doc present', $ok);
    }

    private function testOfflineReadinessDoc(): void
    {
        $doc = is_file(RATEB_ROOT . '/docs/PHASE_19A_ASSETS_ONLINE.md')
            ? (string) file_get_contents(RATEB_ROOT . '/docs/PHASE_19A_ASSETS_ONLINE.md')
            : '';
        $ok = str_contains($doc, 'Offline readiness')
            && str_contains($doc, 'Replay')
            && str_contains($doc, 'attachment')
            && str_contains($doc, 'ONLINE ONLY');
        $this->record('offline readiness matrix in docs', $ok);
    }
}
