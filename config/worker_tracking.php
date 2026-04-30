<?php
declare(strict_types=1);

if (!defined('DB_HOST')) {
    $legacyControlEnv = dirname(__DIR__) . '/control-panel/config/env.php';
    if (is_file($legacyControlEnv)) {
        require_once $legacyControlEnv;
    }
}

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: (defined('DB_HOST') ? (string) DB_HOST : '127.0.0.1'),
        'port' => (int) (getenv('DB_PORT') ?: (defined('DB_PORT') ? (string) DB_PORT : '3306')),
        'database' => getenv('DB_DATABASE')
            ?: (defined('RATIB_PRO_DB_NAME') && (string) RATIB_PRO_DB_NAME !== ''
                ? (string) RATIB_PRO_DB_NAME
                : (defined('DB_NAME') ? (string) DB_NAME : 'ratibprogram')),
        'username' => getenv('DB_USERNAME') ?: (defined('DB_USER') ? (string) DB_USER : 'root'),
        'password' => getenv('DB_PASSWORD') ?: (defined('DB_PASS') ? (string) DB_PASS : ''),
        'charset' => 'utf8mb4',
    ],
    'system_mode' => require __DIR__ . '/system_mode.php',
];
