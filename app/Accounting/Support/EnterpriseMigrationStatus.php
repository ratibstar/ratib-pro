<?php
declare(strict_types=1);

namespace App\Accounting\Support;

use App\Accounting\Infrastructure\AccountingConnectionFactory;

/**
 * Read-only enterprise migration readiness (never auto-applies SQL).
 */
final class EnterpriseMigrationStatus
{
    /** @return list<array{key:string,label:string,tables:list<string>,sql_file:string,applied:bool,missing_tables:list<string>}> */
    public static function tracks(): array
    {
        $root = dirname(__DIR__, 3);

        return [
            [
                'key' => 'event_store',
                'label' => 'Phase 3 — Event Store',
                'sql_file' => 'config/migrations/20260704_accounting_event_store.sql',
                'tables' => ['accounting_events', 'accounting_processed_events', 'accounting_audit_logs'],
            ],
            [
                'key' => 'projections',
                'label' => 'Phase 4 — Projections & Consolidation',
                'sql_file' => 'config/migrations/20260704_accounting_phase4_projections.sql',
                'tables' => [
                    'accounting_trial_balance_snapshots',
                    'accounting_balance_sheet_snapshots',
                    'accounting_profit_loss_snapshots',
                    'accounting_cashflow_snapshots',
                    'accounting_consolidated_trial_balance',
                    'accounting_consolidated_balance_sheet',
                    'accounting_consolidated_profit_loss',
                    'accounting_period_closures',
                    'accounting_drift_reports',
                ],
            ],
            [
                'key' => 'integrity',
                'label' => 'Phase 5 — Integrity Layer',
                'sql_file' => 'config/migrations/20260704_accounting_phase5_integrity.sql',
                'tables' => [
                    'accounting_reconciliation_reports',
                    'accounting_correction_log',
                    'accounting_audit_evidence_packs',
                ],
            ],
        ];
    }

    /**
     * @return array{tracks:list<array<string,mixed>>,all_applied:bool,any_missing:bool,runner_hint:string}
     */
    public static function diagnose(): array
    {
        $pdo = AccountingConnectionFactory::pdo();
        $tracks = [];
        $anyMissing = false;

        foreach (self::tracks() as $track) {
            $sqlPath = dirname(__DIR__, 3) . '/' . ltrim((string) $track['sql_file'], '/');
            $missing = [];
            $applied = true;

            if ($pdo !== null) {
                foreach ($track['tables'] as $table) {
                    if (!self::tableExists($pdo, $table)) {
                        $missing[] = $table;
                        $applied = false;
                    }
                }
            } else {
                $applied = false;
                $missing = $track['tables'];
            }

            if (!$applied) {
                $anyMissing = true;
            }

            $tracks[] = [
                'key' => $track['key'],
                'label' => $track['label'],
                'sql_file' => $track['sql_file'],
                'sql_exists' => is_file($sqlPath),
                'applied' => $applied,
                'missing_tables' => $missing,
                'manual_runner' => self::runnerHint((string) $track['key']),
            ];
        }

        return [
            'tracks' => $tracks,
            'all_applied' => !$anyMissing,
            'any_missing' => $anyMissing,
            'runner_hint' => 'Apply manually via public/run-accounting-*-migrate.php with X-Rateb-Migrate-Token (never auto-run in production).',
        ];
    }

    private static function runnerHint(string $key): string
    {
        return match ($key) {
            'event_store' => 'public/run-accounting-event-store-migrate.php',
            'projections' => 'public/run-accounting-phase4-migrate.php',
            'integrity' => 'public/run-accounting-phase5-migrate.php',
            default => 'public/run-accounting-event-store-migrate.php',
        };
    }

    private static function tableExists(\PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));

            return $stmt !== false && $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }
}
