<?php
/**
 * Emergency fix for control panel HTTP 500 on PHP 7.4.
 *
 * 1) Upload this file to: public_html/control-panel/fix-500.php (cPanel File Manager)
 * 2) Open: https://rateb.sa/control-panel/fix-500.php?run=1&key=rateb-deploy-sync-2026
 * 3) DELETE this file after success.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$key = (string) ($_GET['key'] ?? '');
if (((string) ($_GET['run'] ?? '')) !== '1' || !hash_equals('rateb-deploy-sync-2026', $key)) {
    http_response_code(403);
    echo "Forbidden. Open:\n";
    echo "https://rateb.sa/control-panel/fix-500.php?run=1&key=rateb-deploy-sync-2026\n";
    exit;
}

$root = dirname(__DIR__);
$cfgPath = __DIR__ . '/includes/config.php';
$compatPath = $root . '/includes/rateb-php74-compat.php';
$patchPath = $root . '/includes/rateb_html_global_ai_patch.php';

echo "=== Control panel 500 fix ===\n";
echo 'php=' . PHP_VERSION . "\n";
echo 'config=' . $cfgPath . "\n\n";

if (!is_file($cfgPath)) {
    echo "FAIL: config.php not found\n";
    exit(1);
}

$cfg = (string) file_get_contents($cfgPath);
$before = $cfg;

// 1) Ensure PHP 7.4 polyfill file exists (from GitHub if missing).
if (!is_file($compatPath) && function_exists('curl_init')) {
    $url = 'https://raw.githubusercontent.com/ratebstar/rateb-pro/main/includes/rateb-php74-compat.php';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200 && is_string($body) && $body !== '' && strpos($body, 'str_contains') !== false) {
        $dir = dirname($compatPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (@file_put_contents($compatPath, $body) !== false) {
            echo "OK wrote includes/rateb-php74-compat.php\n";
        } else {
            echo "WARN could not write compat file (permissions)\n";
        }
    }
}

// 2) Patch HTML patch file to self-load compat (PHP 7.4).
if (is_file($patchPath)) {
    $patch = (string) file_get_contents($patchPath);
    if (strpos($patch, 'rateb-php74-compat') === false && strpos($patch, "function_exists('str_contains')") === false) {
        $insert = "<?php\nif (!function_exists('str_contains')) {\n    require_once __DIR__ . '/rateb-php74-compat.php';\n}\n\n";
        $patch = preg_replace('/^<\?php\s*/', $insert, $patch, 1);
        if (@file_put_contents($patchPath, $patch) !== false) {
            echo "OK patched includes/rateb_html_global_ai_patch.php\n";
        } else {
            echo "WARN could not patch HTML patch file\n";
        }
    } else {
        echo "OK HTML patch already safe\n";
    }
}

// 3) Fix control-panel/includes/config.php — remove Global AI patch load; add early compat.
$oldBlock = '$ratebHtmlPatch = dirname(__DIR__, 2) . \'/includes/rateb_html_global_ai_patch.php\';' . "\n"
    . 'if (is_file($ratebHtmlPatch)) {' . "\n"
    . '    require_once $ratebHtmlPatch;' . "\n"
    . '}';
if (strpos($cfg, $oldBlock) !== false) {
    $cfg = str_replace($oldBlock, "// Global AI HTML patch removed (PHP 7.4)\n", $cfg);
    echo "OK removed HTML patch block from config.php\n";
} elseif (strpos($cfg, 'rateb_html_global_ai_patch') !== false) {
    $cfg = preg_replace(
        '/\$ratebHtmlPatch\s*=\s*dirname\(__DIR__,\s*2\)[\s\S]*?require_once\s+\$ratebHtmlPatch;\s*\}/',
        "// Global AI HTML patch removed (PHP 7.4)\n",
        $cfg,
        1
    ) ?? $cfg;
    echo "OK removed HTML patch block (regex) from config.php\n";
}

if (strpos($cfg, 'rateb-php74-compat') === false) {
    $needle = "if (defined('CONTROL_CONFIG_LOADED')) {\n    return;\n}\n\n";
    $insert = "if (defined('CONTROL_CONFIG_LOADED')) {\n    return;\n}\n\n"
        . "\$ratebCompatEarly = dirname(__DIR__, 2) . '/includes/rateb-php74-compat.php';\n"
        . "if (is_file(\$ratebCompatEarly)) {\n    require_once \$ratebCompatEarly;\n}\n\n";
    if (strpos($cfg, $needle) !== false) {
        $cfg = str_replace($needle, $insert, $cfg);
        echo "OK inserted early compat require in config.php\n";
    }
}

if ($cfg !== $before) {
    if (@file_put_contents($cfgPath, $cfg) !== false) {
        echo "OK updated control-panel/includes/config.php\n";
    } else {
        echo "FAIL could not write config.php — use File Manager:\n";
        echo "  - Delete lines that load rateb_html_global_ai_patch.php\n";
        echo "  - Or paste config.php from GitHub main branch\n";
        exit(1);
    }
} else {
    echo "config.php already looks patched\n";
}

// 4) Smoke test
echo "\n--- Smoke test ---\n";
try {
    require_once $cfgPath;
    echo "config load: OK\n";
    echo 'control_db=' . ((isset($GLOBALS['control_conn']) && $GLOBALS['control_conn'] instanceof mysqli) ? 'yes' : 'no') . "\n";
} catch (Throwable $e) {
    echo 'config load: FAIL ' . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone. Test login:\n";
echo "https://rateb.sa/control-panel/pages/login.php\n";
echo "Then agencies:\n";
echo "https://rateb.sa/control-panel/pages/control/agencies.php?control=1\n";
echo "\nDELETE fix-500.php from the server now.\n";
