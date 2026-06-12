<?php
declare(strict_types=1);

if (!function_exists('rateb_erp_database_name')) {
    function rateb_erp_database_name(): string
    {
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
            $name = 'outratib_rateb-erp';
        }
        // Common typo — cPanel prefix must stay underscore: outratib_rateb-erp
        if ($name === 'outratib-rateb-erp') {
            $name = 'outratib_rateb-erp';
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
    if (!defined('RATIB_ENV_NO_SESSION')) {
        define('RATIB_ENV_NO_SESSION', true);
    }
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
    if (defined('DB_HOST')) {
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
