<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Controllers\Api;

use Ratib\ContactCenter\App\Application\Services\ReportService;
use Ratib\ContactCenter\App\Application\Services\RealtimeOrchestrator;
use Ratib\ContactCenter\App\Application\Services\Supervisor\SupervisorAlertService;
use Ratib\ContactCenter\App\Application\Services\Supervisor\SupervisorDashboardService;
use Ratib\ContactCenter\App\Application\Services\Supervisor\SupervisorMonitorService;
use Ratib\ContactCenter\App\Application\Services\Supervisor\SupervisorSlaService;
use Ratib\ContactCenter\App\Application\Services\Supervisor\SupervisorWfmService;
use Ratib\ContactCenter\App\Core\Security\AuthContext;
use Ratib\ContactCenter\App\Core\TenantContext;

final class SupervisorApiController
{
    public function __construct(
        private readonly SupervisorDashboardService $dashboard = new SupervisorDashboardService(),
        private readonly SupervisorMonitorService $monitor = new SupervisorMonitorService(),
        private readonly SupervisorSlaService $sla = new SupervisorSlaService(),
        private readonly SupervisorWfmService $wfm = new SupervisorWfmService(),
        private readonly SupervisorAlertService $alerts = new SupervisorAlertService(),
        private readonly ReportService $reports = new ReportService()
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
        $this->requireSupervisorAccess();
        $tenantId = $this->resolveTenantId($input);
        $userId = AuthContext::userId();
        TenantContext::set($tenantId);

        return match ($action) {
            'dashboard_summary' => $this->requirePerm('rcc.supervisor.view') ?: $this->ok($this->dashboard->summary($tenantId)),

            'wallboard' => $this->requirePerm('rcc.supervisor.wallboard') ?: $this->ok($this->monitor->wallboard($tenantId)),
            'queue_monitor' => $this->requirePerm('rcc.supervisor.queues') ?: $this->ok($this->monitor->queueMonitor($tenantId)),
            'agent_monitor' => $this->requirePerm('rcc.supervisor.agents') ?: $this->ok($this->monitor->agentMonitor($tenantId)),

            'sla_dashboard' => $this->requirePerm('rcc.supervisor.sla') ?: $this->ok($this->sla->dashboard(
                $tenantId,
                isset($input['from']) ? (string) $input['from'] : null,
                isset($input['to']) ? (string) $input['to'] : null
            )),

            'wfm_overview' => $this->requirePerm('rcc.supervisor.wfm') ?: $this->ok($this->wfm->overview($tenantId)),
            'shift_list' => $this->requirePerm('rcc.supervisor.shifts') ?: $this->ok(['shifts' => $this->wfm->listShifts($tenantId)]),
            'shift_save' => $this->requirePerm('rcc.supervisor.shifts') ?: $this->ok(['shift' => $this->wfm->saveShift($tenantId, $input, $userId)]),
            'shift_assignments' => $this->requirePerm('rcc.supervisor.shifts') ?: $this->ok([
                'assignments' => $this->wfm->listAssignments(
                    $tenantId,
                    (string) ($input['from'] ?? gmdate('Y-m-d')),
                    (string) ($input['to'] ?? gmdate('Y-m-d', strtotime('+7 days')))
                ),
            ]),
            'shift_assign' => $this->runPerm('rcc.supervisor.shifts', function () use ($tenantId, $input, $userId) {
                $this->wfm->assignShift(
                    $tenantId,
                    (int) ($input['shift_id'] ?? 0),
                    (int) ($input['agent_id'] ?? 0),
                    (string) ($input['work_date'] ?? gmdate('Y-m-d')),
                    $userId
                );
                return $this->ok(['assigned' => true]);
            }),

            'attendance_list' => $this->requirePerm('rcc.supervisor.attendance') ?: $this->ok([
                'records' => $this->wfm->attendanceForDate($tenantId, (string) ($input['work_date'] ?? gmdate('Y-m-d'))),
            ]),
            'attendance_clock_in' => $this->runPerm('rcc.supervisor.attendance', fn () => $this->ok($this->wfm->clockIn(
                $tenantId,
                (int) ($input['agent_id'] ?? AuthContext::agentIdOrZero()),
                isset($input['shift_id']) ? (int) $input['shift_id'] : null,
                $userId
            ))),
            'attendance_clock_out' => $this->runPerm('rcc.supervisor.attendance', fn () => $this->ok($this->wfm->clockOut(
                $tenantId,
                (int) ($input['agent_id'] ?? AuthContext::agentIdOrZero()),
                $userId
            ))),

            'break_list' => $this->requirePerm('rcc.supervisor.breaks') ?: $this->ok(['breaks' => $this->wfm->activeBreaks($tenantId)]),
            'break_start' => $this->runPerm('rcc.supervisor.breaks', fn () => $this->ok($this->wfm->startBreak(
                $tenantId,
                (int) ($input['agent_id'] ?? AuthContext::agentIdOrZero()),
                (string) ($input['break_type'] ?? 'other'),
                isset($input['reason']) ? (string) $input['reason'] : null,
                $userId
            ))),
            'break_end' => $this->runPerm('rcc.supervisor.breaks', fn () => $this->ok([
                'break' => $this->wfm->endBreak(
                    $tenantId,
                    (int) ($input['agent_id'] ?? AuthContext::agentIdOrZero()),
                    $userId
                ),
            ])),

            'occupancy' => $this->requirePerm('rcc.supervisor.wfm') ?: $this->ok($this->wfm->occupancy($tenantId)),
            'adherence' => $this->requirePerm('rcc.supervisor.wfm') ?: $this->ok($this->wfm->adherence(
                $tenantId,
                (string) ($input['work_date'] ?? gmdate('Y-m-d'))
            )),

            'alert_list' => $this->requirePerm('rcc.supervisor.alerts') ?: $this->ok([
                'alerts' => $this->alerts->list($tenantId, !isset($input['all']) || (string) $input['all'] !== '1'),
            ]),
            'alert_acknowledge' => $this->runPerm('rcc.supervisor.alerts', function () use ($tenantId, $input, $userId) {
                $ok = $this->alerts->acknowledge($tenantId, (int) ($input['alert_id'] ?? 0), $userId);
                return $this->ok(['acknowledged' => $ok]);
            }),
            'alert_rules_list' => $this->requirePerm('rcc.supervisor.alerts') ?: $this->ok(['rules' => $this->alerts->listRules($tenantId)]),
            'alert_rules_save' => $this->runPerm('rcc.supervisor.alerts', function () use ($tenantId, $input, $userId) {
                $this->alerts->saveRule(
                    $tenantId,
                    (string) ($input['rule_key'] ?? ''),
                    !empty($input['is_enabled']),
                    is_array($input['config'] ?? null) ? $input['config'] : [],
                    $userId
                );
                return $this->ok(['saved' => true]);
            }),

            'report' => $this->requirePerm('rcc.supervisor.reports') ?: $this->reportAction($tenantId, $input),
            'tenants_list' => $this->requirePerm('rcc.tenants.manage') ?: $this->ok(['tenants' => $this->listTenants()]),

            default => ['ok' => false, 'error' => 'Unknown action: ' . $action],
        };
    }

    private function requireSupervisorAccess(): void
    {
        if (AuthContext::can('rcc.supervisor.view') || AuthContext::can('rcc.supervisor.dashboard')) {
            return;
        }
        AuthContext::requirePermission('rcc.supervisor.view');
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function reportAction(int $tenantId, array $input): array
    {
        $type = (string) ($input['type'] ?? 'agents');
        $from = (string) ($input['from'] ?? gmdate('Y-m-d 00:00:00'));
        $to = (string) ($input['to'] ?? gmdate('Y-m-d 23:59:59'));
        $rows = match ($type) {
            'queues' => $this->reports->queuePerformance($tenantId, $from, $to),
            'sla' => $this->reports->slaReport($tenantId, $from, $to),
            'calls' => $this->reports->callReport($tenantId, $from, $to),
            'conversations' => $this->reports->conversationReport($tenantId, $from, $to),
            'ai' => $this->reports->aiReport($tenantId, $from, $to),
            default => $this->reports->agentPerformance($tenantId, $from, $to),
        };
        return $this->ok(['type' => $type, 'rows' => $rows, 'count' => count($rows)]);
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

    /** @return array<string, mixed>|null */
    private function requirePerm(string $perm): ?array
    {
        if (!AuthContext::can($perm) && !AuthContext::can('rcc.supervisor.dashboard')) {
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
