<?php
declare(strict_types=1);

namespace App\Accounting\Support;

final class AccountingConfig
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $path = dirname(__DIR__, 3) . '/config/accounting.php';
        self::$config = is_file($path) ? (require $path) : [];

        return self::$config;
    }

    public static function eventStoreEnabled(): bool
    {
        if (defined('ACCOUNTING_EVENT_STORE_ENABLED')) {
            return (bool) ACCOUNTING_EVENT_STORE_ENABLED;
        }

        return !empty(self::all()['event_store_enabled']);
    }

    public static function replayEnabled(): bool
    {
        if (defined('ACCOUNTING_REPLAY_ENABLED')) {
            return (bool) ACCOUNTING_REPLAY_ENABLED;
        }

        return !empty(self::all()['replay_enabled']);
    }

    public static function auditEnabled(): bool
    {
        if (defined('ACCOUNTING_AUDIT_ENABLED')) {
            return (bool) ACCOUNTING_AUDIT_ENABLED;
        }

        $config = self::all();

        return array_key_exists('audit_enabled', $config) ? (bool) $config['audit_enabled'] : true;
    }

    public static function projectionsEnabled(): bool
    {
        if (defined('ACCOUNTING_PROJECTIONS_ENABLED')) {
            return (bool) ACCOUNTING_PROJECTIONS_ENABLED;
        }

        return !empty(self::all()['projections_enabled']);
    }

    public static function consolidationEnabled(): bool
    {
        if (defined('ACCOUNTING_CONSOLIDATION_ENABLED')) {
            return (bool) ACCOUNTING_CONSOLIDATION_ENABLED;
        }

        return !empty(self::all()['consolidation_enabled']);
    }

    public static function driftDetectionEnabled(): bool
    {
        if (defined('ACCOUNTING_DRIFT_DETECTION_ENABLED')) {
            return (bool) ACCOUNTING_DRIFT_DETECTION_ENABLED;
        }

        return !empty(self::all()['drift_detection_enabled']);
    }

    public static function integrityEnabled(): bool
    {
        if (defined('ACCOUNTING_INTEGRITY_ENABLED')) {
            return (bool) ACCOUNTING_INTEGRITY_ENABLED;
        }

        return !empty(self::all()['integrity_enabled']);
    }

    public static function ledgerLockEnforcementEnabled(): bool
    {
        if (defined('ACCOUNTING_LEDGER_LOCK_ENFORCEMENT_ENABLED')) {
            return (bool) ACCOUNTING_LEDGER_LOCK_ENFORCEMENT_ENABLED;
        }

        return !empty(self::all()['ledger_lock_enforcement_enabled']);
    }

    public static function correctionExecutorEnabled(): bool
    {
        if (defined('ACCOUNTING_CORRECTION_EXECUTOR_ENABLED')) {
            return (bool) ACCOUNTING_CORRECTION_EXECUTOR_ENABLED;
        }

        return !empty(self::all()['correction_executor_enabled']);
    }

    public static function correctionAutoFixEnabled(): bool
    {
        if (defined('ACCOUNTING_CORRECTION_AUTO_FIX_ENABLED')) {
            return (bool) ACCOUNTING_CORRECTION_AUTO_FIX_ENABLED;
        }

        return !empty(self::all()['correction_auto_fix_enabled']);
    }

    public static function auditCertificationEnabled(): bool
    {
        if (defined('ACCOUNTING_AUDIT_CERTIFICATION_ENABLED')) {
            return (bool) ACCOUNTING_AUDIT_CERTIFICATION_ENABLED;
        }

        return !empty(self::all()['audit_certification_enabled']);
    }
}
