<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Token gate for ERP administrative health probes (deploy/ops only).
 * Uses the same secret as run-migrations.php — never exposed in responses.
 */
final class HealthProbeAuth
{
    public static function verifyRequest(): bool
    {
        $provided = trim((string) ($_SERVER['HTTP_X_RATEB_HEALTH_TOKEN'] ?? ''));
        if ($provided === '') {
            $provided = trim((string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? ''));
        }
        if ($provided === '') {
            return false;
        }
        $expected = self::expectedToken();
        return $expected !== '' && hash_equals($expected, $provided);
    }

    public static function expectedToken(): string
    {
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        foreach ([
            $root . '/storage/deploy-migrate-token',
            $root . '/storage/.deploy-migrate-token',
        ] as $tokenFile) {
            if (is_file($tokenFile)) {
                $token = trim((string) file_get_contents($tokenFile));
                if ($token !== '') {
                    return $token;
                }
            }
        }
        $fromEnv = getenv('RATEB_ERP_MIGRATE_TOKEN');
        if ($fromEnv !== false && $fromEnv !== '') {
            return (string) $fromEnv;
        }
        if (defined('RATEB_ERP_MIGRATE_TOKEN') && (string) RATEB_ERP_MIGRATE_TOKEN !== '') {
            return (string) RATEB_ERP_MIGRATE_TOKEN;
        }
        return '';
    }
}
