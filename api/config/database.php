<?php
/**
 * EN: Handles API endpoint/business logic in `api/config/database.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/config/database.php`.
 */
/**
 * Tenant-aware DB config for API consumers that still expect an array return.
 * Uses the same host/agency resolver as the main app so every agency gets its own DB.
 */

if (!defined('ENV_LOADED')) {
    require_once __DIR__ . '/../../config/env/load.php';
}

$host = defined('DB_HOST') ? (string) DB_HOST : ((getenv('DB_HOST') !== false) ? (string) getenv('DB_HOST') : 'localhost');
$dbName = defined('DB_NAME') ? (string) DB_NAME : ((getenv('DB_NAME') !== false) ? (string) getenv('DB_NAME') : '');
$user = defined('DB_USER') ? (string) DB_USER : ((getenv('DB_USER') !== false) ? (string) getenv('DB_USER') : '');
$pass = defined('DB_PASS') ? (string) DB_PASS : ((getenv('DB_PASS') !== false) ? (string) getenv('DB_PASS') : '');
$port = defined('DB_PORT') ? (int) DB_PORT : ((getenv('DB_PORT') !== false) ? (int) getenv('DB_PORT') : 3306);

return [
    'host' => $host,
    'port' => $port,
    'database' => $dbName,
    'username' => $user,
    'password' => $pass,
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => ''
];
