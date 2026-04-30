<?php
declare(strict_types=1);

use App\Core\Autoloader;
use App\Core\Database;

$root = dirname(__DIR__);
require_once $root . '/app/Core/Autoloader.php';
Autoloader::register($root . DIRECTORY_SEPARATOR . 'app');

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return $needle !== '' && strpos((string) $haystack, (string) $needle) !== false;
    }
}

final class TestSkippedException extends RuntimeException
{
}

function t_assert_true(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function t_assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message !== '' ? $message : 'Expected ' . var_export($expected, true) . ' got ' . var_export($actual, true));
    }
}

function t_skip(string $message): string
{
    throw new TestSkippedException($message);
}

function t_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $root = dirname(__DIR__);
    $configPath = $root . '/config/worker_tracking.php';
    if (!is_file($configPath)) {
        t_skip('worker_tracking config missing');
    }
    $config = require $configPath;
    try {
        $pdo = Database::connect($config['db'] ?? []);
        return $pdo;
    } catch (Throwable $e) {
        t_skip('database unavailable: ' . $e->getMessage());
    }
}
