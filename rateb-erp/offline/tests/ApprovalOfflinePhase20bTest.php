<?php

declare(strict_types=1);

/**
 * Phase 20B — Approval Offline (Tier 1 drafts) tests.
 *
 * Run: php offline/tests/run-approval-offline-tests.php
 */

use Rateb\App\Offline\OfflineModule;
use Rateb\App\Offline\Services\ApprovalOfflineMasterDataDirectoryService;
use Rateb\App\Offline\Services\ApprovalOfflineReplayService;
use Rateb\App\Offline\Services\ApprovalOfflineTenantGuard;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBackgroundSync;
use Rateb\App\Offline\Services\OfflineConflictResolverService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePayloadSanitizer;
use Rateb\App\Offline\Services\OfflineQueueService;
use Rateb\App\Offline\Services\OfflineReplayEngine;

final class ApprovalOfflinePhase20bTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->clearEnv();

        $this->testFlagsDefaultOff();
        $this->testRequiresMaster();
        $this->testSubflagsRequireApproval();
        $this->testEntityManifestHasApproval();
        $this->testModulesRegistryActiveApproval();
        $this->testDeferredActionsCoverRequirements();
        $this->testClientAdapterSource();
        $this->testSdkBundleHasApprovalAdapter();
        $this->testReplayUsesExistingDomainOnly();
        $this->testNoDecisionEscalateNotificationsAttachmentsEmailSmsPaymentsGov();
        $this->testConflictStatusChanged();
        $this->testConflictServerNewerStillWins();
        $this->testConflictAcceptWhenStatusMatches();
        $this->testReplaySkipsWhenFlagOff();
        $this->testReplayEngineDelegatesWhenFlagOn();
        $this->testQueueRejectsWhenFlagOff();
        $this->testQueueRejectsRequestsWithoutSubflag();
        $this->testQueueAliases();
        $this->testPayloadSanitizerKeepsApprovalModule();
        $this->testAuthzAllowsApprovalAbility();
        $this->testTenantGuardSource();
        $this->testMasterDataEntitiesRegistered();
        $this->testOpsAllowlistApproval();
        $this->testOpsFormsApprovalHooks();
        $this->testBackgroundReportsApprovalFlag();
        $this->testFoundationUntouchedMarkers();

        $this->clearEnv();

        return $this->results;
    }

    private function clearEnv(): void
    {
        foreach ([
            'RATEB_OFFLINE_ENABLED',
            'RATEB_OFFLINE_APPROVAL',
            'RATEB_OFFLINE_APPROVAL_REQUESTS',
            'RATEB_OFFLINE_APPROVAL_WORKFLOW',
            'RATEB_OFFLINE_APPROVAL_MASTERDATA',
        ] as $k) {
            putenv($k);
            unset($_ENV[$k]);
        }
    }

    private function enableBase(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_APPROVAL=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_APPROVAL'] = '1';
    }

    private function enableAll(): void
    {
        $this->enableBase();
        foreach ([
            'RATEB_OFFLINE_APPROVAL_REQUESTS',
            'RATEB_OFFLINE_APPROVAL_WORKFLOW',
            'RATEB_OFFLINE_APPROVAL_MASTERDATA',
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

    private function testFlagsDefaultOff(): void
    {
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.approval') === false
            && $svc->isApprovalEnabled() === false
            && $svc->isApprovalRequestsEnabled() === false
            && $svc->isApprovalWorkflowEnabled() === false
            && $svc->isApprovalMasterDataEnabled() === false;
        $this->record('Approval flags default OFF', $ok);
    }

    private function testRequiresMaster(): void
    {
        putenv('RATEB_OFFLINE_APPROVAL=1');
        $_ENV['RATEB_OFFLINE_APPROVAL'] = '1';
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->enabled('offline.approval') === true && $svc->isApprovalEnabled() === false;
        $this->record('Approval requires offline.enabled', $ok);
        $this->clearEnv();
    }

    private function testSubflagsRequireApproval(): void
    {
        putenv('RATEB_OFFLINE_ENABLED=1');
        putenv('RATEB_OFFLINE_APPROVAL_REQUESTS=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $_ENV['RATEB_OFFLINE_APPROVAL_REQUESTS'] = '1';
        $svc = new OfflineFeatureFlagService();
        $ok = $svc->isApprovalRequestsEnabled() === false;
        $this->record('Subflags require offline.approval', $ok);
        $this->clearEnv();
    }

    private function testEntityManifestHasApproval(): void
    {
        $m = require RATEB_ROOT . '/offline/config/entity-manifest.php';
        $ok = isset($m['approval_request_create'], $m['approval_workflow_transition'], $m['approval_template_directory'])
            && ($m['approval_request_create']['module'] ?? '') === 'approval'
            && ($m['approval_request_create']['replay'] ?? '') === 'delegate_approval';
        $this->record('entity manifest has Approval', $ok);
    }

    private function testModulesRegistryActiveApproval(): void
    {
        $m = require RATEB_ROOT . '/offline/config/modules.php';
        $ok = in_array('approval', $m['active_modules'] ?? [], true)
            && in_array('approval', $m['tiers']['T1'] ?? [], true)
            && isset($m['operations']['approval.approval_request.create']);
        $this->record('modules registry active Approval', $ok);
    }

    private function testDeferredActionsCoverRequirements(): void
    {
        $a = ApprovalOfflineReplayService::deferredActions();
        $need = [
            'approval_request.create', 'approval_request.update', 'workflow.transition',
            'comment.create', 'delegation.create', 'note.create',
        ];
        $ok = true;
        foreach ($need as $n) {
            if (!in_array($n, $a, true)) {
                $ok = false;
                break;
            }
        }
        $ok = $ok
            && !in_array('approve', $a, true)
            && !in_array('reject', $a, true)
            && !in_array('escalate', $a, true);
        $this->record('deferred actions cover Tier-1 only', $ok, implode(',', $a));
    }

    private function testClientAdapterSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/approval-adapter.js');
        $ok = str_contains($src, "module: 'approval'")
            && str_contains($src, 'enqueueRequestCreate')
            && str_contains($src, 'enqueueWorkflowTransition')
            && str_contains($src, 'enqueueDelegationCreate')
            && !preg_match('/enqueue\([\'"]approve/i', $src)
            && !preg_match('/enqueue\([\'"]reject/i', $src)
            && !preg_match('/enqueue\([\'"]escalate/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*payment/i', $src)
            && !preg_match('/enqueue\([\'"][^\'"]*email/i', $src);
        $this->record('client adapter queues Tier-1 drafts only', $ok);
    }

    private function testSdkBundleHasApprovalAdapter(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
        $ok = str_contains($src, 'RatebOfflineApprovalAdapter')
            && str_contains($src, 'isApprovalEnabled')
            && str_contains($src, 'approvals: function')
            && str_contains($src, "'offline.approval'")
            && str_contains($src, '14.2.0');
        $this->record('SDK bundle contains Approval adapter', $ok);
    }

    private function testReplayUsesExistingDomainOnly(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ApprovalOfflineReplayService.php');
        $ok = str_contains($src, 'ApprovalRequestService')
            && str_contains($src, 'ApprovalWorkflowService')
            && str_contains($src, 'ApprovalCommentService')
            && str_contains($src, 'ApprovalDelegationService')
            && !str_contains($src, 'INSERT INTO rateb_eap_requests')
            && !preg_match('/new\s+\\\\?WorkflowService\b/', $src);
        $this->record('replay uses existing Approval domain only', $ok);
    }

    private function testNoDecisionEscalateNotificationsAttachmentsEmailSmsPaymentsGov(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ApprovalOfflineReplayService.php');
        $ok = !preg_match('/softDelete|->delete\(/', $src)
            && !preg_match('/ApprovalEscalationService|NotificationService|PaymentService|sendEmail|sendSms|move_uploaded_file|Zatca|Government/', $src)
            && str_contains($src, 'approval_decision_online_only')
            && str_contains($src, 'APPROVED')
            && str_contains($src, 'REJECTED');
        $this->record('replay excludes decisions/escalate/notifications/attachments/email/sms/payments/gov', $ok);
    }

    private function testConflictStatusChanged(): void
    {
        $r = (new OfflineConflictResolverService())->resolveApproval(
            ['version' => 2, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'pending']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'status_changed';
        $this->record('conflict status_changed', $ok, json_encode($r));
    }

    private function testConflictServerNewerStillWins(): void
    {
        $r = (new OfflineConflictResolverService())->resolveApproval(
            ['version' => 1, 'expected_status' => 'draft'],
            ['version' => 5, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'reject_client' && ($r['reason'] ?? '') === 'server_newer';
        $this->record('conflict server_newer still wins', $ok, json_encode($r));
    }

    private function testConflictAcceptWhenStatusMatches(): void
    {
        $r = (new OfflineConflictResolverService())->resolveApproval(
            ['version' => 3, 'expected_status' => 'draft'],
            ['version' => 1, 'status' => 'draft']
        );
        $ok = ($r['action'] ?? '') === 'accept_client';
        $this->record('conflict accept when status matches', $ok, json_encode($r));
    }

    private function testReplaySkipsWhenFlagOff(): void
    {
        $this->clearEnv();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'approval',
            'action' => 'approval_request.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'approval_request.create', 'payload' => ['title' => 'x']]),
        ]);
        $ok = ($out['status'] ?? '') === 'skipped'
            && ($out['error'] ?? '') === 'approval_offline_disabled';
        $this->record('replay skips Approval when flag OFF', $ok, json_encode($out));
    }

    private function testReplayEngineDelegatesWhenFlagOn(): void
    {
        $this->enableAll();
        $out = (new OfflineReplayEngine())->replay([
            'module' => 'approval',
            'action' => 'approval_request.create',
            'company_id' => 1,
            'payload' => json_encode(['action' => 'approval_request.create', 'payload' => []]),
        ]);
        $ok = ($out['status'] ?? '') !== 'skipped' || ($out['error'] ?? '') !== 'approval_offline_disabled';
        $ok = $ok && in_array(($out['status'] ?? ''), ['failed', 'synced', 'conflict'], true);
        $this->record('replay engine delegates when flag ON', $ok, json_encode($out));
        $this->clearEnv();
    }

    private function testQueueRejectsWhenFlagOff(): void
    {
        $this->clearEnv();
        putenv('RATEB_OFFLINE_ENABLED=1');
        $_ENV['RATEB_OFFLINE_ENABLED'] = '1';
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'eap-off-' . bin2hex(random_bytes(3)),
            'module' => 'approval',
            'action' => 'approval_request.create',
            'payload' => ['title' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1 && (int) ($res['accepted'] ?? 0) === 0;
        $this->record('queue rejects Approval when flag OFF', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueRejectsRequestsWithoutSubflag(): void
    {
        $this->enableBase();
        putenv('RATEB_OFFLINE_APPROVAL_REQUESTS');
        unset($_ENV['RATEB_OFFLINE_APPROVAL_REQUESTS']);
        $res = (new OfflineQueueService())->enqueueBatch([[
            'client_id' => 'eap-req-' . bin2hex(random_bytes(3)),
            'module' => 'approval',
            'action' => 'approval_request.create',
            'payload' => ['title' => 'x'],
        ]], ['company_id' => 1]);
        $ok = (int) ($res['rejected'] ?? 0) >= 1;
        $this->record('queue rejects requests without subflag', $ok, json_encode($res));
        $this->clearEnv();
    }

    private function testQueueAliases(): void
    {
        $this->enableAll();
        $ref = new \ReflectionClass(OfflineQueueService::class);
        $m = $ref->getMethod('normalizeApprovalAction');
        $m->setAccessible(true);
        $svc = new OfflineQueueService();
        $ok = $m->invoke($svc, 'create_approval_request') === 'approval_request.create'
            && $m->invoke($svc, 'approval.workflow.transition') === 'workflow.transition'
            && $m->invoke($svc, 'create_delegation') === 'delegation.create'
            && $m->invoke($svc, 'bogus') === '';
        $this->record('queue aliases normalize', $ok);
        $this->clearEnv();
    }

    private function testPayloadSanitizerKeepsApprovalModule(): void
    {
        $n = (new OfflinePayloadSanitizer())->normalize([
            'module' => 'approval',
            'action' => 'approval_request.create',
            'url' => 'http://evil',
            'payload' => ['title' => 't'],
        ]);
        $ok = ($n['module'] ?? '') === 'approval' && !isset($n['url']);
        $this->record('payload sanitizer keeps approval module', $ok, json_encode($n));
    }

    private function testAuthzAllowsApprovalAbility(): void
    {
        \Rateb\App\Core\TenantContext::setCompanyId(1);
        \Rateb\App\Core\TenantContext::setApiModules(['approval']);
        $ok = (new OfflineAuthorizationService())->canManageSync() === true;
        $this->record('authz allows approval ability', $ok);
        \Rateb\App\Core\TenantContext::setApiModules(null);
        \Rateb\App\Core\TenantContext::setCompanyId(null);
    }

    private function testTenantGuardSource(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/server/Services/ApprovalOfflineTenantGuard.php');
        $ok = str_contains($src, 'assertRequest')
            && str_contains($src, 'requestExistsForKey')
            && str_contains($src, 'branch_mismatch')
            && class_exists(ApprovalOfflineTenantGuard::class);
        $this->record('tenant guard source', $ok);
    }

    private function testMasterDataEntitiesRegistered(): void
    {
        $names = ApprovalOfflineMasterDataDirectoryService::entityNames();
        $cfg = require RATEB_ROOT . '/offline/config/master-data-entities.php';
        $entities = $cfg['entities'] ?? [];
        $ok = in_array('approval_template_directory', $names, true)
            && in_array('approval_chain_directory', $names, true)
            && in_array('approval_stage_directory', $names, true)
            && in_array('approval_rule_directory', $names, true)
            && in_array('approval_delegation_directory', $names, true)
            && isset($entities['approval_template_directory']);
        $this->record('master-data entities registered', $ok, implode(',', $names));
    }

    private function testOpsAllowlistApproval(): void
    {
        $cfg = OfflineModule::opsPageAllowlist();
        $paths = $cfg['paths'] ?? [];
        $ok = in_array('approvals', $paths, true)
            && in_array('approvals/requests', $paths, true)
            && in_array('approvals/templates', $paths, true);
        $this->record('ops allowlist Approval', $ok);
    }

    private function testOpsFormsApprovalHooks(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/offline/client/adapters/ops-forms-adapter.js');
        $ok = str_contains($src, "module: 'approval'")
            && str_contains($src, 'approval_request.create')
            && str_contains($src, 'offline.approval')
            && str_contains($src, 'RatebOfflineApprovalAdapter');
        $this->record('ops forms Approval hooks', $ok);
    }

    private function testBackgroundReportsApprovalFlag(): void
    {
        $this->clearEnv();
        $stats = (new OfflineBackgroundSync())->process(1, 1);
        $ok = array_key_exists('approval_enabled', $stats)
            && ($stats['approval_enabled'] ?? true) === false;
        $this->record('background reports approval_enabled', $ok, json_encode($stats));
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
