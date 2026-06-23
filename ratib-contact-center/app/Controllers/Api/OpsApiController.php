<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\Ops\OpsChecklistService;
use Ratib\ContactCenter\App\Application\Services\Ops\OpsDiagnosticService;
use Ratib\ContactCenter\App\Application\Services\Ops\OpsPbxService;
use Ratib\ContactCenter\App\Application\Services\Ops\OpsProvisioningService;
use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
use Ratib\ContactCenter\App\Core\TenantContext;

final class OpsApiController
{
    public function __construct(
        private readonly OpsPbxService $pbx = new OpsPbxService(),
        private readonly OpsDiagnosticService $diagnostics = new OpsDiagnosticService(),
        private readonly OpsProvisioningService $provisioning = new OpsProvisioningService(),
        private readonly OpsChecklistService $checklist = new OpsChecklistService()
    ) {
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            RealtimeOrchestrator::boot();
            $action = (string) ($_GET['action'] ?? '');
            $body = $this->parseJsonBody();
            $input = array_merge($body, $_GET);
            echo json_encode($this->handleAction($action, $input), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return array<string, mixed> */
    public function handleAction(string $action, array $input): array
    {
        AuthContext::requirePermission('rcc.ops.view');
        $tenantId = $this->resolveTenantId($input);
        $userId = AuthContext::userId();
        TenantContext::set($tenantId);

        return match ($action) {
            'health_center' => $this->ok($this->diagnostics->healthCenter($tenantId)),
            'tenants_list' => $this->requirePerm('rcc.tenants.manage') ?: $this->ok(['tenants' => $this->listTenants()]),

            'pbx_list' => $this->requirePerm('rcc.ops.pbx') ?: $this->ok(['servers' => $this->pbx->list($tenantId), 'dialplan' => $this->pbx->dialplanPackageInfo()]),
            'pbx_save' => $this->requirePerm('rcc.ops.pbx') ?: $this->ok(['server' => $this->pbx->save($tenantId, $input, $userId)]),
            'pbx_test' => $this->requirePerm('rcc.ops.pbx') ?: $this->ok($this->pbx->testAmi($tenantId, (int) ($input['pbx_id'] ?? 0), $userId)),
            'pbx_activate' => $this->runPerm('rcc.ops.pbx', function () use ($tenantId, $input, $userId) {
                $this->pbx->activate($tenantId, (int) ($input['pbx_id'] ?? 0), $userId);
                return $this->ok(['activated' => true]);
            }),

            'sip_list' => $this->requirePerm('rcc.ops.sip') ?: $this->ok(['extensions' => $this->provisioning->listSipExtensions($tenantId)]),
            'sip_save' => $this->requirePerm('rcc.ops.sip') ?: $this->ok($this->provisioning->saveSipExtension($tenantId, $input, $userId)),
            'sip_delete' => $this->runPerm('rcc.ops.sip', function () use ($tenantId, $input, $userId) {
                $this->provisioning->deleteSipExtension($tenantId, (int) ($input['id'] ?? 0), $userId);
                return $this->ok(['deleted' => true]);
            }),

            'queue_list' => $this->requirePerm('rcc.ops.queues') ?: $this->ok(['queues' => $this->provisioning->listQueues($tenantId)]),
            'queue_save' => $this->requirePerm('rcc.ops.queues') ?: $this->ok($this->provisioning->saveQueue($tenantId, $input, $userId)),
            'queue_members_save' => $this->runPerm('rcc.ops.queues', function () use ($tenantId, $input, $userId) {
                $agents = is_array($input['agent_ids'] ?? null) ? $input['agent_ids'] : [];
                $this->provisioning->saveQueueMembers($tenantId, (int) ($input['queue_id'] ?? 0), $agents, $userId);
                return $this->ok(['saved' => true]);
            }),

            'ivr_list' => $this->requirePerm('rcc.ops.ivr') ?: $this->ok(['flows' => $this->provisioning->listIvrFlows($tenantId)]),
            'ivr_save' => $this->requirePerm('rcc.ops.ivr') ?: $this->ok($this->provisioning->saveIvrFlow(
                $tenantId,
                is_array($input['flow'] ?? null) ? $input['flow'] : $input,
                is_array($input['nodes'] ?? null) ? $input['nodes'] : [],
                $userId
            )),
            'ivr_publish' => $this->runPerm('rcc.ops.ivr', function () use ($tenantId, $input, $userId) {
                $this->provisioning->publishIvrFlow($tenantId, (int) ($input['flow_id'] ?? 0), $userId);
                return $this->ok(['published' => true]);
            }),

            'agent_list' => $this->requirePerm('rcc.ops.agents') ?: $this->ok(['agents' => $this->provisioning->listAgents($tenantId)]),
            'agent_provision' => $this->requirePerm('rcc.ops.agents') ?: $this->ok($this->provisioning->provisionAgent($tenantId, $input, $userId)),

            'diag_ami' => $this->requirePerm('rcc.ops.diagnostics') ?: $this->ok($this->diagnostics->diagAmi($tenantId)),
            'diag_webrtc' => $this->requirePerm('rcc.ops.diagnostics') ?: $this->ok($this->diagnostics->diagWebrtc(
                $tenantId,
                (int) ($input['agent_id'] ?? 0) ?: AuthContext::agentIdOrZero()
            )),
            'diag_hub' => $this->requirePerm('rcc.ops.diagnostics') ?: $this->ok($this->diagnostics->diagHub()),
            'diag_voice_worker' => $this->requirePerm('rcc.ops.diagnostics') ?: $this->ok($this->diagnostics->diagVoiceWorker()),

            'hub_status' => $this->requirePerm('rcc.ops.hub') ?: $this->ok($this->diagnostics->diagHub()),
            'hub_start' => $this->runPerm('rcc.ops.hub', fn () => $this->ok($this->startHub())),

            'checklist_list' => $this->requirePerm('rcc.ops.golive') ?: $this->ok(['items' => $this->checklist->list($tenantId), 'summary' => $this->checklist->summary($tenantId)]),
            'checklist_update' => $this->runPerm('rcc.ops.golive', function () use ($tenantId, $input, $userId) {
                $this->checklist->update($tenantId, (string) ($input['step_slug'] ?? ''), (string) ($input['status'] ?? ''), $userId, isset($input['notes']) ? (string) $input['notes'] : null);
                return $this->ok(['summary' => $this->checklist->summary($tenantId)]);
            }),
            'checklist_auto_verify' => $this->requirePerm('rcc.ops.golive') ?: $this->ok($this->checklist->autoVerify($tenantId, $userId)),
            'checklist_summary' => $this->requirePerm('rcc.ops.golive') ?: $this->ok($this->checklist->summary($tenantId)),

            default => ['ok' => false, 'error' => 'Unknown action: ' . $action],
        };
    }

    /** @param array<string, mixed> $input */
    private function resolveTenantId(array $input): int
    {
        $tenantId = AuthContext::tenantId();
        if (AuthContext::can('rcc.tenants.manage')) {
            $requested = (int) ($input['tenant_id'] ?? 0);
            if ($requested > 0) {
                return $requested;
            }
        }
        return $tenantId;
    }

    /** @return array<string, mixed>|null error response */
    private function requirePerm(string $perm): ?array
    {
        if (!AuthContext::can($perm)) {
            return ['ok' => false, 'error' => 'Permission denied: ' . $perm];
        }
        return null;
    }

    /** @return array<string, mixed> */
    private function runPerm(string $perm, callable $fn): array
    {
        $denied = $this->requirePerm($perm);
        return $denied ?? $fn();
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function ok(array $data): array
    {
        return ['ok' => true] + $data;
    }

    /** @return list<array<string, mixed>> */
    private function listTenants(): array
    {
        $stmt = \Ratib\ContactCenter\App\Core\Database::connection()->query(
            "SELECT id, code, name, name_ar, status FROM rcc_tenants WHERE status = 'active' ORDER BY name"
        );
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    /** @return array<string, mixed> */
    private function startHub(): array
    {
        $root = defined('RCC_ROOT') ? RCC_ROOT : dirname(__DIR__, 3);
        $script = $root . '/bin/start-realtime-hub.sh';
        if (!is_file($script)) {
            return ['started' => false, 'message' => 'start script missing'];
        }
        @mkdir($root . '/storage/logs', 0755, true);
        if (function_exists('proc_open')) {
            $cmd = 'bash ' . escapeshellarg($script);
            $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
            if (is_resource($proc)) {
                if (isset($pipes[1])) {
                    fclose($pipes[1]);
                }
                if (isset($pipes[2])) {
                    fclose($pipes[2]);
                }
                proc_close($proc);
            }
        }
        usleep(800000);
        return $this->diagnostics->diagHub() + ['message' => 'start requested'];
    }

    /** @return array<string, mixed> */
    private function parseJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
