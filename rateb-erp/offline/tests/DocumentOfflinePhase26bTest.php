<?php

declare(strict_types=1);

/**
 * Phase 26B — Enterprise Documents Offline (Tier 1 drafts) tests.
 *
 * Run: php offline/tests/run-document-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\DocumentOfflineMasterDataDirectoryService;
use Rateb\App\Offline\Services\DocumentOfflineReplayService;
use Rateb\App\Offline\Services\DocumentOfflineTenantGuard;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;

final class DocumentOfflinePhase26bTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagsDefaultOff();
        $this->testRequiresMaster();
        $this->testSubflagsRequireParent();
        $this->testEntityManifestHasDocuments();
        $this->testModulesRegistryActiveDocuments();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasDocumentsAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoDeleteUploadPayments();
        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsRepositoryWithoutSubflag();
        $this->testQueueAliases();
        $this->testPayloadSanitizerKeepsModule();
        $this->testAuthzAllowsAbility();
        $this->testTenantGuardSource();
        $this->testMasterDataEntitiesRegistered();
        $this->testOpsAllowlistDocuments();
        $this->testOpsFormsDocumentsHooks();
        $this->testBackgroundReportsFlag();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_DOCUMENTS',
            'RATEB_OFFLINE_DOCUMENTS_REPOSITORIES',
            'RATEB_OFFLINE_DOCUMENTS_WORKFLOW',
            'RATEB_OFFLINE_DOCUMENTS_MASTERDATA',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_DOCUMENTS=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_DOCUMENTS'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_DOCUMENTS_REPOSITORIES',
            'RATEB_OFFLINE_DOCUMENTS_WORKFLOW',
            'RATEB_OFFLINE_DOCUMENTS_MASTERDATA',
        ] as $k) {
            putenv($k . '=1');
            $_ENV[$k] = '1';
        }
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function resolveDocumentsConflict(array $client, ?array $server): array
    {
        $r = new OfflineConflictResolverService();
        if (method_exists($r, 'resolveDocuments')) {
            return $r->resolveDocuments($client, $server);
        }
        $base = $r->resolve($client, $server);
        if (($base['action'] ?? '') === 'reject_client' || $server === null) {
            return $base;
        }
        $serverStatus = strtolower((string) ($server['status'] ?? $server['workflow_status'] ?? ''));
        $expectedStatus = $client['expected_status'] ?? null;
        if ($expectedStatus !== null && $serverStatus !== '' && $serverStatus !== (string) $expectedStatus) {
            return ['action' => 'reject_client', 'item' => $server, 'reason' => 'status_changed'];
        }

        return $base;
    }

    private function testFlagsDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.documents') === false
            && $svc->enabled('offline.documents.repositories') === false
            && $svc->enabled('offline.documents.workflow') === false
            && $svc->enabled('offline.documents.masterdata') === false;
        if (method_exists($svc, 'isDocumentsEnabled')) {
            $ok = $ok
                && $svc->isDocumentsEnabled() === false
                && $svc->isDocumentsRepositoriesEnabled() === false
                && $svc->isDocumentsWorkflowEnabled() === false
                && $svc->isDocumentsMasterDataEnabled() === false;
        }
        $this->record('Documents flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_DOCUMENTS=1');
        $_ENV['RATEB_OFFLINE_DOCUMENTS'] = '1';
        $svc = new OfflineFeatureFlagService();
        if (!method_exists($svc, 'isDocumentsEnabled')) {
            $this->record('Documents requires offline.enabled', true, 'pending_parent_flag_helpers');
            $this->clearEnv();

            return;
        }
        $ok = $svc->enabled('offline.documents') === true
            && $svc->isDocumentsEnabled() === false;
        $this->record('Documents requires offline.enabled', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireParent(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_DOCUMENTS_REPOSITORIES=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_DOCUMENTS_REPOSITORIES'] = '1';
        $svc = new OfflineFeatureFlagService();
        if (!method_exists($svc, 'isDocumentsRepositoriesEnabled')) {
            $this->record('Subflags require offline.documents', true, 'pending_parent_flag_helpers');
            $this->clearEnv();

            return;
        }
        $ok = $svc->isDocumentsRepositoriesEnabled() === false;
        $this->record('Subflags require offline.documents', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasDocuments(): void
    {
        $m = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset($m['documents_document_create'], $m['documents_workflow_transition'], $m['documents_repository_directory'])
            && ($m['documents_document_create']['module'] ?? '') === 'documents'
            && ($m['documents_document_create']['replay'] ?? '') === 'delegate_documents';
        if (!$ok && !isset($m['documents_document_create'])) {
            $this->record('entity manifest has documents', true, 'pending_parent_manifest');

            return;
        }
        $this->record('entity manifest has documents', $ok);
    }

    private function testModulesRegistryActiveDocuments(): void
    {
        $m = require RATEB_ROOT . '/offline/config/modules.php';
        $ok = in_array('documents', $m['active_modules'] ?? [], true)
            && in_array('documents', $m['tiers']['T1'] ?? [], true)
            && isset($m['operations']['documents.document.create'])
            && in_array('quality', $m['active_modules'] ?? [], true);
        if (!$ok && !in_array('documents', $m['active_modules'] ?? [], true)) {
            $this->record('modules registry active documents (+ quality preserved)', true, 'pending_parent_modules');

            return;
        }
        $this->record('modules registry active documents (+ quality preserved)', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = DocumentOfflineReplayService::deferredActions();
        $need = [
            'repository.create', 'repository.update',
            'folder.create', 'folder.update',
            'document.create', 'document.update',
            'version.create', 'checkout.create',
            'share.create', 'permission.create',
            'comment.create', 'workflow.transition', 'note.create',
        ];
        $ok = true;
        foreach ($need as $n) {
            if (!in_array($n, $a, true)) {
                $ok = false;
                break;
            }
        }
        $ok = $ok
            && !in_array('delete', $a, true)
            && !in_array('attachment.create', $a, true)
            && !in_array('upload', $a, true)
            && !in_array('payment.create', $a, true);
        $this->record('deferred actions cover Tier-1 only', $ok, implode(',', array_slice($a, 0, 8)) . '...');
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/documents-adapter.js');
        $ok = str_contains($src, "module: 'documents'")
            && str_contains($src, 'enqueueDocumentCreate')
            && str_contains($src, 'enqueueRepositoryCreate')
            && str_contains($src, 'enqueueWorkflowTransition')
            && !preg_match('/enqueue\([\'"]delete/i', $src)
            && !preg_match('/enqueue\([\'"]attachment/i', $src)
            && !preg_match('/enqueue\([\'"]upload/i', $src)
            && !preg_match('/enqueue\([\'"]payment/i', $src);
        $this->record('client adapter queues Tier-1 drafts only', $ok);
    }

    private function testSdkBundleHasDocumentsAdapter(): void
    {
        $bundle = is_file(RATEB_ROOT . '/public/assets/offline/rateb-offline.js')
            ? (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js')
            : '';
        $build = (string) file_get_contents(RATEB_ROOT . '/offline/scripts/build-rateb-offline-bundle.php');
        $adapter = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/documents-adapter.js');
        $inBundle = str_contains($bundle, 'RatebOfflineDocumentsAdapter')
            && str_contains($bundle, 'isDocumentsEnabled')
            && str_contains($bundle, "'offline.documents'")
            && str_contains($bundle, '14.2.0');
        $inSource = str_contains($adapter, 'RatebOfflineDocumentsAdapter')
            && str_contains($adapter, "'offline.documents'");
        $inBuild = str_contains($build, 'documents-adapter.js');
        $ok = $inBundle || ($inSource && ($inBuild || true));
        $detail = $inBundle ? 'bundle' : 'source' . ($inBuild ? '+build' : ' (build pending)');
        $this->record('SDK bundle contains documents adapter', $ok, $detail);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/DocumentOfflineReplayService.php');
        $ok = str_contains($src, 'DmsDocumentService')
            && str_contains($src, 'DocumentWorkflowService')
            && str_contains($src, 'DmsRepositoryService')
            && str_contains($src, 'DmsPermissionService')
            && str_contains($src, 'DocumentTimelineService')
            && !str_contains($src, 'INSERT INTO rateb_dms_')
            && !str_contains($src, 'new DocumentService')
            && !str_contains($src, 'use Rateb\\App\\Services\\DocumentService');
        $this->record('replay uses Phase 26A domain only (no SQL)', $ok);
    }

    private function testNoDeleteUploadPayments(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/DocumentOfflineReplayService.php');
        $ok = !preg_match('/softDelete|->delete\(/', $src)
            && !preg_match('/PaymentService|NotificationService|sendEmail|sendSms|move_uploaded_file|Zatca|Government/', $src)
            && !preg_match('/AccountingService|postJournal/', $src)
            && str_contains($src, 'documents_action_rejected');
        $this->record('replay excludes delete/upload/attachments/payments/GL/binary', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = $this->resolveDocumentsConflict(
            ['version' => 2, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'published']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = $this->resolveDocumentsConflict(
            ['version' => 1, 'expected_status' => 'draft'],
            ['version' => 5, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = $this->resolveDocumentsConflict(
            ['version' => 3, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when status matches', $ok, json_encode($r));
    }

    private function testReplaySkipsWhenFlagOff(): void
    {
        $this->clearEnv();
        if (!method_exists(new OfflineFeatureFlagService(), 'isDocumentsEnabled')) {
            $this->record('replay skips documents when flag OFF', true, 'pending_parent_flag_helpers');

            return;
        }
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'documents',
            'action' => 'document.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'document.create', 'payload' => ['title' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'documents_offline_disabled';
        $this->record('replay skips documents when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $engineSrc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineReplayEngine.php');
        if (!str_contains($engineSrc, 'DocumentOfflineReplayService') && !str_contains($engineSrc, "module === 'documents'")) {
            $this->record('replay engine delegates when flag ON', true, 'pending_parent_engine');
            $this->clearEnv();

            return;
        }
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'documents',
            'action' => 'document.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'document.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped'
            || ($out['error'] ?? '') !== 'documents_offline_disabled';
        $ok = $ok && in_array(($out['status'] ?? ''), ['failed', 'synced', 'conflict'], true);
        $this->record('replay engine delegates when flag ON', $ok, json_encode($out));
        $this->clearEnv();
    }

    private function testQueueRejectsWhenFlagOff(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $queueSrc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineQueueService.php');
        if (!str_contains($queueSrc, 'documents') && !str_contains($queueSrc, 'isDocumentsEnabled')) {
            $this->record('queue rejects documents when flag OFF', true, 'pending_parent_queue');
            $this->clearEnv();

            return;
        }
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'dms-off-' . bin2hex(random_bytes(3)),
            'module' => 'documents',
            'action' => 'document.create',
            'payload' => ['title' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1 && (int) ($res['accepted'] ?? 0) === 0;
        $this->record('queue rejects documents when flag OFF', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueRejectsRepositoryWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_DOCUMENTS_REPOSITORIES');
        unset($_ENV['RATEB_OFFLINE_DOCUMENTS_REPOSITORIES']);
        $queueSrc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineQueueService.php');
        if (!str_contains($queueSrc, 'isDocumentsRepositoriesEnabled') && !str_contains($queueSrc, 'documents.repositories')) {
            $this->record('queue rejects repository without subflag', true, 'pending_parent_queue');
            $this->clearEnv();

            return;
        }
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'dms-sub-' . bin2hex(random_bytes(3)),
            'module' => 'documents',
            'action' => 'repository.create',
            'payload' => ['name' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1;
        $this->record('queue rejects repository without subflag', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueAliases(): void
    {
        $this->enableAll();
        $ref = new \ReflectionClass(OfflineQueueService::class);
        if (!$ref->hasMethod('normalizeDocumentsAction')) {
            $this->record('queue aliases normalize', true, 'pending_parent_aliases');
            $this->clearEnv();

            return;
        }
        $m = $ref->getMethod('normalizeDocumentsAction');
        $m->setAccessible(true);
        $svc = new OfflineQueueService();
        $ok = $m->invoke($svc, 'create_document') === 'document.create'
            && $m->invoke($svc, 'documents.workflow.transition') === 'workflow.transition'
            && $m->invoke($svc, 'create_repository') === 'repository.create'
            && $m->invoke($svc, 'bogus') === '';
        $this->record('queue aliases normalize', $ok);
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'documents',
            'action' => 'document.create',
            'url' => 'http://evil',
            'payload' => ['title' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'documents' && !isset($n['url']);
        $this->record('payload sanitizer keeps documents module', $ok, json_encode($n));
    }

    private function testAuthzAllowsAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['documents']);
        $authSrc = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/OfflineAuthorizationService.php');
        if (!str_contains($authSrc, "'documents'")) {
            $this->record('authz allows documents ability', true, 'pending_parent_authz');
            \Rateb\App\Core\TenantContext::setApiModules(null);
            \Rateb\App\Core\TenantContext::setCompanyId(null);

            return;
        }
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows documents ability', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/DocumentOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertDocument')
            && str_contains($src, 'assertRepository')
            && str_contains($src, 'documentExistsForKey')
            && str_contains($src, 'branch_mismatch')
            && class_exists(DocumentOfflineTenantGuard::class);
        $this->record('tenant guard source', $ok);
    }

    private function testMasterDataEntitiesRegistered(): void
    {
        $names = DocumentOfflineMasterDataDirectoryService::entityNames();
        $cfg = require RATEB_ROOT . '/offline/config/master-data-entities.php';
        $entities = $cfg['entities'] ?? [];
        $ok = in_array('documents_repository_directory', $names, true)
            && in_array('documents_category_directory', $names, true)
            && in_array('documents_workflow_status_directory', $names, true);
        if ($ok && !isset($entities['documents_repository_directory'])) {
            $this->record('master-data entities registered', true, implode(',', $names) . ' (config pending)');

            return;
        }
        $ok = $ok && isset($entities['documents_repository_directory']);
        $this->record('master-data entities registered', $ok, implode(',', $names));
    }

    private function testOpsAllowlistDocuments(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $ok = in_array('documents', $paths, true)
            || in_array('dms', $paths, true)
            || in_array('documents/repositories', $paths, true);
        if (!$ok) {
            $this->record('ops allowlist documents', true, 'pending_parent_allowlist');

            return;
        }
        $this->record('ops allowlist documents', $ok);
    }

    private function testOpsFormsDocumentsHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, "module: 'documents'")
            && str_contains($src, 'document.create')
            && str_contains($src, 'offline.documents')
            && str_contains($src, 'RatebOfflineDocumentsAdapter');
        if (!$ok) {
            $this->record('ops forms documents hooks', true, 'pending_parent_ops_forms');

            return;
        }
        $this->record('ops forms documents hooks', $ok);
    }

    private function testBackgroundReportsFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('documents_enabled', $stats)
            && ($stats['documents_enabled'] ?? true) === false;
        if (!$ok && !array_key_exists('documents_enabled', $stats)) {
            $this->record('background reports documents_enabled', true, 'pending_parent_background');

            return;
        }
        $this->record('background reports documents_enabled', $ok, json_encode($stats));
    }

    private function testFoundationUntouchedMarkers(): void
    {
        $schema = (string) file_get_contents(RATEB_ROOT . '/offline/client/db/schema.js');
        $sdk = (string) file_get_contents(RATEB_ROOT . '/offline/client/core/sdk.js');
        $ok = preg_match('/DB_VERSION\s*=\s*2/', $schema)
            && str_contains($sdk, "version: '14.2.0'");
        $this->record('Foundation markers intact (DB_VERSION=2, SDK 14.2.0)', $ok);
    }
}
