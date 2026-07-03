<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use RuntimeException;
use Throwable;

final class AgencyFileSyncService
{
    private function ensureSyncLib(): void
    {
        if (function_exists('rateb_agency_site_sync_run')) {
            return;
        }
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $path = dirname($root) . '/includes/rateb-test-domain-sync.php';
        if (!is_file($path)) {
            throw new RuntimeException('File sync library not found on server');
        }
        require_once $path;
    }

    private function ensureAgencyLookup(): void
    {
        if (function_exists('rateb_lookup_agency_by_id')) {
            return;
        }
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $path = dirname($root) . '/config/env/agency_lookup.php';
        if (!is_file($path)) {
            throw new RuntimeException('Agency lookup configuration not found');
        }
        require_once $path;
    }

    /** @return array{source:string,target:string,host:string} */
    public function previewForSiteUrl(string $siteUrl): array
    {
        $this->ensureSyncLib();
        $source = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        if ($source === '' && defined('RATEB_ROOT')) {
            $source = dirname(RATEB_ROOT);
        }

        return rateb_agency_site_sync_resolve($siteUrl, $source !== '' ? $source : null);
    }

    /**
     * @param array{agency_ids?:list<int>,scope?:string,confirm?:string} $options
     * @return array<string, mixed>
     */
    public function sync(array $options): array
    {
        $confirm = strtoupper(trim((string) ($options['confirm'] ?? '')));
        if ($confirm !== 'SYNC') {
            throw new RuntimeException(__('agency_erp_sync_confirm_required'));
        }

        $this->ensureSyncLib();
        $this->ensureAgencyLookup();

        $scope = strtolower(trim((string) ($options['scope'] ?? '')));
        $agencyIds = $options['agency_ids'] ?? [];
        if (!is_array($agencyIds)) {
            $agencyIds = [];
        }
        $agencyIds = array_values(array_unique(array_filter(array_map('intval', $agencyIds), static fn (int $id): bool => $id > 0)));

        $listSvc = new AgencyErpMigrationService();
        if ($scope === 'all_ready' || $scope === 'all_subscribed') {
            $rows = $listSvc->listAgencies($scope === 'all_subscribed');
            $agencyIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
            $agencyIds = array_values(array_filter($agencyIds, static fn (int $id): bool => $id > 0));
        }

        if ($agencyIds === []) {
            throw new RuntimeException(__('agency_erp_sync_select_target'));
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(900);
        }

        $source = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        if ($source === '' && defined('RATEB_ROOT')) {
            $source = dirname(RATEB_ROOT);
        }

        $results = [];
        $failed = 0;
        $success = 0;

        foreach ($agencyIds as $agencyId) {
            $agency = rateb_lookup_agency_by_id($agencyId);
            if ($agency === null) {
                $results[] = [
                    'target' => 'files',
                    'agency_id' => $agencyId,
                    'ok' => false,
                    'error' => __('agency_erp_push_not_found'),
                ];
                $failed++;
                continue;
            }

            $name = trim((string) ($agency['name'] ?? ''));
            $siteUrl = trim((string) ($agency['site_url'] ?? ''));
            if ($siteUrl === '') {
                $results[] = [
                    'target' => 'files',
                    'agency_id' => $agencyId,
                    'agency_name' => $name,
                    'ok' => false,
                    'error' => __('agency_erp_sync_no_site_url'),
                ];
                $failed++;
                continue;
            }

            try {
                $run = rateb_agency_site_sync_run($siteUrl, $source !== '' ? $source : null);
                $ok = !empty($run['ok']);
                $results[] = [
                    'target' => 'files',
                    'agency_id' => $agencyId,
                    'agency_name' => $name,
                    'site_url' => $siteUrl,
                    'host' => (string) ($run['host'] ?? ''),
                    'source' => (string) ($run['source'] ?? ''),
                    'target_path' => (string) ($run['target'] ?? ''),
                    'ok' => $ok,
                    'log' => (array) ($run['log'] ?? []),
                    'error' => $ok ? '' : __('agency_erp_sync_failed'),
                ];
                if ($ok) {
                    $success++;
                } else {
                    $failed++;
                }
            } catch (Throwable $e) {
                $results[] = [
                    'target' => 'files',
                    'agency_id' => $agencyId,
                    'agency_name' => $name,
                    'site_url' => $siteUrl,
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
                $failed++;
            }
        }

        if ($success > 0 && function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return [
            'success' => $failed === 0,
            'total' => count($results),
            'success_count' => $success,
            'failed_count' => $failed,
            'results' => $results,
        ];
    }
}
