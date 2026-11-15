<?php
declare(strict_types=1);

$local = [];
$localFile = __DIR__ . '/database.local.php';
if (is_file($localFile)) {
    $loaded = require $localFile;
    if (is_array($loaded)) {
        $local = $loaded;
    }
}

return [
    'host' => (string) ($local['host'] ?? getenv('RATEB_AI_DB_HOST') ?: 'localhost'),
    'database' => (string) ($local['database'] ?? getenv('RATEB_AI_DB_NAME') ?: 'admin_rateb_ai'),
    'username' => (string) ($local['username'] ?? getenv('RATEB_AI_DB_USER') ?: 'admin_rateb_ai'),
    'password' => (string) (getenv('RATEB_AI_DB_PASSWORD') ?: ($local['password'] ?? '')),
    'charset' => 'utf8mb4',
];
