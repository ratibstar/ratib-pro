<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Admin;

use Rateb\PlatformCatalog\Application\Services\MigrationService;

/** Token-gated migrations via catalog front controller. */
final class MigrateController
{
    public function run(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $token = trim((string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? $_GET['token'] ?? ''));
        $expected = $this->expectedToken();
        if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }
            $log = (new MigrationService())->runAll();
            echo json_encode(['ok' => true, 'log' => $log], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    private function expectedToken(): string
    {
        $root = defined('RATEB_CATALOG_ROOT') ? (string) RATEB_CATALOG_ROOT : dirname(__DIR__, 4);
        foreach ([
            $root . '/storage/deploy-migrate-token',
            $root . '/storage/.deploy-migrate-token',
            dirname($root) . '/rateb-erp/storage/deploy-migrate-token',
            dirname($root) . '/rateb-erp/storage/.deploy-migrate-token',
        ] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $token = trim((string) file_get_contents($file));
            if ($token !== '') {
                return $token;
            }
        }

        $env = getenv('RATEB_ERP_MIGRATE_TOKEN');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }
        if (defined('RATEB_ERP_MIGRATE_TOKEN') && trim((string) RATEB_ERP_MIGRATE_TOKEN) !== '') {
            return trim((string) RATEB_ERP_MIGRATE_TOKEN);
        }

        $cpanel = getenv('CPANEL_API_TOKEN');
        if (is_string($cpanel) && trim($cpanel) !== '') {
            return trim($cpanel);
        }

        return '';
    }
}
