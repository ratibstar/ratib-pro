<?php
declare(strict_types=1);

/**
 * Phase D.4 — Export support / diagnostics package (no secrets in name; redacts sync key values).
 * php bin/hybrid-zero-touch-export-support.php
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

$dir = $root . '/storage/branch';
$stamp = gmdate('YmdHis');
$outDir = $dir . '/support';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}
$pack = $outDir . '/ratib-support-' . $stamp;
mkdir($pack, 0775, true);

$copyIf = static function (string $src, string $dest) use ($pack): void {
    if (!is_file($src)) {
        return;
    }
    $target = $pack . '/' . $dest;
    $td = dirname($target);
    if (!is_dir($td)) {
        mkdir($td, 0775, true);
    }
    $body = (string) file_get_contents($src);
    $body = preg_replace('/^(RATEB_HYBRID_SYNC_KEY=).*$/m', '$1[REDACTED]', $body) ?? $body;
    file_put_contents($target, $body);
};

$copyIf($dir . '/status.json', 'status.json');
$copyIf($dir . '/appliance.env', 'appliance.env');
$copyIf($dir . '/serve.env', 'serve.env');
$copyIf($root . '/VERSION', 'VERSION');

// Run status + diagnostics into pack
$php = PHP_BINARY;
@exec(escapeshellarg($php) . ' ' . escapeshellarg($root . '/bin/hybrid-zero-touch-status.php') . ' 2>&1', $stOut);
file_put_contents($pack . '/status-snapshot.txt', implode("\n", $stOut) . "\n");
@exec(escapeshellarg($php) . ' -d extension=pdo_sqlite -d extension=sqlite3 ' . escapeshellarg($root . '/bin/hybrid-branch-diagnostics.php') . ' 2>&1', $dOut);
file_put_contents($pack . '/diagnostics.json', implode("\n", $dOut) . "\n");
@exec(escapeshellarg($php) . ' -d extension=pdo_sqlite -d extension=sqlite3 ' . escapeshellarg($root . '/bin/hybrid-branch-health.php') . ' --once 2>&1', $hOut);
file_put_contents($pack . '/health.json', implode("\n", $hOut) . "\n");

$zipPath = $outDir . '/ratib-support-' . $stamp . '.zip';
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pack, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($pack) + 1));
        }
        $zip->close();
    }
}

echo json_encode([
    'ok' => true,
    'folder' => $pack,
    'zip' => is_file($zipPath) ? $zipPath : null,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit(0);
