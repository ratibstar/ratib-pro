<?php
if (!function_exists('rateb_db_password')) {
    function rateb_db_password(): string
    {
        $env = getenv('RATEB_AI_DB_PASSWORD');
        if (is_string($env) && $env !== '') {
            return $env;
        }
        $path = getenv('RATEB_AI_DB_PASSWORD_FILE');
        if (!is_string($path) || $path === '' || !rateb_db_password_file_allowed($path)) {
            return '';
        }
        $secret = file_get_contents($path);
        return is_string($secret) ? rtrim($secret, "\r\n") : '';
    }
}

if (!function_exists('rateb_db_password_perms_private')) {
    function rateb_db_password_perms_private(int $perms): bool
    {
        return ($perms & 0077) === 0;
    }
}

if (!function_exists('rateb_db_password_file_allowed')) {
    function rateb_db_password_file_allowed(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }
        $perms = fileperms($path);
        return $perms !== false && rateb_db_password_perms_private($perms);
    }
}
return [
    'host' => 'localhost',
    'database' => 'admin_rateb_ai',
    'username' => 'admin_rateb_ai',
    'password' => rateb_db_password(),
    'charset' => 'utf8mb4',
];
