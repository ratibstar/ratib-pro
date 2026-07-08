<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli' && (empty($_SERVER['HTTP_HOST']) || (string) $_SERVER['HTTP_HOST'] === 'default')) {
    $_SERVER['HTTP_HOST'] = getenv('RATEB_DEPLOY_HOST') ?: 'rateb.sa';
}

$parentEnv = dirname(__DIR__, 2) . '/config/env/load.php';
if (is_file($parentEnv)) {
    require_once $parentEnv;
}

if (!function_exists('rateb_platform_catalog_database_name')) {
    function rateb_platform_catalog_database_name(): string
    {
        if (defined('RATEB_PLATFORM_CATALOG_DB_NAME') && (string) RATEB_PLATFORM_CATALOG_DB_NAME !== '') {
            return (string) RATEB_PLATFORM_CATALOG_DB_NAME;
        }
        $fromEnv = getenv('RATEB_PLATFORM_CATALOG_DB_NAME');
        if ($fromEnv !== false && $fromEnv !== '') {
            return (string) $fromEnv;
        }
        $directadmin = dirname(__DIR__, 2) . '/config/env/directadmin_db.php';
        if (is_file($directadmin)) {
            require_once $directadmin;
            if (function_exists('rateb_db_prefix')) {
                return rateb_db_prefix() . '_rateb_platform_catalog';
            }
        }

        return 'admin_rateb_platform_catalog';
    }
}

if (!function_exists('rateb_platform_catalog_db_credentials')) {
    /** @return array{0:string,1:string} */
    function rateb_platform_catalog_db_credentials(): array
    {
        $user = '';
        $pass = null;

        if (defined('RATEB_PLATFORM_CATALOG_DB_USER') && (string) RATEB_PLATFORM_CATALOG_DB_USER !== '') {
            $user = (string) RATEB_PLATFORM_CATALOG_DB_USER;
        } else {
            $fromEnv = getenv('RATEB_PLATFORM_CATALOG_DB_USER');
            if ($fromEnv !== false && $fromEnv !== '') {
                $user = (string) $fromEnv;
            }
        }

        if (defined('RATEB_PLATFORM_CATALOG_DB_PASS')) {
            $pass = (string) RATEB_PLATFORM_CATALOG_DB_PASS;
        } else {
            $fromEnv = getenv('RATEB_PLATFORM_CATALOG_DB_PASS');
            if ($fromEnv !== false) {
                $pass = (string) $fromEnv;
            }
        }

        if ($user === '') {
            if (defined('RATEB_ERP_DB_USER') && (string) RATEB_ERP_DB_USER !== '') {
                $user = (string) RATEB_ERP_DB_USER;
            } else {
                $fromEnv = getenv('RATEB_ERP_DB_USER');
                if ($fromEnv !== false && $fromEnv !== '') {
                    $user = (string) $fromEnv;
                }
            }
        }

        if ($pass === null) {
            if (defined('RATEB_ERP_DB_PASS')) {
                $pass = (string) RATEB_ERP_DB_PASS;
            } else {
                $fromEnv = getenv('RATEB_ERP_DB_PASS');
                $pass = $fromEnv !== false ? (string) $fromEnv : null;
            }
        }

        if ($user === '' && defined('DB_USER')) {
            $user = (string) DB_USER;
        }
        if ($pass === null && defined('DB_PASS')) {
            $pass = (string) DB_PASS;
        }

        if ($user === '') {
            $u = getenv('CONTROL_DB_USER') ?: getenv('DB_USER');
            $user = ($u !== false && $u !== '') ? (string) $u : 'root';
        }
        if ($pass === null) {
            $p = getenv('CONTROL_DB_PASS');
            if ($p === false) {
                $p = getenv('DB_PASS');
            }
            $pass = $p !== false ? (string) $p : '';
        }

        return [$user, (string) $pass];
    }
}

if (!defined('RATEB_PLATFORM_CATALOG_DB_HOST')) {
    [$catalogUser, $catalogPass] = rateb_platform_catalog_db_credentials();

    $host = getenv('RATEB_PLATFORM_CATALOG_DB_HOST');
    if ($host === false || $host === '') {
        if (defined('DB_HOST')) {
            $host = (string) DB_HOST;
        } else {
            $envHost = getenv('CONTROL_DB_HOST') ?: getenv('DB_HOST');
            $host = ($envHost !== false && $envHost !== '') ? (string) $envHost : '127.0.0.1';
        }
    }

    $port = getenv('RATEB_PLATFORM_CATALOG_DB_PORT');
    if ($port === false || $port === '') {
        if (defined('DB_PORT')) {
            $port = (string) DB_PORT;
        } else {
            $envPort = getenv('CONTROL_DB_PORT') ?: getenv('DB_PORT');
            $port = ($envPort !== false && $envPort !== '') ? (string) $envPort : '3306';
        }
    }

    $readHost = getenv('RATEB_PLATFORM_CATALOG_DB_READ_HOST');
    if ($readHost === false || $readHost === '') {
        $readHost = (string) $host;
    }

    define('RATEB_PLATFORM_CATALOG_DB_HOST', (string) $host);
    define('RATEB_PLATFORM_CATALOG_DB_READ_HOST', (string) $readHost);
    define('RATEB_PLATFORM_CATALOG_DB_PORT', (int) $port);
    define('RATEB_PLATFORM_CATALOG_DB_USER', $catalogUser);
    define('RATEB_PLATFORM_CATALOG_DB_PASS', $catalogPass);
    define('RATEB_PLATFORM_CATALOG_DB_NAME', rateb_platform_catalog_database_name());
}
