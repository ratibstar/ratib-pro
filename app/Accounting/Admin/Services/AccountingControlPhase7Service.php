<?php
declare(strict_types=1);

namespace App\Accounting\Admin\Services;

use App\Accounting\Admin\Support\AccountingControlDbTrait;
use App\Accounting\Support\AccountingConfig;
use App\Accounting\Support\AccountingGatewayBootstrap;
use App\Accounting\Support\EnterpriseMigrationStatus;

/**
 * Phase 7 — read-only dashboards, search, timeline, notifications, diagnostics.
 * Does not modify accounting engines or posting logic.
 */
final class AccountingControlPhase7Service
{
    use AccountingControlDbTrait;

    public function __construct(
        private readonly AccountingControlService $core = new AccountingControlService(),
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function sectionDashboard(string $section, array $filters): array
    {
        $companyId = isset($filters['company_id']) ? (int) $filters['company_id'] : null;

        return match ($section) {
            'events' => $this->eventsDashboard($filters),
            'replay' => $this->replayDashboard($filters),
            'audit' => $this->auditDashboard($filters),
            'projections' => $this->projectionsDashboard($filters),
            'consolidation' => $this->consolidationDashboard($filters),
            'drift' => $this->driftDashboard($filters),
            'reconciliation' => $this->reconciliationDashboard($filters),
            'integrity' => $this->integrityDashboard($filters),
            'health' => ['cards' => ['health_ok' => $this->healthPassCount()], 'updated_at' => date('c')],
            default => $this->core->dashboardSummary($companyId),
        };
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function projectionsDetail(string $type, array $filters): array
    {
        $tables = [
            'trial_balance' => 'accounting_trial_balance_snapshots',
            'balance_sheet' => 'accounting_balance_sheet_snapshots',
            'profit_loss' => 'accounting_profit_loss_snapshots',
            'cashflow' => 'accounting_cashflow_snapshots',
        ];
        $table = $tables[$type] ?? $tables['trial_balance'];
        $base = $this->core->listProjections($table, $filters);
        $rows = $this->flattenSnapshotRows($base['rows'] ?? [], $type);
        $history = $this->snapshotHistory($table, $filters);
        $compare = $this->snapshotCompare($table, $filters);

        return array_merge($base, [
            'parsed_rows' => $rows,
            'history' => $history,
            'comparison' => $compare,
            'kpis' => [
                'row_count' => count($rows),
                'total_debit' => round(array_sum(array_column($rows, 'debit')), 2),
                'total_credit' => round(array_sum(array_column($rows, 'credit')), 2),
                'accounts' => count(array_unique(array_filter(array_column($rows, 'account_code')))),
            ],
            'updated_at' => $this->lastTableTimestamp($table, (int) ($filters['company_id'] ?? 0) ?: null),
        ]);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function consolidationDetail(string $type, array $filters): array
    {
        $tables = [
            'trial_balance' => 'accounting_consolidated_trial_balance',
            'balance_sheet' => 'accounting_consolidated_balance_sheet',
            'profit_loss' => 'accounting_consolidated_profit_loss',
        ];
        $table = $tables[$type] ?? $tables['trial_balance'];
        $list = $this->core->listConsolidated($table, $filters);
        $parsed = [];
        $eliminations = [];
        $runs = [];

        foreach ($list['rows'] as $row) {
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
            $runId = (string) ($row['consolidation_run_id'] ?? '');
            if ($runId !== '' && !isset($runs[$runId])) {
                $runs[$runId] = [
                    'run_id' => $runId,
                    'company_id' => $row['company_id'],
                    'branch_id' => $row['branch_id'],
                    'period_from' => $row['period_from'],
                    'period_to' => $row['period_to'],
                    'created_at' => $row['created_at'],
                    'row_count' => 0,
                ];
            }
            if ($runId !== '') {
                $runs[$runId]['row_count']++;
            }
            $flat = $this->flattenSnapshotRows([$payload], $type);
            foreach ($flat as $f) {
                $parsed[] = array_merge($f, [
                    'consolidation_run_id' => $runId,
                    'company_id' => $row['company_id'],
                    'branch_id' => $row['branch_id'],
                ]);
            }
            $elim = $payload['eliminated_event_uuids'] ?? null;
            if (is_array($elim)) {
                foreach ($elim as $uuid) {
                    $eliminations[] = ['event_uuid' => $uuid, 'run_id' => $runId];
                }
            } elseif (is_string($elim) && $elim !== '') {
                $eliminations[] = ['event_uuid' => $elim, 'run_id' => $runId];
            }
        }

        $hierarchy = $this->buildHierarchy($list['rows']);

        return [
            'rows' => $list['rows'],
            'parsed_rows' => $parsed,
            'eliminations' => array_slice($eliminations, 0, 500),
            'execution_history' => array_values($runs),
            'hierarchy' => $hierarchy,
            'kpis' => [
                'runs' => count($runs),
                'rows' => count($parsed),
                'eliminations' => count($eliminations),
                'companies' => count(array_unique(array_column($list['rows'], 'company_id'))),
            ],
            'updated_at' => $this->lastTableTimestamp($table, (int) ($filters['company_id'] ?? 0) ?: null),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function driftDetail(array $filters): array
    {
        $reports = $this->core->listDriftReports($filters);
        $rows = $reports['rows'] ?? [];
        $totals = ['missing' => 0, 'duplicate' => 0, 'mismatched' => 0, 'projection' => 0, 'consolidation' => 0];
        $severity = ['high' => 0, 'medium' => 0, 'low' => 0];
        $actions = [];

        foreach ($rows as $row) {
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
            $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
            $totals['missing'] += (int) ($summary['missing'] ?? 0);
            $totals['duplicate'] += (int) ($summary['duplicate'] ?? 0);
            $totals['mismatched'] += (int) ($summary['mismatched'] ?? 0);
            if (!empty($payload['projection_drift'])) {
                $totals['projection']++;
            }
            if (!empty($payload['consolidation_drift'])) {
                $totals['consolidation']++;
            }
            $sev = (string) ($row['severity'] ?? 'low');
            if (isset($severity[$sev])) {
                $severity[$sev]++;
            }
        }

        if ($totals['missing'] > 0) {
            $actions[] = ['action' => 'replay_missing', 'priority' => 'high', 'count' => $totals['missing']];
        }
        if ($totals['duplicate'] > 0) {
            $actions[] = ['action' => 'review_duplicates', 'priority' => 'medium', 'count' => $totals['duplicate']];
        }
        if ($totals['mismatched'] > 0) {
            $actions[] = ['action' => 'run_reconciliation', 'priority' => 'high', 'count' => $totals['mismatched']];
        }
        if ($totals['projection'] > 0) {
            $actions[] = ['action' => 'rebuild_snapshots', 'priority' => 'medium', 'count' => $totals['projection']];
        }

        return [
            'reports' => $reports,
            'breakdown' => $totals,
            'severity_counts' => $severity,
            'trend' => $this->driftTrendChart(30, (int) ($filters['company_id'] ?? 0) ?: null),
            'recommended_actions' => $actions,
            'updated_at' => $this->lastTableTimestamp('accounting_drift_reports', (int) ($filters['company_id'] ?? 0) ?: null),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function reconciliationDetail(array $filters): array
    {
        $reports = $this->core->listReconciliationReports($filters);
        $corrections = $this->listCorrectionLog($filters);
        $workflow = ['pending' => 0, 'suggested' => 0, 'approved' => 0, 'executed' => 0, 'rejected' => 0, 'dry_run' => 0];

        foreach ($corrections as $c) {
            $st = (string) ($c['status'] ?? 'proposed');
            if ($st === 'proposed') {
                $workflow['suggested']++;
            } elseif ($st === 'executed') {
                $workflow['executed']++;
            } elseif ($st === 'dry_run') {
                $workflow['dry_run']++;
            } elseif ($st === 'rejected') {
                $workflow['rejected']++;
            } else {
                $workflow['pending']++;
            }
        }

        foreach ($reports['rows'] as $row) {
            $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
            if (!empty($payload['correction_suggestions']) && $workflow['suggested'] === 0) {
                $workflow['pending'] += count($payload['correction_suggestions']);
            }
        }

        return [
            'reports' => $reports,
            'corrections' => $corrections,
            'workflow' => $workflow,
            'timeline' => $this->correctionTimeline($corrections),
            'updated_at' => $this->lastTableTimestamp('accounting_reconciliation_reports', (int) ($filters['company_id'] ?? 0) ?: null),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function integrityDetail(array $filters): array
    {
        $overview = $this->core->integrityOverview($filters);
        $packs = $this->core->listEvidencePacks($filters);
        $corrections = $this->listCorrectionLog($filters);
        $conflicts = is_array($overview['conflicts'] ?? null) ? $overview['conflicts'] : [];
        $conflictList = is_array($conflicts['conflicts'] ?? null) ? $conflicts['conflicts'] : [];

        return [
            'overview' => $overview,
            'evidence_packs' => $packs,
            'correction_history' => $corrections,
            'conflict_timeline' => array_map(static fn (array $c): array => [
                'type' => $c['type'] ?? 'conflict',
                'detail' => $c['detail'] ?? $c['message'] ?? '',
                'account_code' => $c['account_code'] ?? null,
            ], $conflictList),
            'audit_readiness' => [
                'score' => $overview['integrity_score'] ?? 0,
                'locked_periods' => count($overview['locked_periods'] ?? []),
                'evidence_count' => $packs['total'] ?? count($packs['rows'] ?? []),
                'hash_verified' => !empty($overview['snapshot_hashes']),
                'certification' => !empty($overview['evidence_pack']),
            ],
            'updated_at' => date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function replayDetail(array $filters): array
    {
        $queue = $this->replayQueue($filters);
        $history = $this->core->listAuditLogs(array_merge($filters, ['action' => 'replay_complete', 'per_page' => 50]));
        $stats = $this->replayStats();

        return [
            'queue' => $queue,
            'history' => $history,
            'stats' => $stats,
            'updated_at' => $this->lastAuditTimestamp('replay_complete'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function globalSearch(string $query, array $filters): array
    {
        $q = trim($query);
        if ($q === '' || mb_strlen($q) < 2) {
            return ['results' => [], 'total' => 0];
        }

        $like = '%' . $q . '%';
        $results = [];
        $limit = 20;

        if ($this->tableExists('accounting_events')) {
            $events = $this->searchTable(
                'accounting_events',
                "event_uuid LIKE :q OR source_system LIKE :q OR event_type LIKE :q OR status LIKE :q",
                ['q' => $like],
                $filters,
                $limit,
                'event'
            );
            $results = array_merge($results, $events);
        }

        if ($this->tableExists('accounting_audit_logs')) {
            $audit = $this->searchTable(
                'accounting_audit_logs',
                "event_uuid LIKE :q OR action LIKE :q OR system LIKE :q",
                ['q' => $like],
                $filters,
                $limit,
                'audit'
            );
            $results = array_merge($results, $audit);
        }

        if ($this->tableExists('accounting_drift_reports')) {
            $drift = $this->searchTable(
                'accounting_drift_reports',
                "CAST(payload AS CHAR) LIKE :q",
                ['q' => $like],
                $filters,
                $limit,
                'drift'
            );
            $results = array_merge($results, $drift);
        }

        if ($this->tableExists('accounting_audit_evidence_packs')) {
            $ev = $this->searchTable(
                'accounting_audit_evidence_packs',
                "certification_hash LIKE :q OR CAST(payload AS CHAR) LIKE :q",
                ['q' => $like],
                $filters,
                $limit,
                'integrity'
            );
            $results = array_merge($results, $ev);
        }

        usort($results, static fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return ['results' => array_slice($results, 0, 50), 'total' => count($results), 'query' => $q];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function activityTimeline(array $filters): array
    {
        $items = [];
        $limit = (int) ($filters['per_page'] ?? 100);

        if ($this->tableExists('accounting_events')) {
            $pdo = $this->controlPdo();
            if ($pdo !== null) {
                $sql = 'SELECT event_uuid AS ref, source_system, event_type AS detail, status, created_at FROM accounting_events ORDER BY id DESC LIMIT ' . min(50, $limit);
                $stmt = $pdo->query($sql);
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $items[] = [
                        'kind' => 'event',
                        'ref' => $row['ref'],
                        'title' => $row['source_system'] . ' / ' . $row['detail'],
                        'status' => $row['status'],
                        'created_at' => $row['created_at'],
                    ];
                }
            }
        }

        $logs = $this->core->listAuditLogs(array_merge($filters, ['per_page' => min(50, $limit)]));
        foreach ($logs['rows'] ?? [] as $log) {
            $items[] = [
                'kind' => $this->timelineKindFromAction((string) ($log['action'] ?? '')),
                'ref' => $log['event_uuid'] ?? '',
                'title' => (string) ($log['action'] ?? ''),
                'status' => $log['status'] ?? '',
                'created_at' => $log['created_at'] ?? '',
            ];
        }

        usort($items, static fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return ['items' => array_slice($items, 0, $limit), 'updated_at' => date('c')];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function notifications(array $filters): array
    {
        $items = [];
        $actions = [
            'replay_complete' => 'replay_finished',
            'snapshot_rebuilt' => 'projection_finished',
            'consolidation_complete' => 'consolidation_finished',
            'drift_detected' => 'drift_detected',
            'correction_required' => 'correction_required',
            'integrity_failure' => 'integrity_failure',
            'certification_complete' => 'certification_complete',
        ];

        if (!$this->tableExists('accounting_audit_logs')) {
            return ['items' => [], 'unread' => 0];
        }

        $pdo = $this->controlPdo();
        $placeholders = implode(',', array_fill(0, count($actions), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, action, status, event_uuid, metadata, created_at FROM accounting_audit_logs
             WHERE action IN ({$placeholders}) ORDER BY id DESC LIMIT 50"
        );
        $stmt->execute(array_keys($actions));

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $action = (string) ($row['action'] ?? '');
            $items[] = [
                'id' => (int) $row['id'],
                'type' => $actions[$action] ?? $action,
                'action' => $action,
                'status' => $row['status'],
                'ref' => $row['event_uuid'],
                'created_at' => $row['created_at'],
                'read' => false,
            ];
        }

        if ($this->tableExists('accounting_drift_reports')) {
            $drift = $this->core->listDriftReports(array_merge($filters, ['per_page' => 10]));
            foreach ($drift['rows'] ?? [] as $d) {
                if (($d['severity'] ?? '') === 'high') {
                    $items[] = [
                        'id' => 'drift-' . $d['id'],
                        'type' => 'drift_detected',
                        'action' => 'drift_report',
                        'status' => 'high',
                        'ref' => (string) $d['id'],
                        'created_at' => $d['created_at'],
                        'read' => false,
                    ];
                }
            }
        }

        usort($items, static fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return ['items' => array_slice($items, 0, 30), 'unread' => count($items)];
    }

    /**
     * @return array<string, mixed>
     */
    public function runDiagnostics(): array
    {
        $checks = [];
        $root = dirname(__DIR__, 4);
        $erpRoot = $root . '/rateb-erp';

        $checks[] = $this->diag('config.accounting', is_file($root . '/config/accounting.php'), 'Accounting config file');
        $checks[] = $this->diag('config.gateway', AccountingGatewayBootstrap::isEnabled(), 'Gateway enabled', 'WARN');
        $checks[] = $this->diag('feature.event_store', AccountingConfig::eventStoreEnabled(), 'Event store flag');
        $checks[] = $this->diag('feature.projections', AccountingConfig::projectionsEnabled(), 'Projections flag');
        $checks[] = $this->diag('database.connected', $this->controlPdo() !== null, 'Enterprise DB connection');

        $requiredTables = [
            'accounting_events', 'accounting_audit_logs', 'accounting_trial_balance_snapshots',
            'accounting_drift_reports', 'accounting_reconciliation_reports', 'accounting_audit_evidence_packs',
        ];
        foreach ($requiredTables as $table) {
            $checks[] = $this->diag('table.' . $table, $this->tableExists($table), "Table {$table}");
        }

        $migrationStatus = EnterpriseMigrationStatus::diagnose();
        foreach ($migrationStatus['tracks'] as $track) {
            $checks[] = $this->diag(
                'migration.' . $track['key'],
                (bool) $track['applied'],
                $track['label'] . (empty($track['missing_tables']) ? '' : ' — missing: ' . implode(', ', $track['missing_tables']))
            );
        }
        $checks[] = [
            'id' => 'migration.runner_hint',
            'status' => $migrationStatus['any_missing'] ? 'WARN' : 'PASS',
            'label' => $migrationStatus['runner_hint'],
        ];

        $controller = $erpRoot . '/app/controllers/Admin/AccountingControlController.php';
        $checks[] = $this->diag('controller', is_file($controller), 'AccountingControlController');

        $viewsDir = $erpRoot . '/views/admin/accounting-control/sections';
        $sections = ['dashboard', 'events', 'replay', 'audit', 'projections', 'consolidation', 'drift', 'reconciliation', 'integrity', 'settings', 'health', 'timeline', 'notifications', 'diagnostics'];
        foreach ($sections as $sec) {
            $checks[] = $this->diag('view.' . $sec, is_file($viewsDir . '/' . $sec . '.php'), "View section {$sec}");
        }

        $assets = ['control-center.js', 'control-center.css'];
        foreach ($assets as $asset) {
            $checks[] = $this->diag('asset.' . $asset, is_file($erpRoot . '/public/assets/accounting-control/' . $asset), "Asset {$asset}");
        }

        $apiFiles = ['events', 'replay', 'audit', 'projections', 'consolidation', 'drift', 'reconciliation', 'integrity'];
        foreach ($apiFiles as $api) {
            $checks[] = $this->diag('api.' . $api, is_file($root . '/api/accounting/' . $api . '.php'), "API {$api}");
        }

        $pass = count(array_filter($checks, static fn ($c) => $c['status'] === 'PASS'));
        $warn = count(array_filter($checks, static fn ($c) => $c['status'] === 'WARN'));
        $fail = count(array_filter($checks, static fn ($c) => $c['status'] === 'FAIL'));

        return [
            'checks' => $checks,
            'summary' => ['pass' => $pass, 'warn' => $warn, 'fail' => $fail, 'total' => count($checks)],
            'overall' => $fail > 0 ? 'FAIL' : ($warn > 0 ? 'WARN' : 'PASS'),
            'generated_at' => date('c'),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rawRows
     * @return list<array<string, mixed>>
     */
    private function flattenSnapshotRows(array $rawRows, string $type): array
    {
        $out = [];
        foreach ($rawRows as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            if (isset($raw['rows']) && is_array($raw['rows'])) {
                foreach ($raw['rows'] as $r) {
                    $out[] = $this->normalizeAccountRow(is_array($r) ? $r : [], $type);
                }
                continue;
            }
            if (isset($raw['accounts']) && is_array($raw['accounts'])) {
                foreach ($raw['accounts'] as $r) {
                    $out[] = $this->normalizeAccountRow(is_array($r) ? $r : [], $type);
                }
                continue;
            }
            $out[] = $this->normalizeAccountRow($raw, $type);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeAccountRow(array $row, string $type): array
    {
        $debit = (float) ($row['debit'] ?? 0);
        $credit = (float) ($row['credit'] ?? 0);
        $amount = (float) ($row['amount'] ?? ($row['balance'] ?? ($debit - $credit)));

        return [
            'account_code' => (string) ($row['account_code'] ?? $row['code'] ?? $row['category'] ?? ''),
            'account_name' => (string) ($row['account_name'] ?? $row['name'] ?? $row['label'] ?? ''),
            'debit' => $debit,
            'credit' => $credit,
            'amount' => $amount,
            'type' => $type,
            'branch_id' => $row['branch_id'] ?? null,
            'company_id' => $row['company_id'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    private function snapshotHistory(string $table, array $filters): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }
        $pdo = $this->controlPdo();
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = :cid';
            $params['cid'] = (int) $filters['company_id'];
        }
        $w = implode(' AND ', $where);
        $stmt = $pdo->prepare(
            "SELECT period_from, period_to, branch_id, created_at FROM {$table} WHERE {$w} ORDER BY created_at DESC LIMIT 24"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function snapshotCompare(string $table, array $filters): array
    {
        $history = $this->snapshotHistory($table, $filters);
        if (count($history) < 2) {
            return ['current' => $history[0] ?? null, 'previous' => null, 'delta_rows' => 0];
        }

        return [
            'current' => $history[0],
            'previous' => $history[1],
            'delta_rows' => 0,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function buildHierarchy(array $rows): array
    {
        $companies = [];
        foreach ($rows as $row) {
            $cid = (string) ($row['company_id'] ?? '0');
            $bid = (string) ($row['branch_id'] ?? '0');
            if (!isset($companies[$cid])) {
                $companies[$cid] = ['company_id' => $row['company_id'], 'branches' => []];
            }
            if (!isset($companies[$cid]['branches'][$bid])) {
                $companies[$cid]['branches'][$bid] = ['branch_id' => $row['branch_id'], 'rows' => 0];
            }
            $companies[$cid]['branches'][$bid]['rows']++;
        }
        foreach ($companies as &$c) {
            $c['branches'] = array_values($c['branches']);
        }

        return ['companies' => array_values($companies)];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    private function listCorrectionLog(array $filters): array
    {
        if (!$this->tableExists('accounting_correction_log')) {
            return [];
        }
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['company_id'])) {
            $where[] = 'company_id = :cid';
            $params['cid'] = (int) $filters['company_id'];
        }
        $w = implode(' AND ', $where);
        $pdo = $this->controlPdo();
        $stmt = $pdo->prepare(
            "SELECT id, company_id, branch_id, reconciliation_report_id, idempotency_key, status, payload, rateb_journal_entry_id, created_at, executed_at
             FROM accounting_correction_log WHERE {$w} ORDER BY id DESC LIMIT 100"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $payload = json_decode((string) ($row['payload'] ?? '{}'), true);
            $row['payload'] = is_array($payload) ? $payload : [];
        }
        unset($row);

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $corrections
     * @return list<array<string, mixed>>
     */
    private function correctionTimeline(array $corrections): array
    {
        return array_map(static fn (array $c): array => [
            'id' => $c['id'],
            'status' => $c['status'],
            'created_at' => $c['created_at'],
            'executed_at' => $c['executed_at'],
            'before' => $c['payload']['before'] ?? null,
            'after' => $c['payload']['after'] ?? null,
        ], $corrections);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    private function replayQueue(array $filters): array
    {
        if (!$this->tableExists('accounting_events')) {
            return [];
        }
        $pdo = $this->controlPdo();
        $where = "status IN ('pending','failed')";
        $params = [];
        if (!empty($filters['company_id'])) {
            $where .= ' AND company_id = :cid';
            $params['cid'] = (int) $filters['company_id'];
        }
        $stmt = $pdo->prepare(
            "SELECT event_uuid, source_system, event_type, status, company_id, branch_id, created_at
             FROM accounting_events WHERE {$where} ORDER BY id ASC LIMIT 100"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, int|float>
     */
    private function replayStats(): array
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
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function replayDashboard(array $filters): array
    {
        $detail = $this->replayDetail($filters);

        return [
            'cards' => [
                'queue_size' => count($detail['queue'] ?? []),
                'processed' => $detail['stats']['processed'] ?? 0,
                'failed' => $detail['stats']['failed'] ?? 0,
                'success_rate' => $detail['stats']['rate'] ?? 0,
            ],
            'updated_at' => $detail['updated_at'] ?? date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function reconciliationDashboard(array $filters): array
    {
        $detail = $this->reconciliationDetail($filters);

        return [
            'cards' => array_merge(['reports' => $detail['reports']['total'] ?? 0], $detail['workflow'] ?? []),
            'updated_at' => $detail['updated_at'] ?? date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function driftDashboard(array $filters): array
    {
        $detail = $this->driftDetail($filters);

        return [
            'cards' => array_merge(['reports' => $detail['reports']['total'] ?? 0], $detail['breakdown'] ?? []),
            'updated_at' => $detail['updated_at'] ?? date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function eventsDashboard(array $filters): array
    {
        $list = $this->core->listEvents(array_merge($filters, ['per_page' => 1]));

        return [
            'cards' => [
                'total_events' => $list['total'] ?? 0,
                'page_size' => $filters['per_page'] ?? 50,
            ],
            'updated_at' => date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function auditDashboard(array $filters): array
    {
        $logs = $this->core->listAuditLogs(array_merge($filters, ['per_page' => 1]));

        return [
            'cards' => ['audit_entries' => $logs['total'] ?? 0],
            'updated_at' => date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function projectionsDashboard(array $filters): array
    {
        return [
            'cards' => [
                'snapshots' => $this->scalarCountSimple('accounting_trial_balance_snapshots', $filters),
                'last_snapshot' => $this->lastTableTimestamp('accounting_trial_balance_snapshots', (int) ($filters['company_id'] ?? 0) ?: null),
            ],
            'updated_at' => date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function consolidationDashboard(array $filters): array
    {
        return [
            'cards' => [
                'consolidated_rows' => $this->scalarCountSimple('accounting_consolidated_trial_balance', $filters),
                'last_run' => $this->lastTableTimestamp('accounting_consolidated_trial_balance', (int) ($filters['company_id'] ?? 0) ?: null),
            ],
            'updated_at' => date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function integrityDashboard(array $filters): array
    {
        $ov = $this->core->integrityOverview($filters);

        return [
            'cards' => ['integrity_score' => $ov['integrity_score'] ?? 0],
            'updated_at' => date('c'),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function scalarCountSimple(string $table, array $filters): int
    {
        if (!$this->tableExists($table)) {
            return 0;
        }
        $pdo = $this->controlPdo();
        $sql = "SELECT COUNT(*) FROM {$table}";
        $params = [];
        if (!empty($filters['company_id'])) {
            $sql .= ' WHERE company_id = :cid';
            $params['cid'] = (int) $filters['company_id'];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{date:string,count:int}>
     */
    private function driftTrendChart(int $days, ?int $companyId): array
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
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    private function searchTable(string $table, string $cond, array $params, array $filters, int $limit, string $kind): array
    {
        $pdo = $this->controlPdo();
        if ($pdo === null || !$this->tableExists($table)) {
            return [];
        }
        $where = [$cond];
        if (!empty($filters['company_id']) && $this->columnExists($table, 'company_id')) {
            $where[] = 'company_id = :cid';
            $params['cid'] = (int) $filters['company_id'];
        }
        $w = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE {$w} ORDER BY id DESC LIMIT " . (int) $limit);
        $stmt->execute($params);
        $out = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $out[] = [
                'kind' => $kind,
                'id' => $row['id'] ?? null,
                'ref' => $row['event_uuid'] ?? $row['certification_hash'] ?? ($row['id'] ?? ''),
                'title' => $row['event_type'] ?? $row['action'] ?? $kind,
                'status' => $row['status'] ?? $row['risk_level'] ?? '',
                'created_at' => $row['created_at'] ?? '',
            ];
        }

        return $out;
    }

    private function timelineKindFromAction(string $action): string
    {
        if (str_contains($action, 'replay')) {
            return 'replay';
        }
        if (str_contains($action, 'snapshot') || str_contains($action, 'projection')) {
            return 'projection';
        }
        if (str_contains($action, 'consolid')) {
            return 'consolidation';
        }
        if (str_contains($action, 'correction')) {
            return 'correction';
        }
        if (str_contains($action, 'certif') || str_contains($action, 'integrity')) {
            return 'integrity';
        }

        return 'audit';
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

    private function lastAuditTimestamp(string $action): ?string
    {
        if (!$this->tableExists('accounting_audit_logs')) {
            return null;
        }
        $pdo = $this->controlPdo();
        $stmt = $pdo->prepare('SELECT created_at FROM accounting_audit_logs WHERE action = :a ORDER BY id DESC LIMIT 1');
        $stmt->execute(['a' => $action]);
        $v = $stmt->fetchColumn();

        return $v !== false ? (string) $v : null;
    }

    private function healthPassCount(): int
    {
        $health = $this->core->systemHealth();
        $n = 0;
        foreach ($health['migrations'] ?? [] as $ok) {
            if ($ok) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @return array{id:string,status:string,label:string,detail?:string}
     */
    private function diag(string $id, bool $ok, string $label, string $failAs = 'FAIL'): array
    {
        return [
            'id' => $id,
            'status' => $ok ? 'PASS' : $failAs,
            'label' => $label,
        ];
    }
}
