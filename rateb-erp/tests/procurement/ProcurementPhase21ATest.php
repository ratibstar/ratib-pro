<?php

declare(strict_types=1);

/**
 * Phase 21A — Enterprise Procurement Platform (ONLINE) gate tests.
 *
 * Run: php tests/procurement/run-procurement-phase21a-tests.php
 */
final class ProcurementPhase21ATest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testBaselineUntouched();
        $this->testNoOfflineEproc();
        $this->testMigrationExists();
        $this->testDomainServicesPresent();
        $this->testDistinctFromLegacyProcurement();
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

    private function testNoOfflineEproc(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/ProcurementEnterpriseDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/ProcurementWorkflowService.php');
        $ok = !str_contains($domain, 'OfflineQueueService')
            && !str_contains($workflow, 'OfflineQueueService')
            && !str_contains($domain, 'offline.procurement')
            && !is_file(RATEB_ROOT . '/offline/client/adapters/eproc-adapter.js');
        $this->record('No Offline EPROC in 21A (online foundation only)', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/187_procurement_enterprise_platform.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_eproc_supplier_profiles')
            && str_contains($sql, 'rateb_eproc_supplier_categories')
            && str_contains($sql, 'rateb_eproc_supplier_scorecards')
            && str_contains($sql, 'rateb_eproc_supplier_sla')
            && str_contains($sql, 'rateb_eproc_supplier_qualification')
            && str_contains($sql, 'rateb_eproc_portal_invites')
            && str_contains($sql, 'rateb_eproc_tenders')
            && str_contains($sql, 'rateb_eproc_bid_comparisons')
            && str_contains($sql, 'rateb_eproc_contracts')
            && str_contains($sql, 'rateb_eproc_calendar_events')
            && str_contains($sql, 'rateb_eproc_spend_snapshots')
            && str_contains($sql, 'rateb_eproc_approval_links')
            && str_contains($sql, 'rateb_eproc_timeline')
            && str_contains($sql, 'rateb_eproc_status_history')
            && str_contains($sql, 'procurement.view')
            && str_contains($sql, 'procurement.supplier')
            && str_contains($sql, 'procurement.tender')
            && str_contains($sql, 'public_uuid')
            && str_contains($sql, 'deleted_at')
            && str_contains($sql, 'version')
            && !str_contains($sql, 'ALTER TABLE rateb_purchase_orders')
            && !str_contains($sql, 'ALTER TABLE rateb_suppliers');
        $this->record('migration 187 schema + permissions', $ok);
    }

    private function testDomainServicesPresent(): void
    {
        $ok = class_exists(\Rateb\App\Services\ProcurementEnterpriseSupport::class)
            && class_exists(\Rateb\App\Services\ProcurementWorkflowService::class)
            && class_exists(\Rateb\App\Services\ProcurementTimelineService::class)
            && class_exists(\Rateb\App\Services\ProcurementEnterpriseService::class)
            && class_exists(\Rateb\App\Services\SupplierCategoryService::class)
            && class_exists(\Rateb\App\Services\SupplierProfileService::class)
            && class_exists(\Rateb\App\Services\SupplierScorecardService::class)
            && class_exists(\Rateb\App\Services\SupplierQualificationService::class)
            && class_exists(\Rateb\App\Services\SupplierPortalService::class)
            && class_exists(\Rateb\App\Services\EnterpriseTenderService::class)
            && class_exists(\Rateb\App\Services\BidComparisonService::class)
            && class_exists(\Rateb\App\Services\EnterpriseContractService::class)
            && class_exists(\Rateb\App\Services\ProcurementCalendarService::class)
            && class_exists(\Rateb\App\Services\SpendAnalysisService::class)
            && class_exists(\Rateb\App\Services\ProcurementApprovalLinkService::class)
            && class_exists(\Rateb\App\Services\ProcurementCommentService::class)
            && class_exists(\Rateb\App\Services\ProcurementAuditService::class);
        $this->record('domain services present', $ok);
    }

    private function testDistinctFromLegacyProcurement(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/ProcurementEnterpriseDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/ProcurementWorkflowService.php');
        $ok = class_exists(\Rateb\App\Models\EprocSupplierProfile::class)
            && class_exists(\Rateb\App\Services\ProcurementService::class)
            && !preg_match('/\bFROM\s+rateb_purchase_|\bINTO\s+rateb_purchase_|\bUPDATE\s+rateb_purchase_/i', $domain)
            && !preg_match('/\bFROM\s+rateb_suppliers\b|\bINTO\s+rateb_suppliers\b|\bUPDATE\s+rateb_suppliers\b/i', $domain)
            && str_contains($domain, 'rateb_eproc_')
            && str_contains($workflow, 'Distinct from legacy');
        $this->record('EPROC distinct from legacy purchase_* / ProcurementService', $ok);
    }

    private function testWorkflowMaps(): void
    {
        $s = \Rateb\App\Services\ProcurementWorkflowService::statuses('supplier_profile');
        $t = \Rateb\App\Services\ProcurementWorkflowService::statuses('tender');
        $c = \Rateb\App\Services\ProcurementWorkflowService::statuses('contract');
        $sm = \Rateb\App\Services\ProcurementWorkflowService::allowedTransitions('supplier_profile');
        $tm = \Rateb\App\Services\ProcurementWorkflowService::allowedTransitions('tender');
        $ok = in_array('qualified', $s, true)
            && in_array('blacklisted', $s, true)
            && in_array('published', $t, true)
            && in_array('awarded', $t, true)
            && in_array('negotiation', $c, true)
            && in_array('qualified', $sm['draft'] ?? [], true)
            && in_array('published', $tm['draft'] ?? [], true)
            && ($sm['archived'] ?? null) === [];
        $this->record('procurement workflow maps', $ok, implode(',', $s));
    }

    private function testUuidHelper(): void
    {
        $u = \Rateb\App\Services\ProcurementEnterpriseSupport::uuidV4();
        $ok = is_string($u) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $u);
        $this->record('UUID helper', $ok, $u);
    }

    private function testControllersAndViews(): void
    {
        $ok = class_exists(\Rateb\App\Controllers\Company\EprocDashboardController::class)
            && class_exists(\Rateb\App\Controllers\Company\EprocSuppliersController::class)
            && class_exists(\Rateb\App\Controllers\Company\EprocTendersController::class)
            && class_exists(\Rateb\App\Controllers\Company\EprocContractsController::class)
            && is_file(RATEB_ROOT . '/views/company/eproc/dashboard.php')
            && is_file(RATEB_ROOT . '/views/company/eproc/suppliers/index.php')
            && is_file(RATEB_ROOT . '/views/company/eproc/suppliers/show.php')
            && is_file(RATEB_ROOT . '/views/company/eproc/tenders/index.php')
            && is_file(RATEB_ROOT . '/views/company/eproc/contracts/index.php')
            && is_file(RATEB_ROOT . '/views/company/eproc/calendar/index.php')
            && is_file(RATEB_ROOT . '/views/company/eproc/spend/index.php')
            && is_file(RATEB_ROOT . '/views/company/eproc/portal/index.php')
            && is_file(RATEB_ROOT . '/views/company/eproc/reports/index.php')
            && is_file(RATEB_ROOT . '/views/company/eproc/qualification/index.php');
        $this->record('controllers + views present', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($routes, "eproc/suppliers")
            && str_contains($routes, "eproc/tenders")
            && str_contains($routes, "eproc/contracts")
            && str_contains($routes, "eproc/calendar")
            && str_contains($routes, "eproc/spend")
            && str_contains($routes, "eproc/portal")
            && str_contains($routes, 'rateb_erp_mw(\'procurement\'')
            && str_contains($routes, 'Phase 21A')
            && str_contains($routes, 'purchase-requests')
            && str_contains($routes, 'PurchaseOrdersController');
        $this->record('routes registered (eproc/* + legacy preserved)', $ok);
    }

    private function testRbacConfig(): void
    {
        $perms = require RATEB_ROOT . '/config/permissions-system.php';
        $entities = require RATEB_ROOT . '/config/entity-permissions.php';
        $labels = require RATEB_ROOT . '/config/permission-labels-en.php';
        $modules = $perms['company_modules'] ?? [];
        $implies = $perms['permission_implies'] ?? [];
        $ok = in_array('procurement', $modules, true)
            && isset($implies['procurement.manage'], $implies['procurement.admin'])
            && in_array('procurement.view', $implies['procurement.manage'], true)
            && isset($entities['eproc'], $entities['eproc-suppliers'])
            && isset($labels['procurement.view'], $labels['procurement.supplier'], $labels['procurement.tender']);
        $this->record('RBAC module + implies + labels', $ok);
    }

    private function testSidebarAndLang(): void
    {
        $nav = (string) file_get_contents(RATEB_ROOT . '/views/partials/sidebar-ops-nav.php');
        $en = (string) file_get_contents(RATEB_ROOT . '/config/lang/en.php');
        $ar = (string) file_get_contents(RATEB_ROOT . '/config/lang/ar.php');
        $ok = str_contains($nav, "'eproc'")
            && str_contains($nav, 'eproc/suppliers')
            && str_contains($nav, 'purchase-requests')
            && str_contains($en, "'procurement_platform' =>")
            && str_contains($ar, "'procurement_platform' =>")
            && str_contains($en, "'eproc_tenders' =>")
            && str_contains($ar, "'eproc_tenders' =>");
        $this->record('sidebar + EN/AR translations', $ok);
    }

    private function testArchitectureDoc(): void
    {
        $ok = is_file(RATEB_ROOT . '/docs/PHASE_21A_PROCUREMENT_ENTERPRISE.md');
        $this->record('architecture doc present', $ok);
    }

    private function testOfflineReadinessDoc(): void
    {
        $doc = is_file(RATEB_ROOT . '/docs/PHASE_21A_PROCUREMENT_ENTERPRISE.md')
            ? (string) file_get_contents(RATEB_ROOT . '/docs/PHASE_21A_PROCUREMENT_ENTERPRISE.md')
            : '';
        $ok = str_contains($doc, 'Offline readiness')
            && str_contains($doc, 'Replay')
            && str_contains($doc, 'ONLINE ONLY');
        $this->record('offline readiness matrix in docs', $ok);
    }
}
