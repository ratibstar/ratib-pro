<?php
/**
 * EN: Handles application behavior in `designed-status.php`.
 * AR: يدير سلوك جزء من التطبيق في `designed-status.php`.
 */
/**
 * Full Designed / URL diagnostic. Upload to document root (same folder as root .htaccess).
 * Open: https://yourdomain/designed-status.php
 */
header('Content-Type: text/plain; charset=UTF-8');

$here = __DIR__;
$doc = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/') : '';
$host = $_SERVER['HTTP_HOST'] ?? '(unknown)';
$sw = $_SERVER['SERVER_SOFTWARE'] ?? '(unknown)';

echo "=== Designed / ratib.sa diagnostic ===\n\n";
echo "HTTP_HOST: {$host}\n";
echo "SERVER_SOFTWARE: {$sw}\n";
echo "DOCUMENT_ROOT: " . ($doc !== '' ? $doc : '(empty)') . "\n";
echo "This file (__DIR__): {$here}\n";

$rewrite = false;
if (function_exists('apache_get_modules')) {
    $rewrite = in_array('mod_rewrite', apache_get_modules(), true);
    echo "mod_rewrite: " . ($rewrite ? 'loaded' : 'NOT loaded') . "\n";
} else {
    echo "mod_rewrite: (cannot detect — not Apache or apache_get_modules disabled)\n";
}

$checks = [
    'Root .htaccess (must exist for /Designed/ rewrites)' => $here . DIRECTORY_SEPARATOR . '.htaccess',
    'public/index.php (rewrite target)' => $here . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php',
    'includes/designed_bootstrap.php' => $here . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'designed_bootstrap.php',
    'pages/designed-launcher.php (works WITHOUT /Designed/ rewrite)' => $here . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'designed-launcher.php',
    'Designed/public/index.php (app)' => $here . DIRECTORY_SEPARATOR . 'Designed' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php',
];

echo "\n--- Files next to this script ---\n";
foreach ($checks as $label => $abs) {
    $ok = is_file($abs);
    echo ($ok ? '[OK] ' : '[MISSING] ') . $label . "\n  " . $abs . "\n";
}

if ($doc !== '') {
    $underDoc = $doc . '/Designed/public/index.php';
    echo "\n--- Under DOCUMENT_ROOT only ---\n";
    echo (is_file($underDoc) ? '[OK] ' : '[MISSING] ') . "Designed/public/index.php\n  {$underDoc}\n";
}

echo "\n=== What your 404 on /Designed/ means ===\n";
echo "Apache text 'Not Found' = the server never ran your PHP for that URL.\n";
echo "Common causes:\n";
echo "  1) These files are on your PC but NOT uploaded to the account that serves {$host}\n";
echo "  2) This domain's document root is NOT this folder (addon domain → another path)\n";
echo "  3) .htaccess is ignored (wrong directory) or mod_rewrite off\n";
echo "  4) public/index.php missing → rewrite target 404\n\n";

echo "=== What to do ===\n";
echo "A) Stop testing https://{$host}/Designed/ until (public/index.php + .htaccess + Designed/) are on the server.\n";
echo "B) Test instead: https://{$host}/pages/designed-launcher.php — needs no /Designed/ rewrite.\n";
echo "C) If this page (designed-status.php) is also 404, you uploaded to the WRONG folder or wrong hosting.\n";
