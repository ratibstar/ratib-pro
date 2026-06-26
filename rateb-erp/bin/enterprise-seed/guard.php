<?php
declare(strict_types=1);

/**
 * Enterprise seed guard — blocks accidental production seed unless explicitly opted in.
 *
 * Official pre-GA development on admin_rateb_erp:
 *   RATEB_OFFICIAL_DEV_DB=1 RATEB_ENTERPRISE_SEED=1 php bin/enterprise-seed/run.php
 */
function enterprise_seed_guard(): void
{
    $env = strtolower(trim((string) (getenv('RATEB_ENV') ?: getenv('APP_ENV') ?: '')));
    $allow = getenv('RATEB_ENTERPRISE_SEED') === '1';
    $officialDev = getenv('RATEB_OFFICIAL_DEV_DB') === '1';

    if (($env === 'production' || $env === 'prod') && !$officialDev) {
        fwrite(STDERR, "ABORT: RATEB_ENV=production — set RATEB_OFFICIAL_DEV_DB=1 for official dev database.\n");
        exit(1);
    }
    if (!$allow && !in_array($env, ['staging', 'stage', 'local', 'dev', 'development'], true) && !$officialDev) {
        fwrite(STDERR, "ABORT: Set RATEB_ENV=development, RATEB_ENTERPRISE_SEED=1, or RATEB_OFFICIAL_DEV_DB=1.\n");
        exit(1);
    }

    if (!$officialDev) {
        $dbName = strtolower(trim((string) (getenv('RATEB_ERP_DB_NAME') ?: getenv('RATEB_DB_NAME') ?: getenv('DB_NAME') ?: '')));
        if ($dbName !== '' && in_array($dbName, ['admin_rateb-erp', 'admin_rateb_erp'], true)) {
            fwrite(STDERR, "ABORT: Refusing seed on {$dbName} without RATEB_OFFICIAL_DEV_DB=1.\n");
            exit(1);
        }
    }
}
