<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/designed_bootstrap.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/designed_bootstrap.php`.
 */

declare(strict_types=1);

/**
 * Serve the Designed app when REQUEST_URI is under /Designed/…
 * Project root is one level above includes/.
 */
function ratib_serve_designed_if_requested(): void
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    if (!preg_match('#^/Designed#i', $path)) {
        return;
    }

    $root = dirname(__DIR__);
    $app = $root . DIRECTORY_SEPARATOR . 'Designed' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php';
    if (!is_file($app)) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Designed app is not installed.\n\nExpected:\n" . $app . "\n";
        exit;
    }

    $appResolved = realpath($app);
    if ($appResolved !== false) {
        $app = $appResolved;
    }

    $parts = parse_url($uri);
    $path = $parts['path'] ?? '/';
    $query = isset($parts['query']) && $parts['query'] !== '' ? $parts['query'] : null;

    if (preg_match('#^/Designed/public#i', $path)) {
        // already /Designed/public/…
    } elseif (preg_match('#^/Designed(?:/(.*))?$#i', $path, $m)) {
        $tail = isset($m[1]) && $m[1] !== '' ? '/' . $m[1] : '/';
        $path = '/Designed/public' . ($tail === '//' ? '/' : $tail);
    }

    $_SERVER['REQUEST_URI'] = $path . ($query !== null ? '?' . $query : '');
    $_SERVER['SCRIPT_NAME'] = '/Designed/public/index.php';
    $_SERVER['PHP_SELF'] = '/Designed/public/index.php';
    /* Do not set SCRIPT_FILENAME: some LiteSpeed/cPanel setups return HTTP 500 if it is rewritten here. */
    unset($_SERVER['PATH_INFO']);

    $prevCwd = getcwd();
    try {
        if (!@chdir(dirname($app))) {
            throw new RuntimeException('chdir failed to: ' . dirname($app));
        }
        require $app;
    } catch (Throwable $e) {
        if ($prevCwd !== false) {
            @chdir($prevCwd);
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Designed app failed to run.\n\n";
        echo $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
        exit;
    }
    exit;
}

/**
 * Run Designed from pages/designed-launcher.php — no URL rewriting required.
 * Use this when /Designed/ returns 404 (Apache/LiteSpeed rewrite not applied).
 */
function ratib_run_designed_launcher(): void
{
    $root = dirname(__DIR__);
    $app = $root . DIRECTORY_SEPARATOR . 'Designed' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php';
    if (!is_file($app)) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Designed app is not installed.\n\nExpected:\n" . $app . "\n";
        echo "\n(Linux is case-sensitive: the folder must be named exactly \"Designed\".)\n";
        exit;
    }

    $appResolved = realpath($app);
    if ($appResolved !== false) {
        $app = $appResolved;
    }

    $_SERVER['REQUEST_URI'] = '/Designed/public/';
    $_SERVER['SCRIPT_NAME'] = '/Designed/public/index.php';
    $_SERVER['PHP_SELF'] = '/Designed/public/index.php';
    unset($_SERVER['PATH_INFO']);

    $prevCwd = getcwd();
    try {
        if (!@chdir(dirname($app))) {
            throw new RuntimeException('chdir failed to: ' . dirname($app));
        }
        require $app;
    } catch (Throwable $e) {
        if ($prevCwd !== false) {
            @chdir($prevCwd);
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Designed app failed to run.\n\n";
        echo $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
        exit;
    }
    exit;
}
