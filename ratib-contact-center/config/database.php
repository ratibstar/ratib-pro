<?php
declare(strict_types=1);

if (!function_exists('rcc_database_name')) {
    function rcc_database_name(): string
    {
        if (defined('RATIB_CC_DB_NAME') && (string) RATIB_CC_DB_NAME !== '') {
            return (string) RATIB_CC_DB_NAME;
        }
        $fromEnv = getenv('RATIB_CC_DB_NAME');
        if ($fromEnv !== false && $fromEnv !== '') {
            return (string) $fromEnv;
        }
        return 'admin_call-center';
    }
}

if (!defined('RCC_DB_HOST')) {
    $host = getenv('RATIB_CC_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('RATIB_CC_DB_PORT') ?: getenv('DB_PORT') ?: 3306);
    $user = getenv('RATIB_CC_DB_USER') ?: getenv('DB_USER') ?: 'admin_call-center';
    $pass = getenv('RATIB_CC_DB_PASS');
    if ($pass === false) {
        $pass = getenv('RATEB_CC_DB_PASS');
    }
    if ($pass === false) {
        $pass = getenv('DB_PASS') ?: '';
    }

    define('RCC_DB_HOST', (string) $host);
    define('RCC_DB_PORT', $port);
    define('RCC_DB_USER', (string) $user);
    define('RCC_DB_PASS', (string) $pass);
    define('RCC_DB_NAME', rcc_database_name());
}
