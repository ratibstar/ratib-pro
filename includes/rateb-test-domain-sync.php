<?php
declare(strict_types=1);

/**
 * Copy rateb.sa application tree → test.rateb.sa (platform ops).
 */

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

        $run = rateb_test_domain_sync_run($resolved['source'], $resolved['target']);

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
