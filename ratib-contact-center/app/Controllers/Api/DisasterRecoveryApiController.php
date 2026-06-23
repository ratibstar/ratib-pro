<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\DisasterRecovery\BackupRestoreService;
use Ratib\ContactCenter\App\Application\Services\DisasterRecovery\MonitoringService;
use Ratib\ContactCenter\App\Application\Services\DisasterRecovery\PbxClusterService;
use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
use Ratib\ContactCenter\App\Core\TenantContext;

final class DisasterRecoveryApiController
{
    public function __construct(
        private readonly BackupRestoreService $backups = new BackupRestoreService(),
        private readonly MonitoringService $monitoring = new MonitoringService(),
        private readonly PbxClusterService $clusters = new PbxClusterService()
    ) {
    }

    public function handle(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            RealtimeOrchestrator::boot();
            $action = (string) ($_GET['action'] ?? '');
            $input = array_merge($this->parseJsonBody(), $_GET);
            echo json_encode($this->handleAction($action, $input), JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return array<string, mixed> */
    public function handleAction(string $action, array $input): array
    {
        AuthContext::requirePermission('rcc.backup.view');
        $tenantId = AuthContext::can('rcc.tenants.manage') && isset($input['tenant_id']) ? (int) $input['tenant_id'] : AuthContext::tenantId();
        $userId = AuthContext::userId();
        TenantContext::set($tenantId);

        return match ($action) {
            'backups_list' => $this->ok(['backups' => $this->backups->listBackups($tenantId)]),
            'backup_start' => $this->runPerm('rcc.backup.manage', fn () => $this->ok([
                'backup' => $this->backups->startBackup($tenantId, (string) ($input['type'] ?? 'tenant'), $userId),
            ])),
            'restore_queue' => $this->runPerm('rcc.backup.restore', fn () => $this->ok(
                $this->backups->queueRestore($tenantId, (int) ($input['backup_id'] ?? 0), $userId, isset($input['approver_user_id']) ? (int) $input['approver_user_id'] : null)
            )),
            'monitors_list' => $this->gate('rcc.monitoring.view', fn () => $this->ok(['monitors' => $this->monitoring->listMonitors($tenantId)])),
            'monitors_run' => $this->runPerm('rcc.monitoring.manage', fn () => $this->ok([
                'results' => isset($input['monitor_id'])
                    ? [$this->monitoring->runCheck((int) $input['monitor_id'])]
                    : $this->monitoring->runAll($tenantId),
            ])),
            'clusters_list' => $this->gate('rcc.pbx.cluster', fn () => $this->ok(['clusters' => $this->clusters->listClusters($tenantId)])),
            'cluster_create' => $this->runPerm('rcc.pbx.cluster', fn () => $this->ok([
                'cluster' => $this->clusters->createCluster($tenantId, $input, $userId),
            ])),
            'cluster_failover' => $this->runPerm('rcc.pbx.cluster', fn () => $this->ok(
                $this->clusters->failover((int) ($input['cluster_id'] ?? 0), (string) ($input['from_node'] ?? ''), (string) ($input['to_node'] ?? ''), $userId)
            )),
            default => ['ok' => false, 'error' => 'Unknown action: ' . $action],
        };
    }

    /** @return array<string, mixed> */
    private function ok(mixed $data): array
    {
        return is_array($data) && isset($data['ok']) ? $data : ['ok' => true] + (is_array($data) ? $data : ['data' => $data]);
    }

    private function gate(string $perm, callable $fn): array
    {
        AuthContext::requirePermission($perm);
        return $fn();
    }

    private function runPerm(string $perm, callable $fn): array
    {
        AuthContext::requirePermission($perm);
        return $fn();
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
