<?php

declare(strict_types=1);

/**
 * Phase 22A — Enterprise Manufacturing (MRP) Platform (ONLINE) gate tests.
 *
 * Run: php tests/manufacturing/run-manufacturing-phase22a-tests.php
 */
final class ManufacturingPhase22ATest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testBaselineUntouched();
        $this->testNoOfflineMfg();
        $this->testMigrationExists();
        $this->testDomainServicesPresent();
        $this->testDistinctFromEamAndInventory();
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

    private function testNoOfflineMfg(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/ManufacturingDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/ManufacturingWorkflowService.php');
        $ok = !str_contains($domain, 'OfflineQueueService')
            && !str_contains($workflow, 'OfflineQueueService')
            && !str_contains($domain, 'offline.manufacturing')
            && !is_file(RATEB_ROOT . '/offline/client/adapters/manufacturing-adapter.js')
            && !is_file(RATEB_ROOT . '/offline/client/adapters/mfg-adapter.js');
        $this->record('No Offline MFG in 22A (online foundation only)', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/188_manufacturing_platform_enterprise.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_mfg_products')
            && str_contains($sql, 'rateb_mfg_product_variants')
            && str_contains($sql, 'rateb_mfg_boms')
            && str_contains($sql, 'rateb_mfg_bom_versions')
            && str_contains($sql, 'rateb_mfg_bom_lines')
            && str_contains($sql, 'rateb_mfg_work_centers')
            && str_contains($sql, 'rateb_mfg_machines')
            && str_contains($sql, 'rateb_mfg_routings')
            && str_contains($sql, 'rateb_mfg_routing_operations')
            && str_contains($sql, 'rateb_mfg_production_orders')
            && str_contains($sql, 'rateb_mfg_work_orders')
            && str_contains($sql, 'rateb_mfg_capacity_plans')
            && str_contains($sql, 'rateb_mfg_production_calendar')
            && str_contains($sql, 'rateb_mfg_schedules')
            && str_contains($sql, 'rateb_mfg_material_reservations')
            && str_contains($sql, 'rateb_mfg_material_consumptions')
            && str_contains($sql, 'rateb_mfg_finished_goods_receipts')
            && str_contains($sql, 'rateb_mfg_scrap_records')
            && str_contains($sql, 'rateb_mfg_quality_checks')
            && str_contains($sql, 'rateb_mfg_production_costs')
            && str_contains($sql, 'rateb_mfg_timeline')
            && str_contains($sql, 'rateb_mfg_assignments')
            && str_contains($sql, 'rateb_mfg_comments')
            && str_contains($sql, 'rateb_mfg_attachments_meta')
            && str_contains($sql, 'rateb_mfg_tags')
            && str_contains($sql, 'rateb_mfg_status_history')
            && str_contains($sql, 'manufacturing.view')
            && str_contains($sql, 'manufacturing.bom')
            && str_contains($sql, 'manufacturing.shopfloor')
            && str_contains($sql, 'public_uuid')
            && str_contains($sql, 'deleted_at')
            && str_contains($sql, 'version')
            && !str_contains($sql, 'ALTER TABLE rateb_inventory')
            && !str_contains($sql, 'ALTER TABLE rateb_eam_')
            && !str_contains($sql, 'ALTER TABLE rateb_eproc_');
        $this->record('migration 188 schema + permissions', $ok);
    }

    private function testDomainServicesPresent(): void
    {
        $ok = class_exists(\Rateb\App\Services\ManufacturingSupport::class)
            && class_exists(\Rateb\App\Services\ManufacturingWorkflowService::class)
            && class_exists(\Rateb\App\Services\ManufacturingTimelineService::class)
            && class_exists(\Rateb\App\Services\ManufacturingEnterpriseService::class)
            && class_exists(\Rateb\App\Services\MfgProductService::class)
            && class_exists(\Rateb\App\Services\BomService::class)
            && class_exists(\Rateb\App\Services\BomVersionService::class)
            && class_exists(\Rateb\App\Services\BomLineService::class)
            && class_exists(\Rateb\App\Services\WorkCenterService::class)
            && class_exists(\Rateb\App\Services\MachineService::class)
            && class_exists(\Rateb\App\Services\RoutingService::class)
            && class_exists(\Rateb\App\Services\RoutingOperationService::class)
            && class_exists(\Rateb\App\Services\ProductionOrderService::class)
            && class_exists(\Rateb\App\Services\MfgWorkOrderService::class)
            && class_exists(\Rateb\App\Services\CapacityPlanService::class)
            && class_exists(\Rateb\App\Services\ProductionCalendarService::class)
            && class_exists(\Rateb\App\Services\ScheduleService::class)
            && class_exists(\Rateb\App\Services\MaterialReservationService::class)
            && class_exists(\Rateb\App\Services\MaterialConsumptionService::class)
            && class_exists(\Rateb\App\Services\FinishedGoodsReceiptService::class)
            && class_exists(\Rateb\App\Services\ScrapRecordingService::class)
            && class_exists(\Rateb\App\Services\QualityCheckService::class)
            && class_exists(\Rateb\App\Services\ProductionCostService::class)
            && class_exists(\Rateb\App\Services\ManufacturingAssignmentService::class)
            && class_exists(\Rateb\App\Services\ManufacturingCommentService::class)
            && class_exists(\Rateb\App\Services\ManufacturingAttachmentMetaService::class)
            && class_exists(\Rateb\App\Services\ManufacturingTagService::class)
            && class_exists(\Rateb\App\Models\MfgProduct::class)
            && class_exists(\Rateb\App\Models\MfgProductionOrder::class);
        $this->record('domain services present', $ok);
    }

    private function testDistinctFromEamAndInventory(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/ManufacturingDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/ManufacturingWorkflowService.php');
        $ok = str_contains($domain, 'rateb_mfg_')
            && !preg_match('/\bFROM\s+rateb_eam_|\bINTO\s+rateb_eam_|\bUPDATE\s+rateb_eam_/i', $domain)
            && !preg_match('/new\s+\\\\?StockMovementService\b|new\s+\\\\?InventoryWorkflowService\b|AccountingService::/', $domain)
            && !str_contains($workflow, 'rateb_eam_work_orders')
            && class_exists(\Rateb\App\Services\AssetWorkflowService::class);
        $this->record('MFG distinct from EAM WO / inventory posting services', $ok);
    }

    private function testWorkflowMaps(): void
    {
        $po = \Rateb\App\Services\ManufacturingWorkflowService::statuses('production_order');
        $wo = \Rateb\App\Services\ManufacturingWorkflowService::statuses('work_order');
        $bom = \Rateb\App\Services\ManufacturingWorkflowService::statuses('bom');
        $map = \Rateb\App\Services\ManufacturingWorkflowService::allowedTransitions('production_order');
        $ok = in_array('draft', $po, true)
            && in_array('planned', $po, true)
            && in_array('released', $po, true)
            && in_array('in_progress', $po, true)
            && in_array('quality_check', $po, true)
            && in_array('completed', $po, true)
            && in_array('closed', $po, true)
            && in_array('cancelled', $po, true)
            && in_array('archived', $po, true)
            && $po === $wo
            && in_array('active', $bom, true)
            && in_array('planned', $map['draft'] ?? [], true)
            && in_array('released', $map['planned'] ?? [], true)
            && ($map['archived'] ?? null) === [];
        $this->record('manufacturing workflow maps', $ok, implode(',', $po));
    }

    private function testUuidHelper(): void
    {
        $u = \Rateb\App\Services\ManufacturingSupport::uuidV4();
        $ok = is_string($u) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $u);
        $this->record('UUID helper', $ok, $u);
    }

    private function testControllersAndViews(): void
    {
        $ok = class_exists(\Rateb\App\Controllers\Company\MfgDashboardController::class)
            && class_exists(\Rateb\App\Controllers\Company\MfgProductsController::class)
            && class_exists(\Rateb\App\Controllers\Company\MfgBomsController::class)
            && class_exists(\Rateb\App\Controllers\Company\MfgProductionOrdersController::class)
            && class_exists(\Rateb\App\Controllers\Company\MfgWorkOrdersController::class)
            && is_file(RATEB_ROOT . '/views/company/mfg/dashboard.php')
            && is_file(RATEB_ROOT . '/views/company/mfg/products/index.php')
            && is_file(RATEB_ROOT . '/views/company/mfg/products/show.php')
            && is_file(RATEB_ROOT . '/views/company/mfg/boms/index.php')
            && is_file(RATEB_ROOT . '/views/company/mfg/production-orders/index.php')
            && is_file(RATEB_ROOT . '/views/company/mfg/production-orders/show.php')
            && is_file(RATEB_ROOT . '/views/company/mfg/work-orders/index.php')
            && is_file(RATEB_ROOT . '/views/company/mfg/capacity/index.php')
            && is_file(RATEB_ROOT . '/views/company/mfg/quality/index.php')
            && is_file(RATEB_ROOT . '/views/company/mfg/reports/index.php');
        $this->record('controllers + views present', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($routes, "mfg/products")
            && str_contains($routes, "mfg/boms")
            && str_contains($routes, "mfg/production-orders")
            && str_contains($routes, "mfg/work-orders")
            && str_contains($routes, "mfg/capacity")
            && str_contains($routes, "mfg/quality")
            && str_contains($routes, "rateb_erp_mw('manufacturing'")
            && str_contains($routes, 'Phase 22A')
            && str_contains($routes, 'eproc/suppliers')
            && str_contains($routes, 'purchase-requests');
        $this->record('routes registered (mfg/* + siblings preserved)', $ok);
    }

    private function testRbacConfig(): void
    {
        $perms = require RATEB_ROOT . '/config/permissions-system.php';
        $entities = require RATEB_ROOT . '/config/entity-permissions.php';
        $modules = require RATEB_ROOT . '/config/module-permissions.php';
        $implies = $perms['permission_implies']['manufacturing.manage'] ?? [];
        $ok = in_array('manufacturing', $perms['company_modules'] ?? [], true)
            && ($perms['tenant_module_labels']['manufacturing'] ?? '') === 'manufacturing_platform'
            && in_array('manufacturing.view', $implies, true)
            && in_array('manufacturing.bom', $implies, true)
            && in_array('manufacturing.shopfloor', $implies, true)
            && isset($entities['mfg'], $entities['mfg-boms'], $entities['mfg-production-orders'])
            && ($modules['manufacturing'] ?? '') === 'manufacturing.manage';
        $this->record('RBAC module + implies + labels wiring', $ok);
    }

    private function testSidebarAndLang(): void
    {
        $nav = (string) file_get_contents(RATEB_ROOT . '/views/partials/sidebar-ops-nav.php');
        $en = require RATEB_ROOT . '/config/lang/en.php';
        $ar = require RATEB_ROOT . '/config/lang/ar.php';
        $labelsEn = require RATEB_ROOT . '/config/permission-labels-en.php';
        $ok = str_contains($nav, "mfg/products")
            && str_contains($nav, "mfg/production-orders")
            && str_contains($nav, 'manufacturing.view')
            && isset($en['manufacturing_platform'], $en['mfg_boms'], $ar['manufacturing_platform'])
            && isset($labelsEn['manufacturing.manage'], $labelsEn['manufacturing.shopfloor']);
        $this->record('sidebar + EN/AR translations', $ok);
    }

    private function testArchitectureDoc(): void
    {
        $path = RATEB_ROOT . '/docs/PHASE_22A_MANUFACTURING_ONLINE.md';
        $doc = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $doc !== ''
            && str_contains($doc, '188_manufacturing_platform_enterprise.sql')
            && str_contains($doc, 'ManufacturingWorkflowService')
            && str_contains($doc, 'rateb_mfg_')
            && str_contains($doc, 'Enterprise Baseline')
            && str_contains($doc, 'Offline Foundation');
        $this->record('architecture doc present', $ok);
    }

    private function testOfflineReadinessDoc(): void
    {
        $doc = (string) file_get_contents(RATEB_ROOT . '/docs/PHASE_22A_MANUFACTURING_ONLINE.md');
        $ok = str_contains($doc, 'Offline readiness')
            && str_contains($doc, '22B')
            && str_contains($doc, 'Replay-ready');
        $this->record('offline readiness matrix in docs', $ok);
    }
}
