<?php
declare(strict_types=1);

return [
    'event_store_enabled' => filter_var(getenv('ACCOUNTING_EVENT_STORE_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
    'replay_enabled' => filter_var(getenv('ACCOUNTING_REPLAY_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
    'audit_enabled' => filter_var(getenv('ACCOUNTING_AUDIT_ENABLED') ?: true, FILTER_VALIDATE_BOOLEAN),
    'gateway_enabled' => filter_var(
        (($g = getenv('ACCOUNTING_GATEWAY_ENABLED')) !== false && $g !== '')
            ? $g
            : (getenv('ACCOUNTING_EVENT_STORE_ENABLED') ?: '0'),
        FILTER_VALIDATE_BOOLEAN
    ),

    'projections_enabled' => filter_var(getenv('ACCOUNTING_PROJECTIONS_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
    'consolidation_enabled' => filter_var(getenv('ACCOUNTING_CONSOLIDATION_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
    'drift_detection_enabled' => filter_var(getenv('ACCOUNTING_DRIFT_DETECTION_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),

    'integrity_enabled' => filter_var(getenv('ACCOUNTING_INTEGRITY_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
    'ledger_lock_enforcement_enabled' => filter_var(getenv('ACCOUNTING_LEDGER_LOCK_ENFORCEMENT_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
    'correction_executor_enabled' => filter_var(getenv('ACCOUNTING_CORRECTION_EXECUTOR_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
    'correction_auto_fix_enabled' => filter_var(getenv('ACCOUNTING_CORRECTION_AUTO_FIX_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
    'audit_certification_enabled' => filter_var(getenv('ACCOUNTING_AUDIT_CERTIFICATION_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),

    /*
    | Optional PDO DSN overrides for cross-system read-only reporting.
    | When null, AccountingConnectionFactory resolves from Laravel DB or site config.
    */
    'connections' => [
        'rateb-erp' => null,
        'main-site' => null,
        'control-panel' => null,
        'ledger' => null,
    ],
];
