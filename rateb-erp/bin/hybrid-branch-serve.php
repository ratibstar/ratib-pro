<?php
declare(strict_types=1);

/**
 * Phase B — start local PHP web server for Branch appliance (SQLite).
 *
 * Loads storage/branch/serve.env written by hybrid-branch-install.php,
 * then runs: php -S host:port -t public public/index.php
 *
 * Usage:
 *   php -d extension=pdo_sqlite bin/hybrid-branch-serve.php
 *   php -d extension=pdo_sqlite bin/hybrid-branch-serve.php --port=8088
 */

$root = dirname(__DIR__);
$port = 8088;
$host = '127.0.0.1';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--port=')) {
        $port = (int) substr($arg, 7);
    }
    if (str_starts_with($arg, '--host=')) {
        $host = substr($arg, 7);
    }
}

$serveEnv = $root . '/storage/branch/serve.env';
if (!is_readable($serveEnv)) {
    fwrite(STDERR, "Missing {$serveEnv}\nRun: php -d extension=pdo_sqlite bin/hybrid-branch-install.php\n");
    exit(1);
}

$env = [];
foreach (file($serveEnv, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
    $k = trim($k);
    $v = trim($v);
    if ($k === '') {
        continue;
    }
    $env[$k] = $v;
    putenv($k . '=' . $v);
    $_ENV[$k] = $v;
}

if (($env['RATEB_RUNTIME'] ?? '') !== 'branch') {
    fwrite(STDERR, "serve.env must set RATEB_RUNTIME=branch\n");
    exit(1);
}

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "pdo_sqlite required\n");
    exit(1);
}

$docRoot = $root . '/public';
$router = $root . '/public/index.php';
echo "RATEB Branch server http://{$host}:{$port}/" . PHP_EOL;
echo "Runtime=branch SQLite=" . ($env['RATEB_SQLITE_PATH'] ?? '(default)') . PHP_EOL;
echo "Login: admin@branch.test / 123456 (or username: admin)" . PHP_EOL;
echo "Ctrl+C to stop" . PHP_EOL;

$cmd = [
    PHP_BINARY,
    '-d', 'extension=pdo_sqlite',
    '-d', 'extension=sqlite3',
    '-S', "{$host}:{$port}",
    '-t', $docRoot,
    $router,
];

// Child inherits current process env (serve.env already applied via putenv).
// Passing a partial env array breaks Windows sockets (SystemRoot/PATH missing).
$descriptors = [STDIN, STDOUT, STDERR];
$proc = proc_open($cmd, $descriptors, $pipes, $root, null);
if (!is_resource($proc)) {
    fwrite(STDERR, "Failed to start PHP built-in server\n");
    exit(1);
}
$exit = proc_close($proc);
exit($exit > 0 ? $exit : 0);
