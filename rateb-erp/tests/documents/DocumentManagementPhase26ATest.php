<?php

declare(strict_types=1);

/**
 * Phase 26A — Enterprise Document Management Platform (ONLINE) gate tests.
 *
 * Run: php tests/documents/run-document-management-phase26a-tests.php
 */
final class DocumentManagementPhase26ATest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testBaselineUntouched();
        $this->testNoOfflineCoupling();
        $this->testMigrationExists();
        $this->testDomainServicesPresent();
        $this->testDistinctFromLegacyDocuments();
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

    private function testNoOfflineCoupling(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/DocumentManagementDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/DocumentWorkflowService.php');
        $docService = (string) file_get_contents(RATEB_ROOT . '/app/services/DocumentService.php');
        $ok = !str_contains($domain, 'OfflineQueueService')
            && !str_contains($workflow, 'OfflineQueueService')
            && !str_contains($domain, 'offline.dms')
            && class_exists(\Rateb\App\Services\DocumentService::class)
            && str_contains($docService, 'final class DocumentService')
            && !str_contains($domain, 'DocumentService::');
        $this->record('26A online layer has no offline coupling; DocumentService untouched', $ok);
    }

    private function testMigrationExists(): void
    {
        $path = RATEB_ROOT . '/migrations/192_document_management_platform.sql';
        $sql = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $sql !== ''
            && str_contains($sql, 'rateb_dms_repositories')
            && str_contains($sql, 'rateb_dms_folders')
            && str_contains($sql, 'rateb_dms_documents')
            && str_contains($sql, 'rateb_dms_versions')
            && str_contains($sql, 'rateb_dms_document_metadata')
            && str_contains($sql, 'rateb_dms_checkouts')
            && str_contains($sql, 'rateb_dms_shares')
            && str_contains($sql, 'rateb_dms_permissions')
            && str_contains($sql, 'rateb_dms_retention_policies')
            && str_contains($sql, 'rateb_dms_retention_jobs')
            && str_contains($sql, 'rateb_dms_legal_holds')
            && str_contains($sql, 'rateb_dms_categories')
            && str_contains($sql, 'rateb_dms_tags')
            && str_contains($sql, 'rateb_dms_document_links')
            && str_contains($sql, 'rateb_dms_document_relations')
            && str_contains($sql, 'rateb_dms_approvals_meta')
            && str_contains($sql, 'rateb_dms_signature_requests')
            && str_contains($sql, 'rateb_dms_signature_events')
            && str_contains($sql, 'rateb_dms_audit_logs')
            && str_contains($sql, 'rateb_dms_timeline')
            && str_contains($sql, 'rateb_dms_comments')
            && str_contains($sql, 'rateb_dms_favorites')
            && str_contains($sql, 'rateb_dms_recent_items')
            && str_contains($sql, 'rateb_dms_search_index')
            && str_contains($sql, 'rateb_dms_status_history')
            && str_contains($sql, 'documents.view')
            && str_contains($sql, 'documents.share')
            && str_contains($sql, 'documents.retention')
            && str_contains($sql, 'public_uuid')
            && str_contains($sql, 'deleted_at')
            && str_contains($sql, 'version')
            && !str_contains($sql, 'ALTER TABLE rateb_documents')
            && !str_contains($sql, 'ALTER TABLE rateb_entity_attachments');
        $this->record('migration 192 schema + permissions', $ok);
    }

    private function testDomainServicesPresent(): void
    {
        $ok = class_exists(\Rateb\App\Services\DocumentManagementSupport::class)
            && class_exists(\Rateb\App\Services\DocumentWorkflowService::class)
            && class_exists(\Rateb\App\Services\DocumentTimelineService::class)
            && class_exists(\Rateb\App\Services\DocumentManagementEnterpriseService::class)
            && class_exists(\Rateb\App\Services\DmsRepositoryService::class)
            && class_exists(\Rateb\App\Services\DmsFolderService::class)
            && class_exists(\Rateb\App\Services\DmsDocumentService::class)
            && class_exists(\Rateb\App\Services\DmsVersionService::class)
            && class_exists(\Rateb\App\Services\DmsShareService::class)
            && class_exists(\Rateb\App\Services\DmsRetentionService::class)
            && class_exists(\Rateb\App\Services\DmsLegalHoldService::class)
            && class_exists(\Rateb\App\Services\DmsPermissionService::class)
            && class_exists(\Rateb\App\Services\DmsSearchService::class)
            && class_exists(\Rateb\App\Services\DmsFavoriteService::class)
            && class_exists(\Rateb\App\Models\DmsDocument::class)
            && class_exists(\Rateb\App\Models\DmsRepository::class);
        $this->record('domain services present', $ok);
    }

    private function testDistinctFromLegacyDocuments(): void
    {
        $domain = (string) file_get_contents(RATEB_ROOT . '/app/services/DocumentManagementDomainServices.php');
        $workflow = (string) file_get_contents(RATEB_ROOT . '/app/services/DocumentWorkflowService.php');
        $ok = str_contains($domain, 'rateb_dms_')
            && !preg_match('/\bFROM\s+rateb_documents\b|\bUPDATE\s+rateb_documents\b|\bINTO\s+rateb_documents\b/i', $domain)
            && !preg_match('/(?<!Dms)\bDocumentService\b/', $domain)
            && !str_contains($workflow, 'rateb_documents')
            && class_exists(\Rateb\App\Controllers\Company\DocumentsController::class)
            && is_file(RATEB_ROOT . '/app/services/DocumentService.php');
        $this->record('DMS distinct from legacy rateb_documents / DocumentService', $ok);
    }

    private function testWorkflowMaps(): void
    {
        $doc = \Rateb\App\Services\DocumentWorkflowService::statuses('document');
        $map = \Rateb\App\Services\DocumentWorkflowService::allowedTransitions('document');
        $ok = in_array('draft', $doc, true)
            && in_array('checked_in', $doc, true)
            && in_array('review', $doc, true)
            && in_array('approved', $doc, true)
            && in_array('published', $doc, true)
            && in_array('archived', $doc, true)
            && in_array('checked_in', $map['draft'] ?? [], true)
            && in_array('review', $map['checked_in'] ?? [], true)
            && in_array('approved', $map['review'] ?? [], true)
            && in_array('published', $map['approved'] ?? [], true)
            && ($map['archived'] ?? null) === [];
        $this->record('document workflow maps', $ok, implode(',', $doc));
    }

    private function testUuidHelper(): void
    {
        $u = \Rateb\App\Services\DocumentManagementSupport::uuidV4();
        $ok = is_string($u) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $u);
        $this->record('UUID helper', $ok, $u);
    }

    private function testControllersAndViews(): void
    {
        $ok = class_exists(\Rateb\App\Controllers\Company\DmsDashboardController::class)
            && class_exists(\Rateb\App\Controllers\Company\DmsRepositoriesController::class)
            && class_exists(\Rateb\App\Controllers\Company\DmsFoldersController::class)
            && class_exists(\Rateb\App\Controllers\Company\DmsDocumentsController::class)
            && class_exists(\Rateb\App\Controllers\Company\DmsVersionsController::class)
            && class_exists(\Rateb\App\Controllers\Company\DmsSearchController::class)
            && class_exists(\Rateb\App\Controllers\Company\DmsFavoritesController::class)
            && class_exists(\Rateb\App\Controllers\Company\DmsSharesController::class)
            && class_exists(\Rateb\App\Controllers\Company\DmsRetentionController::class)
            && class_exists(\Rateb\App\Controllers\Company\DmsLegalHoldsController::class)
            && class_exists(\Rateb\App\Controllers\Company\DmsPermissionsController::class)
            && is_file(RATEB_ROOT . '/views/company/dms/dashboard.php')
            && is_file(RATEB_ROOT . '/views/company/dms/repositories/index.php')
            && is_file(RATEB_ROOT . '/views/company/dms/folders/index.php')
            && is_file(RATEB_ROOT . '/views/company/dms/documents/index.php')
            && is_file(RATEB_ROOT . '/views/company/dms/documents/show.php')
            && is_file(RATEB_ROOT . '/views/company/dms/versions/index.php')
            && is_file(RATEB_ROOT . '/views/company/dms/search/index.php')
            && is_file(RATEB_ROOT . '/views/company/dms/favorites/index.php')
            && is_file(RATEB_ROOT . '/views/company/dms/shares/index.php')
            && is_file(RATEB_ROOT . '/views/company/dms/retention/index.php')
            && is_file(RATEB_ROOT . '/views/company/dms/legal-holds/index.php')
            && is_file(RATEB_ROOT . '/views/company/dms/permissions/index.php')
            && is_file(RATEB_ROOT . '/views/company/dms/timeline/index.php')
            && is_file(RATEB_ROOT . '/views/company/dms/reports/index.php');
        $this->record('controllers + views present', $ok);
    }

    private function testRoutesRegistered(): void
    {
        $routes = (string) file_get_contents(RATEB_ROOT . '/routes/company.php');
        $ok = str_contains($routes, 'dms-platform')
            && str_contains($routes, 'dms/dashboard')
            && str_contains($routes, 'dms/repositories')
            && str_contains($routes, 'dms/documents')
            && str_contains($routes, 'dms/shares')
            && str_contains($routes, 'dms/retention')
            && str_contains($routes, 'dms/legal-holds')
            && str_contains($routes, 'dms/timeline')
            && str_contains($routes, "rateb_erp_mw('documents'")
            && str_contains($routes, 'Phase 26A')
            && str_contains($routes, "app('documents')");
        $this->record('routes registered (dms/* + legacy documents preserved)', $ok);
    }

    private function testRbacConfig(): void
    {
        $perms = require RATEB_ROOT . '/config/permissions-system.php';
        $entities = require RATEB_ROOT . '/config/entity-permissions.php';
        $labels = require RATEB_ROOT . '/config/permission-labels-en.php';
        $implies = $perms['permission_implies']['documents.manage'] ?? [];
        $ok = in_array('documents', $perms['company_modules'] ?? [], true)
            && in_array('documents.view', $implies, true)
            && in_array('documents.create', $implies, true)
            && in_array('documents.share', $implies, true)
            && in_array('documents.download', $implies, true)
            && in_array('documents.retention', $implies, true)
            && in_array('documents.admin', $implies, true)
            && isset($entities['documents'], $entities['dms-repositories'], $entities['dms-documents'], $entities['dms-shares'])
            && isset($labels['documents.create'], $labels['documents.admin'], $labels['documents.manage']);
        $this->record('RBAC module + implies + labels wiring', $ok);
    }

    private function testSidebarAndLang(): void
    {
        $nav = (string) file_get_contents(RATEB_ROOT . '/views/partials/sidebar-ops-nav.php');
        $en = require RATEB_ROOT . '/config/lang/en.php';
        $ar = require RATEB_ROOT . '/config/lang/ar.php';
        $ok = str_contains($nav, 'dms/documents')
            && str_contains($nav, 'dms/shares')
            && str_contains($nav, 'documents.view')
            && isset($en['dms_platform'], $en['dms_repositories'], $ar['dms_platform'], $ar['dms_shares']);
        $this->record('sidebar + EN/AR translations', $ok);
    }

    private function testArchitectureDoc(): void
    {
        $path = RATEB_ROOT . '/docs/PHASE_26A_DOCUMENT_MANAGEMENT.md';
        $doc = is_file($path) ? (string) file_get_contents($path) : '';
        $ok = $doc !== ''
            && str_contains($doc, '192_document_management_platform.sql')
            && str_contains($doc, 'DocumentWorkflowService')
            && str_contains($doc, 'rateb_dms_')
            && str_contains($doc, 'Enterprise Baseline')
            && str_contains($doc, 'Offline Foundation');
        $this->record('architecture doc present', $ok);
    }

    private function testOfflineReadinessDoc(): void
    {
        $doc = (string) file_get_contents(RATEB_ROOT . '/docs/PHASE_26A_DOCUMENT_MANAGEMENT.md');
        $ok = str_contains($doc, 'Offline readiness')
            && str_contains($doc, '26B')
            && str_contains($doc, 'Replay-ready');
        $this->record('offline readiness matrix in docs', $ok);
    }
}
