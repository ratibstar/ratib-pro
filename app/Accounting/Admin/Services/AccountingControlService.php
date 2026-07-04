<?php
declare(strict_types=1);

namespace App\Accounting\Admin\Services;

use App\Accounting\Admin\Support\AccountingControlDbTrait;
use App\Accounting\Audit\AccountingAuditService;
use App\Accounting\Consolidation\AccountingConsolidationEngine;
use App\Accounting\Drift\AccountingDriftDetector;
use App\Accounting\EventStore\AccountingEvent;
use App\Accounting\EventStore\AccountingEventRepository;
use App\Accounting\Integrity\AccountingAuditCertificationEngine;
use App\Accounting\Integrity\AccountingCorrectionExecutor;
use App\Accounting\Integrity\AccountingGoldenLedgerResolver;
use App\Accounting\Integrity\AccountingLedgerLockManager;
use App\Accounting\Integrity\AccountingReconciliationEngine;
use App\Accounting\Integrity\IntegrityRepository;
use App\Accounting\Projections\AccountingProjectionEngine;
use App\Accounting\Projections\AccountingSnapshotRebuilder;
use App\Accounting\Projections\ProjectionRepository;
use App\Accounting\Replay\AccountingReplayEngine;
use App\Accounting\Support\AccountingConfig;
use App\Accounting\Support\AccountingGatewayBootstrap;

/**
 * Phase 6 read/query facade — delegates to existing engines; no duplicated business logic.
 */
final class AccountingControlService
{
    use AccountingControlDbTrait;

    public function __construct(
        private readonly AccountingEventRepository $events = new AccountingEventRepository(),
        private readonly ProjectionRepository $projections = new ProjectionRepository(),
        private readonly IntegrityRepository $integrity = new IntegrityRepository(),
        private readonly AccountingDriftDetector $drift = new AccountingDriftDetector(),
        private readonly AccountingReconciliationEngine $reconciliation = new AccountingReconciliationEngine(),
        private readonly AccountingReplayEngine $replayEngine = new AccountingReplayEngine(),
        private readonly AccountingCorrectionExecutor $corrections = new AccountingCorrectionExecutor(),
        private readonly AccountingSnapshotRebuilder $rebuilder = new AccountingSnapshotRebuilder(),
        private readonly AccountingConsolidationEngine $consolidation = new AccountingConsolidationEngine(),
        private readonly AccountingGoldenLedgerResolver $golden = new AccountingGoldenLedgerResolver(),
        private readonly AccountingAuditCertificationEngine $certification = new AccountingAuditCertificationEngine(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardSummary(?int $companyId = null): array
    {
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');

        $eventsToday = $this->scalarCount(
            'accounting_events',
            'created_at >= :d',
            ['d' => $today . ' 00:00:00'],
            $companyId
        );
        $eventsFailed = $this->scalarCount(
            'accounting_events',
            "status = 'failed'",
            [],
            $companyId
        );
        $replayPending = $this->scalarCount(
            'accounting_events',
            "status IN ('pending','failed')",
            [],
            $companyId
        );
        $replaySuccess = $this->scalarCount(
            'accounting_audit_logs',
            "action = 'replay_complete' AND status = 'processed'",
            [],
            null
        );

        $driftCount = $this->scalarCount('accounting_drift_reports', '1=1', [], $companyId);
        $reconCount = $this->scalarCount('accounting_reconciliation_reports', '1=1', [], $companyId);
        $auditPacks = $this->scalarCount('accounting_audit_evidence_packs', '1=1', [], $companyId);
        $lockedPeriods = $this->scalarCount('accounting_period_closures', "status IN ('soft_closed','hard_closed')", [], $companyId);

        $tbStatus = $this->tableExists('accounting_trial_balance_snapshots') ? 'ready' : 'missing';
        $projectionStatus = AccountingConfig::projectionsEnabled() ? 'enabled' : 'disabled';

        return [
            'cards' => [
                'events_today' => $eventsToday,
                'events_failed' => $eventsFailed,
                'replay_pending' => $replayPending,
                'replay_success' => $replaySuccess,
                'trial_balance_status' => $tbStatus,
                'projection_status' => $projectionStatus,
                'drift_count' => $driftCount,
                'consolidation_status' => AccountingConfig::consolidationEnabled() ? 'enabled' : 'disabled',
                'reconciliation_status' => AccountingConfig::integrityEnabled() ? 'enabled' : 'disabled',
                'locked_periods' => $lockedPeriods,
                'audit_packs' => $auditPacks,
                'last_replay' => $this->lastAuditAction('replay_complete'),
                'last_snapshot' => $this->lastTableTimestamp('accounting_trial_balance_snapshots', $companyId),
                'last_consolidation' => $this->lastTableTimestamp('accounting_consolidated_trial_balance', $companyId),
            ],
            'charts' => [
                'daily_events' => $this->dailyEventCounts(14, $companyId),
                'monthly_posting' => $this->monthlyPostingCounts(6, $companyId),
                'branch_activity' => $this->groupCount('accounting_events', 'branch_id', $companyId),
                'company_activity' => $this->groupCount('accounting_events', 'company_id', $companyId),
                'replay_success_rate' => $this->replaySuccessRate(),
                'drift_trend' => $this->driftTrend(30, $companyId),
            ],
            'period' => ['from' => $monthStart, 'to' => $monthEnd],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function listEvents(array $filters): array
    {
        if (!$this->events->tableExists()) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => 50];
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 50)));
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }
        if (isset($filters['branch_id']) && $filters['branch_id'] !== '') {
            $where[] = 'branch_id = :branch_id';
            $params['branch_id'] = (int) $filters['branch_id'];
        }
        if (!empty($filters['source_system'])) {
            $where[] = 'source_system = :source_system';
            $params['source_system'] = (string) $filters['source_system'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (!empty($filters['event_uuid'])) {
            $where[] = 'event_uuid LIKE :uuid';
            $params['uuid'] = '%' . (string) $filters['event_uuid'] . '%';
        }
        if (!empty($filters['from_date'])) {
            $where[] = 'created_at >= :from_date';
            $params['from_date'] = (string) $filters['from_date'] . ' 00:00:00';
        }
        if (!empty($filters['to_date'])) {
            $where[] = 'created_at <= :to_date';
            $params['to_date'] = (string) $filters['to_date'] . ' 23:59:59';
        }

        $w = implode(' AND ', $where);
        $result = $this->paginate(
            "SELECT id, event_uuid, source_system, event_type, status, company_id, branch_id, payload, created_at, processed_at FROM accounting_events WHERE {$w} ORDER BY id DESC",
            "SELECT COUNT(*) FROM accounting_events WHERE {$w}",
            $params,
            $page,
            $perPage
        );

        $rows = [];
        foreach ($result['rows'] as $row) {
            $ev = AccountingEvent::fromRow($row);
            $rows[] = [
                'id' => $ev->id,
                'event_uuid' => $ev->eventUuid,
                'source_system' => $ev->sourceSystem,
                'event_type' => $ev->eventType,
                'status' => $ev->status,
                'company_id' => $ev->companyId,
                'branch_id' => $ev->branchId,
                'payload' => $ev->payload,
                'created_at' => $ev->createdAt,
                'processed_at' => $ev->processedAt,
            ];
        }

        return ['rows' => $rows, 'total' => $result['total'], 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function replay(array $filters, bool $dryRun = false): array
    {
        if ($dryRun) {
            $preview = $this->events->findByFilters(array_merge($filters, ['limit' => 5000]));

            return [
                'dry_run' => true,
                'count' => count($preview),
                'event_uuids' => array_map(static fn ($e) => $e->eventUuid, $preview),
            ];
        }

        $result = $this->replayEngine->replay($filters);

        return array_merge($result->toArray(), ['dry_run' => false]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function listAuditLogs(array $filters): array
    {
        if (!$this->tableExists('accounting_audit_logs')) {
            return ['rows' => [], 'total' => 0];
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 50)));
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['action'])) {
            $where[] = 'action = :action';
            $params['action'] = (string) $filters['action'];
        }
        if (!empty($filters['system'])) {
            $where[] = 'system = :system';
            $params['system'] = (string) $filters['system'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (!empty($filters['event_uuid'])) {
            $where[] = 'event_uuid LIKE :uuid';
            $params['uuid'] = '%' . (string) $filters['event_uuid'] . '%';
        }
        if (!empty($filters['from_date'])) {
            $where[] = 'created_at >= :from_date';
            $params['from_date'] = (string) $filters['from_date'] . ' 00:00:00';
        }
        if (!empty($filters['to_date'])) {
            $where[] = 'created_at <= :to_date';
            $params['to_date'] = (string) $filters['to_date'] . ' 23:59:59';
        }

        $w = implode(' AND ', $where);
        $result = $this->paginate(
            "SELECT id, event_uuid, action, system, status, metadata, created_at FROM accounting_audit_logs WHERE {$w} ORDER BY id DESC",
            "SELECT COUNT(*) FROM accounting_audit_logs WHERE {$w}",
            $params,
            $page,
            $perPage
        );

        foreach ($result['rows'] as &$row) {
            $meta = json_decode((string) ($row['metadata'] ?? '{}'), true);
            $row['metadata'] = is_array($meta) ? $meta : [];
        }
        unset($row);

        return ['rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function listProjections(string $table, array $params): array
    {
        $companyId = (int) ($params['company_id'] ?? 0);
        $branchId = isset($params['branch_id']) ? (int) $params['branch_id'] : null;
        $periodFrom = (string) ($params['period_from'] ?? date('Y-m-01'));
        $periodTo = (string) ($params['period_to'] ?? date('Y-m-d'));

        $rows = $this->projections->fetchSnapshotPayloads($table, $companyId, $periodFrom, $periodTo, $branchId);
        $closure = $this->integrity->fetchPeriodClosure($companyId, $periodFrom, $periodTo, $branchId);

        return [
            'table' => $table,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
            'rows' => $rows,
            'row_count' => count($rows),
            'period_closure' => $closure,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function rebuildSnapshots(array $params): array
    {
        return $this->rebuilder->rebuild(
            (int) ($params['company_id'] ?? 0),
            (string) ($params['period_from'] ?? date('Y-m-01')),
            (string) ($params['period_to'] ?? date('Y-m-d')),
            isset($params['branch_id']) ? (int) $params['branch_id'] : null
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function runConsolidation(array $params): array
    {
        return $this->consolidation->runConsolidation($params);
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function listConsolidated(string $table, array $filters): array
    {
        if (!$this->tableExists($table)) {
            return ['rows' => [], 'total' => 0];
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 50)));
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }
        if (!empty($filters['period_from'])) {
            $where[] = 'period_from = :period_from';
            $params['period_from'] = (string) $filters['period_from'];
        }
        if (!empty($filters['period_to'])) {
            $where[] = 'period_to = :period_to';
            $params['period_to'] = (string) $filters['period_to'];
        }
        if (!empty($filters['consolidation_run_id'])) {
            $where[] = 'consolidation_run_id = :run';
            $params['run'] = (string) $filters['consolidation_run_id'];
        }

        $w = implode(' AND ', $where);
        $result = $this->paginate(
            "SELECT id, company_id, branch_id, period_from, period_to, consolidation_run_id, payload, created_at FROM {$table} WHERE {$w} ORDER BY id DESC",
            "SELECT COUNT(*) FROM {$table} WHERE {$w}",
            $params,
            $page,
            $perPage
        );

        foreach ($result['rows'] as &$row) {
            $payload = json_decode((string) ($row['payload'] ?? '{}'), true);
            $row['payload'] = is_array($payload) ? $payload : [];
        }
        unset($row);

        return ['rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function detectDrift(array $params): array
    {
        $report = $this->drift->detectDrift($params);

        return $report->toArray();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function listDriftReports(array $filters): array
    {
        if (!$this->tableExists('accounting_drift_reports')) {
            return ['rows' => [], 'total' => 0];
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 50)));
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }
        if (!empty($filters['from_date'])) {
            $where[] = 'created_at >= :from_date';
            $params['from_date'] = (string) $filters['from_date'] . ' 00:00:00';
        }

        $w = implode(' AND ', $where);
        $result = $this->paginate(
            "SELECT id, company_id, branch_id, period_from, period_to, payload, created_at FROM accounting_drift_reports WHERE {$w} ORDER BY id DESC",
            "SELECT COUNT(*) FROM accounting_drift_reports WHERE {$w}",
            $params,
            $page,
            $perPage
        );

        foreach ($result['rows'] as &$row) {
            $payload = json_decode((string) ($row['payload'] ?? '{}'), true);
            $row['payload'] = is_array($payload) ? $payload : [];
            $row['severity'] = $this->driftSeverityFromPayload($payload);
        }
        unset($row);

        return ['rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * @param array<string, mixed> $params
     */
    public function reconcile(array $params): array
    {
        $drift = $this->drift->detectDrift($params);
        $report = $this->reconciliation->reconcileFromDrift($drift, $params);

        return $report->toArray();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function listReconciliationReports(array $filters): array
    {
        if (!$this->tableExists('accounting_reconciliation_reports')) {
            return ['rows' => [], 'total' => 0];
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 50)));
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }
        if (!empty($filters['risk_level'])) {
            $where[] = 'risk_level = :risk';
            $params['risk'] = (string) $filters['risk_level'];
        }

        $w = implode(' AND ', $where);
        $result = $this->paginate(
            "SELECT id, company_id, branch_id, period_from, period_to, drift_report_id, risk_level, payload, created_at FROM accounting_reconciliation_reports WHERE {$w} ORDER BY id DESC",
            "SELECT COUNT(*) FROM accounting_reconciliation_reports WHERE {$w}",
            $params,
            $page,
            $perPage
        );

        foreach ($result['rows'] as &$row) {
            $payload = json_decode((string) ($row['payload'] ?? '{}'), true);
            $row['payload'] = is_array($payload) ? $payload : [];
        }
        unset($row);

        return ['rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * @param array<string, mixed> $proposal
     * @param array<string, mixed> $options
     */
    public function executeCorrection(array $proposal, array $options): array
    {
        return $this->corrections->execute($proposal, $options);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function integrityOverview(array $params): array
    {
        $companyId = (int) ($params['company_id'] ?? 0);
        $periodFrom = (string) ($params['period_from'] ?? date('Y-m-01'));
        $periodTo = (string) ($params['period_to'] ?? date('Y-m-d'));
        $branchId = isset($params['branch_id']) ? (int) $params['branch_id'] : null;

        $golden = $this->golden->resolve($params);
        $conflicts = $this->golden->detectConflicts($params);
        $locks = $this->integrity->fetchLockedPeriods($companyId, $branchId);
        $hashes = $this->integrity->computeSnapshotHashes($companyId, $periodFrom, $periodTo, $branchId);
        $lockMgr = new AccountingLedgerLockManager();
        $lockVerdict = $lockMgr->assertMutable($companyId, $periodTo, $branchId, 'create');

        $drift = $this->drift->detectDrift($params);
        $pack = $this->certification->certify($drift, $params);

        $score = 100;
        if ($conflicts->hasConflicts()) {
            $score -= min(40, count($conflicts->conflicts) * 5);
        }
        if ($drift->hasDrift()) {
            $score -= 20;
        }
        if (!$golden->isBalanced()) {
            $score -= 15;
        }
        $score = max(0, $score);

        return [
            'golden_ledger' => $golden->toArray(),
            'conflicts' => $conflicts->toArray(),
            'locked_periods' => $locks,
            'snapshot_hashes' => $hashes,
            'lock_verdict' => $lockVerdict->toArray(),
            'evidence_pack' => $pack->toArray(),
            'integrity_score' => $score,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function listEvidencePacks(array $filters): array
    {
        if (!$this->tableExists('accounting_audit_evidence_packs')) {
            return ['rows' => [], 'total' => 0];
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($filters['per_page'] ?? 50)));
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }

        $w = implode(' AND ', $where);
        $result = $this->paginate(
            "SELECT id, company_id, branch_id, period_from, period_to, certification_hash, payload, created_at FROM accounting_audit_evidence_packs WHERE {$w} ORDER BY id DESC",
            "SELECT COUNT(*) FROM accounting_audit_evidence_packs WHERE {$w}",
            $params,
            $page,
            $perPage
        );

        foreach ($result['rows'] as &$row) {
            $payload = json_decode((string) ($row['payload'] ?? '{}'), true);
            $row['payload'] = is_array($payload) ? $payload : [];
        }
        unset($row);

        return ['rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * @return array<string, mixed>
     */
    public function systemHealth(): array
    {
        AccountingGatewayBootstrap::registerAutoloader();

        return [
            'gateway' => ['enabled' => AccountingGatewayBootstrap::isEnabled()],
            'pipeline' => ['event_store_enabled' => AccountingConfig::eventStoreEnabled()],
            'event_store' => ['table' => $this->events->tableExists()],
            'replay' => ['enabled' => AccountingConfig::replayEnabled()],
            'projection' => [
                'enabled' => AccountingConfig::projectionsEnabled(),
                'snapshots_table' => $this->tableExists('accounting_trial_balance_snapshots'),
            ],
            'consolidation' => ['enabled' => AccountingConfig::consolidationEnabled()],
            'integrity' => ['enabled' => AccountingConfig::integrityEnabled()],
            'drift' => ['enabled' => AccountingConfig::driftDetectionEnabled()],
            'database' => ['connected' => $this->controlPdo() !== null],
            'queue' => ['status' => 'n/a', 'note' => 'No dedicated accounting queue in Phase 6'],
            'migrations' => [
                'event_store' => $this->tableExists('accounting_events'),
                'audit_logs' => $this->tableExists('accounting_audit_logs'),
                'snapshots' => $this->tableExists('accounting_trial_balance_snapshots'),
                'drift_reports' => $this->tableExists('accounting_drift_reports'),
                'reconciliation' => $this->tableExists('accounting_reconciliation_reports'),
                'evidence_packs' => $this->tableExists('accounting_audit_evidence_packs'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [
            'ACCOUNTING_GATEWAY_ENABLED' => AccountingGatewayBootstrap::isEnabled(),
            'ACCOUNTING_EVENT_STORE_ENABLED' => AccountingConfig::eventStoreEnabled(),
            'ACCOUNTING_REPLAY_ENABLED' => AccountingConfig::replayEnabled(),
            'ACCOUNTING_AUDIT_ENABLED' => AccountingConfig::auditEnabled(),
            'ACCOUNTING_PROJECTIONS_ENABLED' => AccountingConfig::projectionsEnabled(),
            'ACCOUNTING_CONSOLIDATION_ENABLED' => AccountingConfig::consolidationEnabled(),
            'ACCOUNTING_DRIFT_DETECTION_ENABLED' => AccountingConfig::driftDetectionEnabled(),
            'ACCOUNTING_INTEGRITY_ENABLED' => AccountingConfig::integrityEnabled(),
            'ACCOUNTING_LEDGER_LOCK_ENFORCEMENT_ENABLED' => AccountingConfig::ledgerLockEnforcementEnabled(),
            'ACCOUNTING_CORRECTION_EXECUTOR_ENABLED' => AccountingConfig::correctionExecutorEnabled(),
            'ACCOUNTING_CORRECTION_AUTO_FIX_ENABLED' => AccountingConfig::correctionAutoFixEnabled(),
            'ACCOUNTING_AUDIT_CERTIFICATION_ENABLED' => AccountingConfig::auditCertificationEnabled(),
        ];
    }

    private function scalarCount(string $table, string $cond, array $params, ?int $companyId): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        $pdo = $this->controlPdo();
        if ($pdo === null) {
            return 0;
        }
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$cond}";
        if ($companyId !== null && $companyId > 0 && $this->columnExists($table, 'company_id')) {
            $sql .= ' AND company_id = :cid';
            $params['cid'] = $companyId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $pdo = $this->controlPdo();
        if ($pdo === null) {
            return false;
        }
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function lastAuditAction(string $action): ?string
    {
        if (!$this->tableExists('accounting_audit_logs')) {
            return null;
        }
        $pdo = $this->controlPdo();
        $stmt = $pdo->query("SELECT created_at FROM accounting_audit_logs WHERE action = " . $pdo->quote($action) . " ORDER BY id DESC LIMIT 1");
        $v = $stmt ? $stmt->fetchColumn() : false;

        return $v !== false ? (string) $v : null;
    }

    private function lastTableTimestamp(string $table, ?int $companyId): ?string
    {
        if (!$this->tableExists($table)) {
            return null;
        }
        $pdo = $this->controlPdo();
        $sql = "SELECT MAX(created_at) FROM {$table}";
        $params = [];
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' WHERE company_id = :cid';
            $params['cid'] = $companyId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $v = $stmt->fetchColumn();

        return $v !== false ? (string) $v : null;
    }

    /**
     * @return list<array{date:string,count:int}>
     */
    private function dailyEventCounts(int $days, ?int $companyId): array
    {
        if (!$this->tableExists('accounting_events')) {
            return [];
        }
        $pdo = $this->controlPdo();
        $sql = "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM accounting_events WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)";
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' AND company_id = :cid';
        }
        $sql .= ' GROUP BY DATE(created_at) ORDER BY d ASC';
        $stmt = $pdo->prepare($sql);
        $params = ['days' => $days];
        if ($companyId !== null && $companyId > 0) {
            $params['cid'] = $companyId;
        }
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $out[] = ['date' => (string) $row['d'], 'count' => (int) $row['c']];
        }

        return $out;
    }

    /**
     * @return list<array{month:string,count:int}>
     */
    private function monthlyPostingCounts(int $months, ?int $companyId): array
    {
        if (!$this->tableExists('accounting_events')) {
            return [];
        }
        $pdo = $this->controlPdo();
        $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS c FROM accounting_events WHERE status = 'processed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)";
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' AND company_id = :cid';
        }
        $sql .= ' GROUP BY m ORDER BY m ASC';
        $stmt = $pdo->prepare($sql);
        $params = ['months' => $months];
        if ($companyId !== null && $companyId > 0) {
            $params['cid'] = $companyId;
        }
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $out[] = ['month' => (string) $row['m'], 'count' => (int) $row['c']];
        }

        return $out;
    }

    /**
     * @return list<array{key:string|null,count:int}>
     */
    private function groupCount(string $table, string $column, ?int $companyId): array
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return [];
        }
        $pdo = $this->controlPdo();
        $sql = "SELECT {$column} AS k, COUNT(*) AS c FROM {$table} WHERE 1=1";
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' AND company_id = :cid';
        }
        $sql .= " GROUP BY {$column} ORDER BY c DESC LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $params = $companyId !== null && $companyId > 0 ? ['cid' => $companyId] : [];
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $out[] = ['key' => $row['k'] !== null ? (string) $row['k'] : null, 'count' => (int) $row['c']];
        }

        return $out;
    }

    /**
     * @return array{processed:int,failed:int,rate:float}
     */
    private function replaySuccessRate(): array
    {
        if (!$this->tableExists('accounting_audit_logs')) {
            return ['processed' => 0, 'failed' => 0, 'rate' => 0.0];
        }
        $pdo = $this->controlPdo();
        $processed = (int) $pdo->query("SELECT COUNT(*) FROM accounting_audit_logs WHERE action = 'replay_complete' AND status = 'processed'")->fetchColumn();
        $failed = (int) $pdo->query("SELECT COUNT(*) FROM accounting_audit_logs WHERE action = 'replay_complete' AND status = 'failed'")->fetchColumn();
        $total = $processed + $failed;

        return [
            'processed' => $processed,
            'failed' => $failed,
            'rate' => $total > 0 ? round($processed / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * @return list<array{date:string,count:int}>
     */
    private function driftTrend(int $days, ?int $companyId): array
    {
        if (!$this->tableExists('accounting_drift_reports')) {
            return [];
        }
        $pdo = $this->controlPdo();
        $sql = "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM accounting_drift_reports WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)";
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' AND company_id = :cid';
        }
        $sql .= ' GROUP BY DATE(created_at) ORDER BY d ASC';
        $stmt = $pdo->prepare($sql);
        $params = ['days' => $days];
        if ($companyId !== null && $companyId > 0) {
            $params['cid'] = $companyId;
        }
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $out[] = ['date' => (string) $row['d'], 'count' => (int) $row['c']];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function driftSeverityFromPayload(array $payload): string
    {
        $summary = $payload['summary'] ?? [];
        $m = (int) ($summary['mismatched'] ?? 0);
        $miss = (int) ($summary['missing'] ?? 0);
        if ($m >= 3 || $miss >= 5) {
            return 'high';
        }
        if ($m >= 1 || $miss >= 1) {
            return 'medium';
        }

        return 'low';
    }
}
