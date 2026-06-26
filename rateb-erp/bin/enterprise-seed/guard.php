<?php
declare(strict_types=1);

/**
 * Staging-only guard — never run enterprise seed on production.
 */
function enterprise_seed_guard(): void
{
    $env = strtolower(trim((string) (getenv('RATEB_ENV') ?: getenv('APP_ENV') ?: '')));
    $allow = getenv('RATEB_ENTERPRISE_SEED') === '1';

    if ($env === 'production' || $env === 'prod') {
        fwrite(STDERR, "ABORT: RATEB_ENV=production — enterprise seed is staging-only.\n");
        exit(1);
    }
    if (!$allow && !in_array($env, ['staging', 'stage', 'local', 'dev', 'development'], true)) {
        fwrite(STDERR, "ABORT: Set RATEB_ENV=staging or RATEB_ENTERPRISE_SEED=1 to run seed.\n");
        exit(1);
    }
    $dbName = strtolower(trim((string) (getenv('RATEB_ERP_DB_NAME') ?: getenv('RATEB_DB_NAME') ?: getenv('DB_NAME') ?: '')));
    if ($dbName !== '' && in_array($dbName, ['admin_rateb-erp', 'admin_rateb_erp'], true)) {
        fwrite(STDERR, "ABORT: Refusing seed on production database: {$dbName}\n");
        exit(1);
    }
}
