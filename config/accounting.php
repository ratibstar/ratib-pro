<?php
declare(strict_types=1);

return [
    'event_store_enabled' => filter_var(getenv('ACCOUNTING_EVENT_STORE_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
    'replay_enabled' => filter_var(getenv('ACCOUNTING_REPLAY_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
    'audit_enabled' => filter_var(getenv('ACCOUNTING_AUDIT_ENABLED') ?: true, FILTER_VALIDATE_BOOLEAN),
    'gateway_enabled' => filter_var(getenv('ACCOUNTING_GATEWAY_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),

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
