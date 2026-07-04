<?php
declare(strict_types=1);

namespace App\Accounting\Infrastructure;

/**
 * Idempotent schema fixes for enterprise accounting tables on admin_rateb.
 * CREATE TABLE IF NOT EXISTS does not add columns to older partial tables.
 */
final class AccountingSchemaCatchup
{
    private static bool $done = false;

    /** @var array<string, array<string, string>> */
    private const TABLE_COLUMNS = [
        'accounting_events' => [
            'payload' => 'JSON NULL',
            'processed_at' => 'TIMESTAMP NULL DEFAULT NULL',
            'company_id' => 'INT NULL',
            'branch_id' => 'INT NULL',
        ],
        'accounting_drift_reports' => [
            'company_id' => 'INT NULL',
            'branch_id' => 'INT NULL',
            'period_from' => 'DATE NULL',
            'period_to' => 'DATE NULL',
            'payload' => 'JSON NULL',
        ],
        'accounting_reconciliation_reports' => [
            'company_id' => 'INT NULL',
            'branch_id' => 'INT NULL',
            'period_from' => 'DATE NULL',
            'period_to' => 'DATE NULL',
            'drift_report_id' => 'BIGINT UNSIGNED NULL',
            'risk_level' => "VARCHAR(16) NOT NULL DEFAULT 'low'",
            'payload' => 'JSON NULL',
        ],
        'accounting_audit_evidence_packs' => [
            'company_id' => 'INT NULL',
            'branch_id' => 'INT NULL',
            'period_from' => 'DATE NULL',
            'period_to' => 'DATE NULL',
            'certification_hash' => 'VARCHAR(64) NULL',
            'payload' => 'JSON NULL',
        ],
        'accounting_trial_balance_snapshots' => [
            'payload' => 'JSON NULL',
            'snapshot_key' => 'VARCHAR(191) NULL',
        ],
        'accounting_balance_sheet_snapshots' => [
            'payload' => 'JSON NULL',
            'snapshot_key' => 'VARCHAR(191) NULL',
        ],
        'accounting_profit_loss_snapshots' => [
            'payload' => 'JSON NULL',
            'snapshot_key' => 'VARCHAR(191) NULL',
        ],
        'accounting_cashflow_snapshots' => [
            'payload' => 'JSON NULL',
            'snapshot_key' => 'VARCHAR(191) NULL',
        ],
        'accounting_consolidated_trial_balance' => [
            'payload' => 'JSON NULL',
            'consolidation_run_id' => 'VARCHAR(64) NULL',
        ],
        'accounting_consolidated_balance_sheet' => [
            'payload' => 'JSON NULL',
            'consolidation_run_id' => 'VARCHAR(64) NULL',
        ],
        'accounting_consolidated_profit_loss' => [
            'payload' => 'JSON NULL',
            'consolidation_run_id' => 'VARCHAR(64) NULL',
        ],
        'accounting_period_closures' => [
            'payload' => 'JSON NULL',
            'status' => "VARCHAR(20) NOT NULL DEFAULT 'soft_closed'",
        ],
    ];

    public static function ensure(?\PDO $pdo = null): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;

        $pdo = $pdo ?? AccountingConnectionFactory::pdo();
        if ($pdo === null) {
            return;
        }

        foreach (self::TABLE_COLUMNS as $table => $columns) {
            if (!self::tableExists($pdo, $table)) {
                continue;
            }
            foreach ($columns as $column => $definition) {
                self::addColumnIfMissing($pdo, $table, $column, $definition);
            }
        }
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

    private static function columnExists(\PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE :col');
            $stmt->execute(['col' => $column]);

            return (bool) $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return false;
        }
    }

    private static function addColumnIfMissing(\PDO $pdo, string $table, string $column, string $definition): void
    {
        if (self::columnExists($pdo, $table, $column)) {
            return;
        }
        try {
            $safeTable = str_replace('`', '', $table);
            $safeCol = str_replace('`', '', $column);
            $pdo->exec("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeCol}` {$definition}");
        } catch (\Throwable) {
            // Non-fatal — migrate runner can still fix manually.
        }
    }
}
