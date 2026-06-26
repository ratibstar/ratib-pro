<?php
declare(strict_types=1);

/**
 * Staging-only guard — never run enterprise seed on production.
 */
function enterprise_seed_guard(): void
{
    $env = strtolower(trim((string) (getenv('RATEB_ENV') ?: getenv('APP_ENV') ?: '')));
    $allow = getenv('RATEB_ENTERPRISE_SEED') === '1';
    $host = strtolower((string) (getenv('RATEB_ERP_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost'));

    if ($env === 'production' || $env === 'prod') {
        fwrite(STDERR, "ABORT: RATEB_ENV=production — enterprise seed is staging-only.\n");
        exit(1);
    }
    if (!$allow && !in_array($env, ['staging', 'stage', 'local', 'dev', 'development'], true)) {
        fwrite(STDERR, "ABORT: Set RATEB_ENV=staging or RATEB_ENTERPRISE_SEED=1 to run seed.\n");
        exit(1);
    }
    if (str_contains($host, 'out.ratib.sa') || str_contains($host, 'ratib.sa')) {
        fwrite(STDERR, "ABORT: Refusing seed against production-like host: {$host}\n");
        exit(1);
    }
}
