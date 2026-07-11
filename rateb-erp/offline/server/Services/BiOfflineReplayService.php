<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\BiAlertService;
use Rateb\App\Services\BiAnalyticsScopeService;
use Rateb\App\Services\BiDashboardService;
use Rateb\App\Services\BiDatasetService;
use Rateb\App\Services\BiExportService;
use Rateb\App\Services\BiForecastService;
use Rateb\App\Services\BiKpiService;
use Rateb\App\Services\BiReportService;
use Rateb\App\Services\BiScheduleService;
use Rateb\App\Services\BiTimelineService;
use Rateb\App\Services\BiTrendService;
use Rateb\App\Services\BiWidgetService;
use Rateb\App\Services\BusinessIntelligenceWorkflowService;

/**
 * Thin BI offline replay (Phase 27B) — delegates ONLY to Phase 27A domain services.
 * Tier-1 drafts only. No delete / binary / notifications / email / SMS /
 * payments / publish / download / export file generation.
 */
final class BiOfflineReplayService
{
    private const PREFIX = 'bi.';

    /**
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        $bare = [
            'dashboard.create',
            'kpi.create',
            'report.create',
            'widget.create',
            'dataset.create',
            'alert.create',
            'schedule.create',
            'export.create',
            'trend.create',
            'forecast.create',
            'scope.create',
            'workflow.transition',
            'note.create',
        ];
        $out = $bare;
        foreach ($bare as $a) {
            $out[] = self::PREFIX . $a;
        }

        return $out;
    }

    public function __construct(
        private ?BiOfflineTenantGuard $guard = null,
        private ?OfflineConflictResolverService $resolver = null,
    ) {
    }

    private function guard(): BiOfflineTenantGuard
    {
        return $this->guard ??= new BiOfflineTenantGuard();
    }

    private function resolver(): OfflineConflictResolverService
    {
        return $this->resolver ??= new OfflineConflictResolverService();
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array{status: string, error?: string, result?: array<string, mixed>, reason?: string}
     */
    public function replayFromQueueRow(array $queueRow): array
    {
        $decoded = $this->decodePayload($queueRow);
        $action = $this->normalizeAction(
            (string) ($decoded['action'] ?? $queueRow['action'] ?? '')
        );
        $inner = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
        unset($inner['branch_id'], $inner['company_id'], $inner['user_id'], $inner['device_id']);
        $idempotencyKey = substr(trim((string) (
            $queueRow['idempotency_key']
            ?? $decoded['client_id']
            ?? $decoded['idempotency_key']
            ?? ''
        )), 0, 64);

        if (!in_array($action, self::deferredActions(), true)
            && !in_array($this->normalizeAction($action), self::deferredActions(), true)) {
            return ['status' => 'skipped', 'error' => 'unknown_bi_action'];
        }
        $action = $this->normalizeAction($action);

        if (in_array($action, [
            'delete', 'attachment.create', 'upload', 'payment.create', 'bank_transfer',
            'accounting.post', 'inventory.post', 'notification.send', 'email.send', 'sms.send',
            'government.submit', 'approval.decide', 'publish', 'download', 'binary.upload',
        ], true)) {
            return ['status' => 'skipped', 'error' => 'bi_action_rejected'];
        }

        $flags = new OfflineFeatureFlagService();
        if (!$flags->isBiEnabled()) {
            return ['status' => 'skipped', 'error' => 'bi_offline_disabled'];
        }
        if ($action === 'workflow.transition' && !$flags->isBiWorkflowEnabled()) {
            return ['status' => 'skipped', 'error' => 'bi_workflow_offline_disabled'];
        }

        try {
            $scope = (new OfflineReplayScopeService())->fromQueueRow($queueRow);
            if ($scope['company_id'] < 1) {
                return ['status' => 'failed', 'error' => 'company_required'];
            }

            TenantContext::setCompanyId($scope['company_id']);
            if ($scope['user_id'] > 0) {
                SessionManager::set('rateb_user_id', $scope['user_id']);
            }

            $result = $this->replay($action, $scope, $inner, $idempotencyKey);

            return ['status' => 'synced', 'result' => $result];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if ($this->isConflictError($message)) {
                return ['status' => 'conflict', 'error' => $message, 'reason' => $message];
            }

            return ['status' => 'failed', 'error' => $message];
        }
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function replay(string $action, array $scope, array $inner, string $idempotencyKey): array
    {
        return match ($action) {
            'dashboard.create' => ['ok' => true, 'dashboard' => (new BiDashboardService())->create($this->stampNotes($inner, $idempotencyKey))],
            'kpi.create' => ['ok' => true, 'kpi' => (new BiKpiService())->create($this->stampNotes($inner, $idempotencyKey))],
            'report.create' => ['ok' => true, 'report' => (new BiReportService())->create($this->stampNotes($inner, $idempotencyKey))],
            'widget.create' => ['ok' => true, 'widget' => (new BiWidgetService())->create($this->stampNotes($inner, $idempotencyKey))],
            'dataset.create' => ['ok' => true, 'dataset' => (new BiDatasetService())->create($this->stampNotes($inner, $idempotencyKey))],
            'alert.create' => ['ok' => true, 'alert' => (new BiAlertService())->create($this->stampNotes($inner, $idempotencyKey))],
            'schedule.create' => ['ok' => true, 'schedule' => (new BiScheduleService())->create($this->stampNotes($inner, $idempotencyKey))],
            'export.create' => ['ok' => true, 'export' => (new BiExportService())->create($this->stampNotes($inner, $idempotencyKey))],
            'trend.create' => ['ok' => true, 'trend' => (new BiTrendService())->create($this->stampNotes($inner, $idempotencyKey))],
            'forecast.create' => ['ok' => true, 'forecast' => (new BiForecastService())->create($this->stampNotes($inner, $idempotencyKey))],
            'scope.create' => ['ok' => true, 'scope' => (new BiAnalyticsScopeService())->create($this->stampNotes($inner, $idempotencyKey))],
            'workflow.transition' => $this->workflowTransition($scope, $inner),
            'note.create' => $this->noteCreate($inner),
            default => throw new \RuntimeException('unknown_bi_action'),
        };
    }

    /**
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function stampNotes(array $inner, string $idempotencyKey): array
    {
        if ($idempotencyKey === '') {
            return $inner;
        }
        $notes = trim((string) ($inner['notes'] ?? ''));
        $inner['notes'] = trim($notes . ' [offline:' . $idempotencyKey . ']');

        return $inner;
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function workflowTransition(array $scope, array $inner): array
    {
        $entityType = strtolower(trim((string) ($inner['entity_type'] ?? BusinessIntelligenceWorkflowService::ENTITY_DASHBOARD)));
        if ($entityType === '') {
            $entityType = BusinessIntelligenceWorkflowService::ENTITY_DASHBOARD;
        }
        if (!in_array($entityType, BusinessIntelligenceWorkflowService::entityTypes(), true)) {
            throw new \InvalidArgumentException('invalid_bi_entity_type');
        }
        $id = (int) ($inner['entity_id'] ?? $inner['id'] ?? 0);
        $assert = match ($entityType) {
            BusinessIntelligenceWorkflowService::ENTITY_DASHBOARD => $this->guard()->assertDashboard($id, $scope),
            BusinessIntelligenceWorkflowService::ENTITY_REPORT => $this->guard()->assertReport($id, $scope),
            BusinessIntelligenceWorkflowService::ENTITY_KPI => $this->guard()->assertKpi($id, $scope),
            default => ['ok' => false, 'error' => 'invalid_bi_entity_type'],
        };
        if (!($assert['ok'] ?? false)) {
            throw new \RuntimeException((string) ($assert['error'] ?? 'tenant_mismatch'));
        }
        $server = $assert['dashboard'] ?? $assert['report'] ?? $assert['kpi'] ?? [];
        $this->maybeConflict($inner, is_array($server) ? $server : []);
        $to = trim((string) ($inner['to_status'] ?? $inner['target_status'] ?? ''));
        if ($to === '') {
            throw new \InvalidArgumentException('to_status_required');
        }
        // Offline may only advance early statuses (never publish).
        $allowedOffline = ['draft', 'archived'];
        if (!in_array($to, $allowedOffline, true)) {
            throw new \RuntimeException('bi_workflow_offline_denied');
        }
        $reason = isset($inner['reason']) ? (string) $inner['reason'] : null;
        $expectedVersion = isset($inner['expected_version']) ? (int) $inner['expected_version'] : null;

        return (new BusinessIntelligenceWorkflowService())->transition($entityType, $id, $to, $reason, $expectedVersion);
    }

    /**
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function noteCreate(array $inner): array
    {
        $title = trim((string) ($inner['title'] ?? $inner['event_type'] ?? 'Offline note'));
        $body = isset($inner['body']) ? (string) $inner['body'] : (isset($inner['notes']) ? (string) $inner['notes'] : null);
        $entityType = isset($inner['entity_type']) ? (string) $inner['entity_type'] : null;
        $entityId = isset($inner['entity_id']) ? (int) $inner['entity_id'] : null;
        (new BiTimelineService())->record(
            'offline_note',
            $title !== '' ? $title : 'Offline note',
            $entityType,
            $entityId > 0 ? $entityId : null,
            $body
        );

        return ['ok' => true, 'note' => true];
    }

    /**
     * @param array<string, mixed> $inner
     * @param array<string, mixed> $server
     */
    private function maybeConflict(array $inner, array $server): void
    {
        if (!isset($inner['expected_status'])) {
            return;
        }
        $status = strtolower((string) ($server['workflow_status'] ?? $server['status'] ?? 'draft'));
        $clientItem = [
            'version' => (int) ($inner['version'] ?? $inner['expected_version'] ?? 1),
            'expected_status' => $inner['expected_status'],
        ];
        $serverItem = [
            'version' => (int) ($server['version'] ?? $inner['server_version'] ?? 0),
            'status' => $status,
        ];
        $resolver = $this->resolver();
        if (method_exists($resolver, 'resolveBi')) {
            $decision = $resolver->resolveBi($clientItem, $serverItem);
        } else {
            $decision = $this->resolveGenericLikeQuality($resolver, $clientItem, $serverItem, $status);
        }
        if (($decision['action'] ?? '') === 'reject_client') {
            throw new \RuntimeException((string) ($decision['reason'] ?? 'status_changed'));
        }
    }

    /**
     * Fallback until OfflineConflictResolverService::resolveBi is wired.
     *
     * @param array<string, mixed> $clientItem
     * @param array<string, mixed> $serverItem
     * @return array<string, mixed>
     */
    private function resolveGenericLikeQuality(
        OfflineConflictResolverService $resolver,
        array $clientItem,
        array $serverItem,
        string $serverStatus
    ): array {
        $base = $resolver->resolve($clientItem, $serverItem);
        if (($base['action'] ?? '') === 'reject_client') {
            return $base;
        }
        if (in_array($serverStatus, ['published', 'archived'], true)
            && isset($clientItem['expected_status'])
            && (string) $clientItem['expected_status'] !== $serverStatus) {
            return [
                'action' => 'reject_client',
                'item' => $serverItem,
                'reason' => 'status_changed',
            ];
        }
        $expectedStatus = $clientItem['expected_status'] ?? null;
        if ($expectedStatus !== null && ($serverStatus !== '' || array_key_exists('status', $serverItem))) {
            $compare = $serverStatus !== '' ? $serverStatus : (string) ($serverItem['status'] ?? '');
            if ($compare !== (string) $expectedStatus) {
                return [
                    'action' => 'reject_client',
                    'item' => $serverItem,
                    'reason' => 'status_changed',
                ];
            }
        }

        return $base;
    }

    private function normalizeAction(string $action): string
    {
        $action = trim($action);
        if (str_starts_with($action, self::PREFIX)) {
            $action = substr($action, strlen(self::PREFIX));
        }

        return $action;
    }

    /**
     * @param array<string, mixed> $queueRow
     * @return array<string, mixed>
     */
    private function decodePayload(array $queueRow): array
    {
        $payload = $queueRow['payload'] ?? null;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($payload) ? $payload : [];
    }

    private function isConflictError(string $message): bool
    {
        return in_array($message, [
            'status_changed',
            'server_newer',
            'version_conflict',
        ], true);
    }
}
