<?php
/**
 * One-time: fix .htaccess + web-readable permissions after a bad deploy (PHP 7.4).
 * https://rateb.sa/pages/ratib-fix-perms.php?run=1&key=ratib-deploy-sync-2026
 * DELETE this file after use.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$key = isset($_GET['key']) ? (string) $_GET['key'] : '';
if (!isset($_GET['run']) || (string) $_GET['run'] !== '1' || !hash_equals('ratib-deploy-sync-2026', $key)) {
    http_response_code(403);
    echo "Use: ?run=1&key=ratib-deploy-sync-2026\n";
    exit;
}

$root = dirname(__DIR__);
echo "ratib-fix-perms\n";
echo 'root=' . $root . "\n";
echo 'php=' . PHP_VERSION . "\n\n";

$fixedHt = 0;
$fixedDir = 0;
$fixedFile = 0;

$skipDirs = ['Designed', '.git', 'node_modules', 'vendor'];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $path = $item->getPathname();
    foreach ($skipDirs as $skip) {
        if (strpos($path, DIRECTORY_SEPARATOR . $skip . DIRECTORY_SEPARATOR) !== false) {
            continue 2;
        }
    }
    if ($item->isDir()) {
        if (@chmod($path, 0755)) {
            $fixedDir++;
        }
        continue;
    }
    $base = $item->getBasename();
    if ($base === '.htaccess') {
        if (@chmod($path, 0644)) {
            $fixedHt++;
            echo "htaccess 644 {$path}\n";
        }
        continue;
    }
    $ext = strtolower($item->getExtension());
    if (in_array($ext, ['php', 'css', 'js', 'svg', 'json', 'txt', 'html', 'ico', 'png', 'jpg', 'jpeg', 'webp', 'woff', 'woff2'], true)) {
        if (@chmod($path, 0644)) {
            $fixedFile++;
        }
    }
}

echo "\nSummary: htaccess={$fixedHt} dirs={$fixedDir} files={$fixedFile}\n";
echo "Test: https://" . ($_SERVER['HTTP_HOST'] ?? 'rateb.sa') . "/pages/home.php\n";
echo "Then: /pages/ratib-profile-deploy.php?deploy=1&key=ratib-deploy-sync-2026\n";
echo "DELETE pages/ratib-fix-perms.php when done.\n";
