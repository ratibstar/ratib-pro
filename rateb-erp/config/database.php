<?php
declare(strict_types=1);

$ratebParentEnv = dirname(__DIR__, 2) . '/config/env';
$erpAgencyResolver = $ratebParentEnv . '/erp_agency_resolver.php';
if (is_file($erpAgencyResolver) && !defined('RATEB_ERP_AGENCY_RESOLVED')) {
    require_once $erpAgencyResolver;
    rateb_resolve_agency_erp_from_request();
}

if (!function_exists('rateb_erp_database_name')) {
    function rateb_erp_database_name(): string
    {
        if (PHP_SAPI !== 'cli' && function_exists('rateb_agency_erp_binding_for_request_host')) {
            $lookupFile = dirname(__DIR__, 2) . '/config/env/agency_lookup.php';
            if (is_file($lookupFile)) {
                require_once $lookupFile;
                $binding = rateb_agency_erp_binding_for_request_host();
                if (is_array($binding) && trim((string) ($binding['db'] ?? '')) !== '') {
                    return (string) $binding['db'];
                }
            }
        }

        $name = '';
        if (defined('RATEB_ERP_DB_NAME') && (string) RATEB_ERP_DB_NAME !== '') {
            $name = (string) RATEB_ERP_DB_NAME;
        } else {
            $fromEnv = getenv('RATEB_ERP_DB_NAME');
            if ($fromEnv !== false && $fromEnv !== '') {
                $name = (string) $fromEnv;
            }
        }
        if ($name === '') {
            $prefix = function_exists('rateb_db_prefix') ? rateb_db_prefix() : 'admin';
            $name = $prefix . '_rateb-erp';
        }
        if ($name === 'admin-rateb-erp') {
            $name = 'admin_rateb-erp';
        }
        if ($name === 'admin-rateb-erp') {
            $name = 'admin_rateb-erp';
        }
        return $name;
    }
}

if (!function_exists('rateb_erp_database_candidates')) {
    /** @return list<string> */
    function rateb_erp_database_candidates(): array
    {
        $primary = rateb_erp_database_name();
        $list = [$primary];
        // Hyphen vs underscore in the suffix only (rateb-erp ↔ rateb_erp)
        if (strpos($primary, 'rateb-erp') !== false) {
            $alt = str_replace('rateb-erp', 'rateb_erp', $primary);
            if (!in_array($alt, $list, true)) {
                $list[] = $alt;
            }
        } elseif (strpos($primary, 'rateb_erp') !== false) {
            $alt = str_replace('rateb_erp', 'rateb-erp', $primary);
            if (!in_array($alt, $list, true)) {
                $list[] = $alt;
            }
        }
        return array_values(array_unique($list));
    }
}

if (!defined('DB_HOST') && !defined('DB_NAME')) {
    $parentRoot = dirname(RATEB_ROOT, 1);
    $parentEnv = $parentRoot . '/config/env/load.php';
    if (is_file($parentEnv)) {
        require_once $parentEnv;
    }

    $envFile = $parentRoot . '/.env';
    if (is_file($envFile) && !getenv('DB_HOST')) {
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . trim($val, " \t\"'"));
            }
        }
    }
}

if (!function_exists('rateb_erp_db_credentials')) {
    /** @return array{0:string,1:string} */
    function rateb_erp_db_credentials(): array
    {
        if (PHP_SAPI !== 'cli' && function_exists('rateb_agency_erp_binding_for_request_host')) {
            $lookupFile = dirname(__DIR__, 2) . '/config/env/agency_lookup.php';
            if (is_file($lookupFile)) {
                require_once $lookupFile;
                $binding = rateb_agency_erp_binding_for_request_host();
                if (is_array($binding)) {
                    $user = trim((string) ($binding['user'] ?? ''));
                    $pass = (string) ($binding['pass'] ?? '');
                    if ($user !== '') {
                        return [$user, $pass];
                    }
                }
            }
        }

        $user = '';
        $pass = '';
        if (defined('RATEB_ERP_DB_USER') && (string) RATEB_ERP_DB_USER !== '') {
            $user = (string) RATEB_ERP_DB_USER;
        } else {
            $fromEnv = getenv('RATEB_ERP_DB_USER');
            if ($fromEnv !== false && $fromEnv !== '') {
                $user = (string) $fromEnv;
            }
        }
        if (defined('RATEB_ERP_DB_PASS') && (string) RATEB_ERP_DB_PASS !== '') {
            $pass = (string) RATEB_ERP_DB_PASS;
        } else {
            $fromEnv = getenv('RATEB_ERP_DB_PASS');
            if ($fromEnv !== false && $fromEnv !== '') {
                $pass = (string) $fromEnv;
            }
        }
        if ($user === '' && defined('DB_USER')) {
            $user = (string) DB_USER;
        }
        if ($pass === '' && defined('DB_PASS')) {
            $pass = (string) DB_PASS;
        }
        if ($user === '') {
            $u = getenv('CONTROL_DB_USER') ?: getenv('DB_USER');
            $user = ($u !== false && $u !== '') ? (string) $u : 'root';
        }
        if ($pass === '') {
            $p = getenv('CONTROL_DB_PASS');
            if ($p === false) {
                $p = getenv('DB_PASS');
            }
            $pass = $p !== false ? (string) $p : '';
        }
        return [$user, $pass];
    }
}

if (!defined('RATEB_DB_HOST')) {
    [$erpUser, $erpPass] = rateb_erp_db_credentials();
    if (defined('RATEB_ERP_DB_HOST') && (string) RATEB_ERP_DB_HOST !== '') {
        define('RATEB_DB_HOST', (string) RATEB_ERP_DB_HOST);
        define('RATEB_DB_PORT', defined('DB_PORT') ? (int) DB_PORT : 3306);
        define('RATEB_DB_USER', $erpUser);
        define('RATEB_DB_PASS', $erpPass);
    } elseif (defined('DB_HOST')) {
        define('RATEB_DB_HOST', (string) DB_HOST);
        define('RATEB_DB_PORT', defined('DB_PORT') ? (int) DB_PORT : 3306);
        define('RATEB_DB_USER', $erpUser);
        define('RATEB_DB_PASS', $erpPass);
    } else {
        $dbHost = getenv('CONTROL_DB_HOST') ?: getenv('DB_HOST');
        $dbPort = getenv('CONTROL_DB_PORT') ?: getenv('DB_PORT');
        define('RATEB_DB_HOST', $dbHost !== false && $dbHost !== '' ? (string) $dbHost : '127.0.0.1');
        define('RATEB_DB_PORT', (int) ($dbPort !== false && $dbPort !== '' ? $dbPort : 3306));
        define('RATEB_DB_USER', $erpUser);
        define('RATEB_DB_PASS', $erpPass);
    }
    define('RATEB_DB_NAME', rateb_erp_database_name());
}

if (!defined('RATEB_ERP_DB_NAME')) {
    define('RATEB_ERP_DB_NAME', RATEB_DB_NAME);
}

if (!function_exists('rateb_platform_erp_database_name')) {
    /**
     * Main SaaS ERP database on rateb.sa (admin_rateb-erp).
     * NEVER use agency RATEB_ERP_DB_NAME — that is the local agency DB and breaks cross-DB sync.
     */
    function rateb_platform_erp_database_name(): string
    {
        $directadmin = dirname(__DIR__, 2) . '/config/env/directadmin_db.php';
        if (is_file($directadmin)) {
            require_once $directadmin;
        }
        $explicit = getenv('RATEB_PLATFORM_ERP_DB_NAME');
        if ($explicit !== false && $explicit !== '') {
            $name = (string) $explicit;
        } elseif (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            // Agency host: ignore RATEB_ERP_DB_NAME (local agency DB).
            $name = function_exists('rateb_db_prefix') ? rateb_db_prefix() . '_rateb-erp' : 'admin_rateb-erp';
        } else {
            $fromEnv = getenv('RATEB_ERP_DB_NAME');
            if ($fromEnv !== false && $fromEnv !== '') {
                $name = (string) $fromEnv;
            } else {
                $name = function_exists('rateb_db_prefix') ? rateb_db_prefix() . '_rateb-erp' : 'admin_rateb-erp';
            }
        }
        if ($name === 'admin-rateb-erp') {
            $name = 'admin_rateb-erp';
        }

        return $name;
    }
}

if (!function_exists('rateb_platform_erp_db_credentials')) {
    /**
     * Credentials that can reach the platform ERP DB from an agency host.
     * Never use the per-agency MySQL binding (often locked to the agency schema only).
     *
     * @return array{0:string,1:string}
     */
    function rateb_platform_erp_db_credentials(): array
    {
        $user = '';
        $pass = '';
        foreach (['RATEB_PLATFORM_ERP_DB_USER', 'CONTROL_DB_USER', 'DB_USER'] as $key) {
            $v = getenv($key);
            if ($v !== false && $v !== '') {
                $user = (string) $v;
                break;
            }
        }
        foreach (['RATEB_PLATFORM_ERP_DB_PASS', 'CONTROL_DB_PASS', 'DB_PASS'] as $key) {
            $v = getenv($key);
            if ($v !== false) {
                $pass = (string) $v;
                break;
            }
        }
        if ($user === '' && defined('DB_USER')) {
            $user = (string) DB_USER;
        }
        if ($pass === '' && defined('DB_PASS')) {
            $pass = (string) DB_PASS;
        }
        if ($user === '') {
            $user = function_exists('rateb_default_mysql_user') ? rateb_default_mysql_user() : 'admin_rateb';
        }

        return [$user, $pass];
    }
}

if (!function_exists('rateb_apply_agency_erp_request_binding')) {
    function rateb_apply_agency_erp_request_binding(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        $lookupFile = dirname(__DIR__, 2) . '/config/env/agency_lookup.php';
        if (!is_file($lookupFile)) {
            return;
        }
        require_once $lookupFile;
        $binding = rateb_agency_erp_binding_for_request_host();
        if ($binding === null || trim((string) ($binding['db'] ?? '')) === '') {
            $host = rateb_normalize_http_host((string) ($_SERVER['HTTP_HOST'] ?? ''));
            if ($host !== '' && function_exists('rateb_lookup_agency_erp_by_host')) {
                $row = rateb_lookup_agency_erp_by_host($host);
                if (is_array($row) && function_exists('rateb_agency_erp_binding_for_host')) {
                    $binding = rateb_agency_erp_binding_for_host($host);
                }
            }
        }
        if ($binding === null || trim((string) ($binding['db'] ?? '')) === '') {
            $binding = null;
            if (defined('RATEB_ERP_AGENCY_RESOLVED') && RATEB_ERP_AGENCY_RESOLVED
                && defined('RATEB_ERP_DB_NAME') && trim((string) RATEB_ERP_DB_NAME) !== '') {
                $binding = [
                    'host' => defined('RATEB_ERP_DB_HOST') && (string) RATEB_ERP_DB_HOST !== ''
                        ? (string) RATEB_ERP_DB_HOST
                        : (defined('RATEB_DB_HOST') ? (string) RATEB_DB_HOST : '127.0.0.1'),
                    'port' => defined('RATEB_DB_PORT') ? (int) RATEB_DB_PORT : 3306,
                    'user' => defined('RATEB_ERP_DB_USER') && (string) RATEB_ERP_DB_USER !== ''
                        ? (string) RATEB_ERP_DB_USER
                        : (defined('RATEB_DB_USER') ? (string) RATEB_DB_USER : 'root'),
                    'pass' => defined('RATEB_ERP_DB_PASS')
                        ? (string) RATEB_ERP_DB_PASS
                        : (defined('RATEB_DB_PASS') ? (string) RATEB_DB_PASS : ''),
                    'db' => (string) RATEB_ERP_DB_NAME,
                    'agency_id' => defined('RATEB_ERP_AGENCY_ID') ? (int) RATEB_ERP_AGENCY_ID : 0,
                ];
            }
        }
        if ($binding === null || trim((string) ($binding['db'] ?? '')) === '') {
            return;
        }
        if (!defined('RATEB_ERP_AGENCY_RESOLVED')) {
            define('RATEB_ERP_AGENCY_RESOLVED', true);
        }
        if (!defined('RATEB_ERP_DEPLOYMENT_MODE')) {
            define('RATEB_ERP_DEPLOYMENT_MODE', 'dedicated');
        }
        if (class_exists(\Rateb\App\Core\Database::class)) {
            \Rateb\App\Core\Database::useConnectionOverride($binding);
        }
        if (function_exists('rateb_resolve_ops_company_id')) {
            rateb_resolve_ops_company_id();
        }
    }
}
