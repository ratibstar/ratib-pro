<?php
/**
 * EN: Handles public web entry/assets behavior in `public/index.php`.
 * AR: يدير سلوك المدخل العام للويب وملفات الواجهة في `public/index.php`.
 */

declare(strict_types=1);

/**
 * Front controller referenced by root .htaccess (RewriteRule → public/index.php).
 * Without this file, Apache returns 404 for any rewritten URL (including /Designed/).
 */

/* Surface fatal errors as plain text when Designed launcher fails (add &ratib_diag=1 for full notices). */
if (!empty($_GET['ratib_designed'])) {
    if (!empty($_GET['ratib_diag'])) {
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL);
    }
    register_shutdown_function(static function (): void {
        $e = error_get_last();
        if ($e === null) {
            return;
        }
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int) $e['type'], $fatal, true)) {
            return;
        }
        $msg = (string) ($e['message'] ?? '');
        $file = (string) ($e['file'] ?? '');
        $line = (int) ($e['line'] ?? 0);
        if (headers_sent()) {
            echo "\n\n[FATAL] {$msg}\n{$file}:{$line}\n";

            return;
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "FATAL (Designed / public front):\n{$msg}\n{$file}:{$line}\n";
    });
}

require_once dirname(__DIR__) . '/includes/designed_bootstrap.php';
ratib_serve_designed_if_requested();

if (!empty($_GET['ratib_designed'])) {
    if (isset($_GET['ping']) && (string) $_GET['ping'] === '1') {
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'designed-launcher route OK (via public/index.php), PHP ' . PHP_VERSION . "\n";
        exit;
    }
    ratib_run_designed_launcher();
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/' || $path === '') {
    require dirname(__DIR__) . '/index.php';
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Not found';
