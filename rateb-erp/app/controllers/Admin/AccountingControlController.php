<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

require_once dirname(__DIR__, 2) . '/bootstrap/accounting-control-bridge.php';

use App\Accounting\Admin\AccountingControlBootstrap;
use App\Accounting\Admin\Services\AccountingControlService;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;

/**
 * Phase 6 — Enterprise Accounting Control Center (UI only).
 */
final class AccountingControlController extends Controller
{
    private AccountingControlService $service;

    public function __construct()
    {
        AccountingControlBootstrap::init();
        $this->service = new AccountingControlService();
    }

    public function dashboard(): void
    {
        $this->renderPage('dashboard', __('accounting_control_dashboard'), 'accounting.dashboard');
    }

    public function events(): void
    {
        $this->renderPage('events', __('accounting_control_events'), 'accounting.events');
    }

    public function replay(): void
    {
        $this->renderPage('replay', __('accounting_control_replay'), 'accounting.replay');
    }

    public function audit(): void
    {
        $this->renderPage('audit', __('accounting_control_audit'), 'accounting.audit');
    }

    public function projections(): void
    {
        $this->renderPage('projections', __('accounting_control_projections'), 'accounting.projections');
    }

    public function consolidation(): void
    {
        $this->renderPage('consolidation', __('accounting_control_consolidation'), 'accounting.consolidation');
    }

    public function drift(): void
    {
        $this->renderPage('drift', __('accounting_control_drift'), 'accounting.drift');
    }

    public function reconciliation(): void
    {
        $this->renderPage('reconciliation', __('accounting_control_reconciliation'), 'accounting.reconciliation');
    }

    public function integrity(): void
    {
        $this->renderPage('integrity', __('accounting_control_integrity'), 'accounting.integrity');
    }

    public function settings(): void
    {
        $this->renderPage('settings', __('accounting_control_settings'), 'accounting.dashboard');
    }

    public function health(): void
    {
        $this->renderPage('health', __('accounting_control_health'), 'accounting.system_health');
    }

    /**
     * JSON API proxy — same session as ERP (avoids cross-app auth issues).
     */
    public function api(array $params): void
    {
        $this->assertPermission($this->apiPermissionForResource((string) ($params['resource'] ?? '')));

        if (!$this->validateCsrfForApi()) {
            Response::json(['ok' => false, 'message' => 'Invalid CSRF token'], 403);
        }

        $resource = (string) ($params['resource'] ?? '');
        $filters = array_merge($_GET, $this->jsonBody());
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        try {
            if ($resource === 'events' && isset($filters['export']) && (string) $filters['export'] === 'csv') {
                $this->streamEventsCsv($filters);

                return;
            }

            $data = match ($resource) {
                'dashboard' => $this->service->dashboardSummary(isset($filters['company_id']) ? (int) $filters['company_id'] : null),
                'events' => $this->handleEventsApi($filters, $method),
                'replay' => $this->handleReplayApi($filters, $method),
                'audit' => ['logs' => $this->service->listAuditLogs($filters), 'evidence_packs' => $this->service->listEvidencePacks($filters)],
                'projections' => $this->handleProjectionsApi($filters, $method),
                'consolidation' => $this->handleConsolidationApi($filters, $method),
                'drift' => $method === 'POST'
                    ? $this->service->detectDrift($filters)
                    : ['reports' => $this->service->listDriftReports($filters)],
                'reconciliation' => $this->handleReconciliationApi($filters, $method),
                'integrity' => ['overview' => $this->service->integrityOverview($filters), 'evidence_packs' => $this->service->listEvidencePacks($filters)],
                'health' => $this->service->systemHealth(),
                'settings' => $this->service->settings(),
                default => throw new \InvalidArgumentException('Unknown resource'),
            };
            Response::json(['ok' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage()], 400);
        }
    }

    private function renderPage(string $section, string $title, string $permission): void
    {
        $this->assertPermission($permission);
        $companyId = function_exists('rateb_resolve_ops_company_id') ? rateb_resolve_ops_company_id() : 0;

        $this->view('admin/accounting-control/layout', [
            'title' => $title,
            'accSection' => $section,
            'csrf' => Csrf::token(),
            'companyId' => $companyId,
            'apiBase' => rateb_app_url('accounting-control/api'),
            'accNav' => $this->navItems(),
        ], 'main');
    }

    /**
     * @return list<array{slug:string,label:string,route:string,icon:string,permission:string}>
     */
    private function navItems(): array
    {
        return [
            ['slug' => 'dashboard', 'label' => __('accounting_control_dashboard'), 'route' => 'accounting-control', 'icon' => 'fa-gauge-high', 'permission' => 'accounting.dashboard'],
            ['slug' => 'events', 'label' => __('accounting_control_events'), 'route' => 'accounting-control/events', 'icon' => 'fa-database', 'permission' => 'accounting.events'],
            ['slug' => 'replay', 'label' => __('accounting_control_replay'), 'route' => 'accounting-control/replay', 'icon' => 'fa-rotate', 'permission' => 'accounting.replay'],
            ['slug' => 'audit', 'label' => __('accounting_control_audit'), 'route' => 'accounting-control/audit', 'icon' => 'fa-shield-halved', 'permission' => 'accounting.audit'],
            ['slug' => 'projections', 'label' => __('accounting_control_projections'), 'route' => 'accounting-control/projections', 'icon' => 'fa-chart-line', 'permission' => 'accounting.projections'],
            ['slug' => 'consolidation', 'label' => __('accounting_control_consolidation'), 'route' => 'accounting-control/consolidation', 'icon' => 'fa-building-columns', 'permission' => 'accounting.consolidation'],
            ['slug' => 'drift', 'label' => __('accounting_control_drift'), 'route' => 'accounting-control/drift', 'icon' => 'fa-triangle-exclamation', 'permission' => 'accounting.drift'],
            ['slug' => 'reconciliation', 'label' => __('accounting_control_reconciliation'), 'route' => 'accounting-control/reconciliation', 'icon' => 'fa-scale-balanced', 'permission' => 'accounting.reconciliation'],
            ['slug' => 'integrity', 'label' => __('accounting_control_integrity'), 'route' => 'accounting-control/integrity', 'icon' => 'fa-certificate', 'permission' => 'accounting.integrity'],
            ['slug' => 'settings', 'label' => __('accounting_control_settings'), 'route' => 'accounting-control/settings', 'icon' => 'fa-gear', 'permission' => 'accounting.dashboard'],
            ['slug' => 'health', 'label' => __('accounting_control_health'), 'route' => 'accounting-control/health', 'icon' => 'fa-heart-pulse', 'permission' => 'accounting.system_health'],
        ];
    }

    private function assertPermission(string $permission): void
    {
        if (rateb_is_super_admin()) {
            return;
        }
        if (!rateb_can($permission)) {
            http_response_code(403);
            echo '403 Forbidden';
            exit;
        }
    }

    private function apiPermissionForResource(string $resource): string
    {
        return match ($resource) {
            'events' => 'accounting.events',
            'replay' => 'accounting.replay',
            'audit' => 'accounting.audit',
            'projections' => 'accounting.projections',
            'consolidation' => 'accounting.consolidation',
            'drift' => 'accounting.drift',
            'reconciliation' => 'accounting.reconciliation',
            'integrity' => 'accounting.integrity',
            'health' => 'accounting.system_health',
            default => 'accounting.dashboard',
        };
    }

    private function validateCsrfForApi(): bool
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            return true;
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? ($this->jsonBody()['_csrf'] ?? '');

        return Csrf::validate(is_string($token) ? $token : '');
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function handleEventsApi(array $filters, string $method): array
    {
        return $this->service->listEvents($filters);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function handleReplayApi(array $filters, string $method): array
    {
        $dryRun = !empty($filters['dry_run']);
        if ($method === 'GET') {
            return $this->service->replay($filters, true);
        }
        if (empty($filters['confirm'])) {
            throw new \RuntimeException('Confirmation required');
        }

        return $this->service->replay($filters, $dryRun);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function handleProjectionsApi(array $filters, string $method): array
    {
        $type = (string) ($filters['type'] ?? 'trial_balance');
        $tables = [
            'trial_balance' => 'accounting_trial_balance_snapshots',
            'balance_sheet' => 'accounting_balance_sheet_snapshots',
            'profit_loss' => 'accounting_profit_loss_snapshots',
            'cashflow' => 'accounting_cashflow_snapshots',
        ];
        if ($method === 'POST' && ($filters['action'] ?? '') === 'rebuild') {
            if (empty($filters['confirm'])) {
                throw new \RuntimeException('Confirmation required');
            }

            return $this->service->rebuildSnapshots($filters);
        }

        return $this->service->listProjections($tables[$type] ?? $tables['trial_balance'], $filters);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function handleConsolidationApi(array $filters, string $method): array
    {
        $type = (string) ($filters['type'] ?? 'trial_balance');
        $tables = [
            'trial_balance' => 'accounting_consolidated_trial_balance',
            'balance_sheet' => 'accounting_consolidated_balance_sheet',
            'profit_loss' => 'accounting_consolidated_profit_loss',
        ];
        if ($method === 'POST') {
            if (empty($filters['confirm'])) {
                throw new \RuntimeException('Confirmation required');
            }

            return $this->service->runConsolidation($filters);
        }

        return $this->service->listConsolidated($tables[$type] ?? $tables['trial_balance'], $filters);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function handleReconciliationApi(array $filters, string $method): array
    {
        if ($method === 'POST') {
            if (($filters['action'] ?? '') === 'execute') {
                if (empty($filters['confirm'])) {
                    throw new \RuntimeException('Confirmation required');
                }
                $proposal = is_array($filters['proposal'] ?? null) ? $filters['proposal'] : [];

                return $this->service->executeCorrection($proposal, [
                    'dry_run' => !empty($filters['dry_run']),
                    'approved' => !empty($filters['approved']),
                ]);
            }

            return $this->service->reconcile($filters);
        }

        return $this->service->listReconciliationReports($filters);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function streamEventsCsv(array $filters): void
    {
        $this->assertPermission('accounting.events');
        $data = $this->service->listEvents(array_merge($filters, ['per_page' => 5000]));
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="accounting-events.csv"');
        $out = fopen('php://output', 'w');
        if ($out === false) {
            throw new \RuntimeException('Unable to open CSV stream');
        }
        fputcsv($out, ['event_uuid', 'source_system', 'event_type', 'status', 'company_id', 'branch_id', 'created_at']);
        foreach ($data['rows'] as $row) {
            fputcsv($out, [
                $row['event_uuid'],
                $row['source_system'],
                $row['event_type'],
                $row['status'],
                $row['company_id'],
                $row['branch_id'],
                $row['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }
}
