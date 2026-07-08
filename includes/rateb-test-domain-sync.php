<?php
declare(strict_types=1);

/**
 * Copy rateb.sa application tree → agency domains on the same server.
 * Agency ERP push uses a filtered bundle (ERP only); test bootstrap may use the full tree.
 */

if (!function_exists('rateb_agency_erp_sync_rateb_erp_skip_rel')) {
    /**
     * Relative paths under rateb-erp/ skipped for agency file sync (platform-only UI).
     *
     * @return list<string>
     */
    function rateb_agency_erp_sync_rateb_erp_skip_rel(): array
    {
        return [
            'views/admin/cms',
            'views/marketing',
            'views/admin/agency-updates',
            'views/admin/executive-dashboard',
            'views/partials/platform-catalog-nav-link.php',
            'storage/backups',
            'storage/logs',
            'storage/cache',
            'storage/sessions',
        ];
    }
}

if (!function_exists('rateb_agency_erp_sync_should_skip_rel')) {
    function rateb_agency_erp_sync_should_skip_rel(string $relPath, array $skipPrefixes): bool
    {
        $rel = str_replace('\\', '/', strtolower(trim($relPath, '/\\')));
        if ($rel === '') {
            return false;
        }
        foreach ($skipPrefixes as $prefix) {
            $prefix = str_replace('\\', '/', strtolower(trim((string) $prefix, '/\\')));
            if ($prefix === '') {
                continue;
            }
            if ($rel === $prefix || str_starts_with($rel, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('rateb_agency_erp_sync_include_files')) {
    /** @return list<string> */
    function rateb_agency_erp_sync_include_files(): array
    {
        return [
            'includes/rateb-agency-super-admin-restore.php',
            'includes/rateb-test-domain-sync.php',
        ];
    }
}

if (!function_exists('rateb_agency_erp_sync_config_env_files')) {
    /** @return list<string> */
    function rateb_agency_erp_sync_config_env_files(): array
    {
        return [
            'agency_lookup.php',
            'erp_agency_resolver.php',
            'agency_resolver.php',
            'control_db_for_lookup.php',
            'load.php',
            'dotenv_bridge.php',
            'default.php',
        ];
    }
}

if (!function_exists('rateb_agency_erp_sync_root_files')) {
    /** @return list<string> */
    function rateb_agency_erp_sync_root_files(): array
    {
        return ['index.php', '.htaccess', 'favicon.php'];
    }
}

if (!function_exists('rateb_agency_copy_tree_filtered')) {
    /**
     * @param list<string> $skipPrefixes relative paths under $src root to skip
     */
    function rateb_agency_copy_tree_filtered(string $src, string $dst, array $skipPrefixes, array &$log, string $relBase = ''): bool
    {
        if (!is_dir($src)) {
            $log[] = 'SKIP missing: ' . $src;
            return true;
        }
        if (rateb_agency_erp_sync_should_skip_rel($relBase, $skipPrefixes)) {
            $log[] = 'SKIP agency-excluded: ' . ($relBase !== '' ? $relBase : basename($src));
            return true;
        }
        if (!is_dir($dst) && !@mkdir($dst, 0755, true) && !is_dir($dst)) {
            $log[] = 'FAIL mkdir: ' . $dst;
            return false;
        }
        $items = @scandir($src);
        if (!is_array($items)) {
            $log[] = 'FAIL scandir: ' . $src;
            return false;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $childRel = $relBase === '' ? $item : $relBase . '/' . $item;
            if (rateb_agency_erp_sync_should_skip_rel($childRel, $skipPrefixes)) {
                $log[] = 'SKIP agency-excluded: ' . $childRel;
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $item;
            $to = $dst . DIRECTORY_SEPARATOR . $item;
            if (is_dir($from)) {
                if (!rateb_agency_copy_tree_filtered($from, $to, $skipPrefixes, $log, $childRel)) {
                    return false;
                }
                continue;
            }
            if (!@copy($from, $to)) {
                $log[] = 'FAIL copy: ' . $from;
                return false;
            }
            @chmod($to, 0644);
        }

        return true;
    }
}

if (!function_exists('rateb_agency_erp_sync_run')) {
    /**
     * ERP-only bundle for any agency domain (not full rateb.sa tree).
     *
     * @return array{ok:bool,log:list<string>,source:string,target:string}
     */
    function rateb_agency_erp_sync_run(?string $sourceOverride = null, ?string $targetOverride = null): array
    {
        $paths = rateb_test_domain_sync_resolve($sourceOverride, $targetOverride);
        $source = $paths['source'];
        $target = $paths['target'];
        $log = [];
        $ok = true;

        if ($source === '' || !is_dir($source)) {
            return ['ok' => false, 'log' => ['Source document root not found.'], 'source' => $source, 'target' => $target];
        }
        if ($target === '' || $target === $source) {
            return [
                'ok' => false,
                'log' => ['Target path could not be resolved.'],
                'source' => $source,
                'target' => $target,
            ];
        }
        if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
            return ['ok' => false, 'log' => ['Cannot create target: ' . $target], 'source' => $source, 'target' => $target];
        }

        $log[] = 'Source: ' . $source;
        $log[] = 'Target: ' . $target;
        $log[] = 'Mode: agency ERP bundle (filtered)';

        $skip = rateb_agency_erp_sync_rateb_erp_skip_rel();
        $erpSrc = $source . DIRECTORY_SEPARATOR . 'rateb-erp';
        $erpDst = $target . DIRECTORY_SEPARATOR . 'rateb-erp';
        if (!is_dir($erpSrc)) {
            $log[] = 'FAIL missing rateb-erp on source';
            return ['ok' => false, 'log' => $log, 'source' => $source, 'target' => $target];
        }
        $log[] = 'COPY rateb-erp (agency ERP only) …';
        if (!rateb_agency_copy_tree_filtered($erpSrc, $erpDst, $skip, $log, '')) {
            return ['ok' => false, 'log' => $log, 'source' => $source, 'target' => $target];
        }

        foreach (rateb_agency_erp_sync_include_files() as $rel) {
            $src = $source . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            $dst = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!is_file($src)) {
                $log[] = 'SKIP ' . $rel;
                continue;
            }
            $dir = dirname($dst);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                $log[] = 'FAIL mkdir: ' . $dir;
                $ok = false;
                continue;
            }
            if (!@copy($src, $dst)) {
                $log[] = 'FAIL ' . $rel;
                $ok = false;
            } else {
                $log[] = 'OK ' . $rel;
            }
        }

        $envSrcDir = $source . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'env';
        $envDstDir = $target . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'env';
        if (is_dir($envSrcDir)) {
            if (!is_dir($envDstDir) && !@mkdir($envDstDir, 0755, true) && !is_dir($envDstDir)) {
                $log[] = 'FAIL mkdir: ' . $envDstDir;
                $ok = false;
            } else {
                foreach (rateb_agency_erp_sync_config_env_files() as $envFile) {
                    $src = $envSrcDir . DIRECTORY_SEPARATOR . $envFile;
                    $dst = $envDstDir . DIRECTORY_SEPARATOR . $envFile;
                    if (!is_file($src)) {
                        $log[] = 'SKIP config/env/' . $envFile;
                        continue;
                    }
                    if (!@copy($src, $dst)) {
                        $log[] = 'FAIL config/env/' . $envFile;
                        $ok = false;
                    } else {
                        $log[] = 'OK config/env/' . $envFile;
                    }
                }
            }
        } else {
            $log[] = 'SKIP config/env (missing on source)';
        }

        foreach (rateb_agency_erp_sync_root_files() as $file) {
            $src = $source . DIRECTORY_SEPARATOR . $file;
            if (!is_file($src)) {
                continue;
            }
            if (!@copy($src, $target . DIRECTORY_SEPARATOR . $file)) {
                $log[] = 'FAIL root file: ' . $file;
                $ok = false;
            } else {
                $log[] = 'OK ' . $file;
            }
        }

        $buildMarker = $erpSrc . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'ratib-erp-build.txt';
        if (is_file($buildMarker)) {
            $log[] = 'BUILD ' . trim((string) @file_get_contents($buildMarker));
        }

        return ['ok' => $ok, 'log' => $log, 'source' => $source, 'target' => $target];
    }
}

if (!function_exists('rateb_test_domain_sync_paths')) {
    /** @return list<string> */
    function rateb_test_domain_sync_paths(): array
    {
        return [
            'rateb-erp', 'config', 'core', 'app', 'includes', 'css', 'js',
            'pages', 'api', 'control-panel', 'admin', 'public',
        ];
    }
}

if (!function_exists('rateb_test_domain_sync_root_files')) {
    /** @return list<string> */
    function rateb_test_domain_sync_root_files(): array
    {
        return ['index.php', '.htaccess', 'favicon.php', 'composer.json', 'control.php'];
    }
}

if (!function_exists('rateb_test_domain_sync_critical_files')) {
    /** @return list<string> */
    function rateb_test_domain_sync_critical_files(): array
    {
        return [
            'core/TenantExecutionContext.php',
            'core/bootstrap.php',
            'app/Core/ErrorTracker.php',
            'config/env/test_rateb_sa.php',
        ];
    }
}

if (!function_exists('rateb_test_domain_sync_resolve')) {
    /**
     * @return array{source:string,target:string}
     */
    function rateb_test_domain_sync_resolve(?string $sourceOverride = null, ?string $targetOverride = null): array
    {
        $source = rtrim((string) ($sourceOverride ?? $_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $target = rtrim((string) ($targetOverride ?? ''), '/\\');
        if ($target === '') {
            $fromEnv = getenv('RATEB_TEST_DOMAIN_DOCROOT');
            if (is_string($fromEnv) && trim($fromEnv) !== '') {
                $target = rtrim(trim($fromEnv), '/\\');
            }
        }
        if ($target === '' && $source !== '') {
            $target = str_replace(
                [DIRECTORY_SEPARATOR . 'rateb.sa' . DIRECTORY_SEPARATOR, '/rateb.sa/'],
                [DIRECTORY_SEPARATOR . 'test.rateb.sa' . DIRECTORY_SEPARATOR, '/test.rateb.sa/'],
                $source
            );
            $target = rtrim((string) $target, '/\\');
        }
        if ($target === '' && $source !== '') {
            $parent = dirname($source);
            $target = $parent . DIRECTORY_SEPARATOR . 'test.rateb.sa' . DIRECTORY_SEPARATOR . 'public_html';
        }

        return ['source' => $source, 'target' => $target];
    }
}

if (!function_exists('rateb_test_domain_copy_tree')) {
    function rateb_test_domain_copy_tree(string $src, string $dst, array &$log): bool
    {
        if (!is_dir($src)) {
            $log[] = 'SKIP missing: ' . $src;
            return true;
        }
        if (!is_dir($dst) && !@mkdir($dst, 0755, true) && !is_dir($dst)) {
            $log[] = 'FAIL mkdir: ' . $dst;
            return false;
        }
        $items = @scandir($src);
        if (!is_array($items)) {
            $log[] = 'FAIL scandir: ' . $src;
            return false;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $item;
            $to = $dst . DIRECTORY_SEPARATOR . $item;
            if (is_dir($from)) {
                if (!rateb_test_domain_copy_tree($from, $to, $log)) {
                    return false;
                }
                continue;
            }
            if (!@copy($from, $to)) {
                $log[] = 'FAIL copy: ' . $from;
                return false;
            }
            @chmod($to, 0644);
        }
        return true;
    }
}

if (!function_exists('rateb_agency_site_sync_resolve')) {
    /**
     * Resolve document-root paths for copying platform tree → an agency site on the same server.
     *
     * @return array{source:string,target:string,host:string}
     */
    function rateb_agency_site_sync_resolve(string $siteUrl, ?string $sourceOverride = null): array
    {
        $paths = rateb_test_domain_sync_resolve($sourceOverride, null);
        $source = $paths['source'];
        $siteUrl = trim($siteUrl);
        if ($siteUrl === '') {
            return ['source' => $source, 'target' => $paths['target'], 'host' => ''];
        }
        $host = strtolower(trim((string) (parse_url($siteUrl, PHP_URL_HOST) ?: '')));
        if ($host === '') {
            return ['source' => $source, 'target' => '', 'host' => ''];
        }
        $resolver = dirname(__DIR__) . '/config/env/erp_agency_resolver.php';
        if (is_file($resolver)) {
            require_once $resolver;
        }
        if (function_exists('rateb_erp_is_main_platform_host') && rateb_erp_is_main_platform_host($host)) {
            return ['source' => $source, 'target' => '', 'host' => $host];
        }
        $target = $source;
        if (strpos($target, DIRECTORY_SEPARATOR . 'rateb.sa' . DIRECTORY_SEPARATOR) !== false) {
            $target = str_replace(
                DIRECTORY_SEPARATOR . 'rateb.sa' . DIRECTORY_SEPARATOR,
                DIRECTORY_SEPARATOR . $host . DIRECTORY_SEPARATOR,
                $target
            );
        } elseif (strpos($target, '/rateb.sa/') !== false) {
            $target = str_replace('/rateb.sa/', '/' . $host . '/', $target);
        } else {
            $parent = dirname($source);
            $target = $parent . DIRECTORY_SEPARATOR . $host . DIRECTORY_SEPARATOR . 'public_html';
        }
        $target = rtrim((string) $target, '/\\');

        return ['source' => $source, 'target' => $target, 'host' => $host];
    }
}

if (!function_exists('rateb_agency_site_sync_run')) {
    /**
     * @return array{ok:bool,log:list<string>,source:string,target:string,host:string}
     */
    function rateb_agency_site_sync_run(string $siteUrl, ?string $sourceOverride = null): array
    {
        $resolved = rateb_agency_site_sync_resolve($siteUrl, $sourceOverride);
        if ($resolved['target'] === '' || $resolved['target'] === $resolved['source']) {
            $host = $resolved['host'] !== '' ? $resolved['host'] : trim($siteUrl);

            return [
                'ok' => false,
                'log' => ['Target path could not be resolved for: ' . $host],
                'source' => $resolved['source'],
                'target' => $resolved['target'],
                'host' => $resolved['host'],
            ];
        }

        $run = rateb_agency_erp_sync_run($resolved['source'], $resolved['target']);

        return [
            'ok' => !empty($run['ok']),
            'log' => (array) ($run['log'] ?? []),
            'source' => (string) ($run['source'] ?? $resolved['source']),
            'target' => (string) ($run['target'] ?? $resolved['target']),
            'host' => $resolved['host'],
        ];
    }
}

if (!function_exists('rateb_test_domain_sync_run')) {
    /**
     * @return array{ok:bool,log:list<string>,source:string,target:string}
     */
    function rateb_test_domain_sync_run(?string $sourceOverride = null, ?string $targetOverride = null): array
    {
        $paths = rateb_test_domain_sync_resolve($sourceOverride, $targetOverride);
        $source = $paths['source'];
        $target = $paths['target'];
        $log = [];
        $ok = true;

        if ($source === '' || !is_dir($source)) {
            return ['ok' => false, 'log' => ['Source document root not found.'], 'source' => $source, 'target' => $target];
        }
        if ($target === '' || $target === $source) {
            return [
                'ok' => false,
                'log' => ['Target path could not be resolved. Set RATEB_TEST_DOMAIN_DOCROOT in .env'],
                'source' => $source,
                'target' => $target,
            ];
        }
        if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
            return ['ok' => false, 'log' => ['Cannot create target: ' . $target], 'source' => $source, 'target' => $target];
        }

        $log[] = 'Source: ' . $source;
        $log[] = 'Target: ' . $target;

        foreach (rateb_test_domain_sync_paths() as $rel) {
            $src = $source . DIRECTORY_SEPARATOR . $rel;
            $dst = $target . DIRECTORY_SEPARATOR . $rel;
            if (!is_dir($src)) {
                $log[] = 'SKIP ' . $rel;
                continue;
            }
            $log[] = 'COPY ' . $rel . ' …';
            if (!rateb_test_domain_copy_tree($src, $dst, $log)) {
                return ['ok' => false, 'log' => $log, 'source' => $source, 'target' => $target];
            }
        }

        foreach (rateb_test_domain_sync_root_files() as $file) {
            $src = $source . DIRECTORY_SEPARATOR . $file;
            if (!is_file($src)) {
                continue;
            }
            if (!@copy($src, $target . DIRECTORY_SEPARATOR . $file)) {
                $log[] = 'FAIL root file: ' . $file;
                $ok = false;
            } else {
                $log[] = 'OK ' . $file;
            }
        }

        if ($ok && is_file($source . DIRECTORY_SEPARATOR . '.env') && !is_file($target . DIRECTORY_SEPARATOR . '.env')) {
            if (@copy($source . DIRECTORY_SEPARATOR . '.env', $target . DIRECTORY_SEPARATOR . '.env')) {
                $log[] = 'OK .env (copied once; edit test DB settings if needed)';
            }
        }

        if ($ok) {
            foreach (rateb_test_domain_sync_critical_files() as $rel) {
                $p = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if (is_file($p)) {
                    $log[] = 'VERIFY OK ' . $rel;
                } else {
                    $log[] = 'VERIFY MISSING ' . $rel;
                    $ok = false;
                }
            }
        }

        return ['ok' => $ok, 'log' => $log, 'source' => $source, 'target' => $target];
    }
}
