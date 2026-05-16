<?php
declare(strict_types=1);

/**
 * cPanel Version Control deployment — copy git checkout to live docroots.
 * Invoked from .cpanel.yml: /usr/local/bin/php scripts/cpanel-deploy-sync.php
 */
$root = dirname(__DIR__);
chdir($root);

$markerFile = $root . '/public/ratib-build.txt';
$marker = is_file($markerFile) ? trim((string) file_get_contents($markerFile)) : 'unknown';
$stamp = 'deploy-' . gmdate('Ymd\THis\Z') . '-' . $marker;
$home = getenv('HOME') ?: '';
$user = getenv('USER') ?: getenv('USERNAME') ?: '';
$logFile = ($home !== '' ? $home : $root) . '/.ratib-deploy-log';

$log = static function (string $msg) use ($logFile): void {
    $line = gmdate('Y-m-d\TH:i:s\Z') . ' ' . $msg . PHP_EOL;
    @file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
};

$log("php-sync start bundle=about-enterprise-20260516-v10 marker={$marker} root={$root} user={$user}");

$targets = [];

$addTarget = static function (string $path) use (&$targets): void {
    $path = rtrim($path, "/\\");
    if ($path === '' || !is_dir($path)) {
        return;
    }
    if (!in_array($path, $targets, true)) {
        $targets[] = $path;
    }
};

$userdataFiles = [
    "/var/cpanel/userdata/{$user}/out.ratib.sa",
    "/var/cpanel/userdata/{$user}/out.ratib.sa_SSL",
    '/var/cpanel/userdata/outratib/out.ratib.sa',
    '/var/cpanel/userdata/outratib/out.ratib.sa_SSL',
];
foreach ($userdataFiles as $ud) {
    if (!is_file($ud)) {
        continue;
    }
    $lines = file($ud, FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $line) {
        if (preg_match('/^documentroot:\s*(.+)$/i', $line, $m)) {
            $dr = trim($m[1]);
            $addTarget($dr);
            $log("userdata {$ud} -> {$dr}");
        }
    }
}

$listFile = $root . '/config/cpanel-deploy-targets.txt';
if (is_file($listFile)) {
    foreach (file($listFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim(explode('#', $line, 2)[0]);
        $addTarget($line);
    }
}

$repoRoot = getenv('CPANEL_REPO_ROOT') ?: '';
foreach ([
    '/home/outratib/public_html',
    '/home/outratib/repositories/ratib-pro',
    '/home/outratib/domains/out.ratib.sa/public_html',
    '/home/outratib/out.ratib.sa/public_html',
    '/home/outratib/out.ratib.sa',
    $repoRoot,
    $home !== '' ? "{$home}/public_html" : '',
    $home !== '' ? "{$home}/out.ratib.sa/public_html" : '',
    $home !== '' ? "{$home}/domains/out.ratib.sa/public_html" : '',
] as $path) {
    $addTarget($path);
}

if ($targets === []) {
    $log('ERROR no deploy targets found');
    exit(1);
}

$copyTree = static function (string $src, string $dest) use ($log): void {
  $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
      RecursiveIteratorIterator::SELF_FIRST
  );
  foreach ($iterator as $item) {
      $rel = substr($item->getPathname(), strlen($src) + 1);
      if ($rel === '.git' || str_starts_with($rel, '.git' . DIRECTORY_SEPARATOR)) {
          continue;
      }
      $target = $dest . DIRECTORY_SEPARATOR . $rel;
      if ($item->isDir()) {
          if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
              throw new RuntimeException("mkdir failed: {$target}");
          }
      } else {
          $parent = dirname($target);
          if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
              throw new RuntimeException("mkdir failed: {$parent}");
          }
          if (!@copy($item->getPathname(), $target)) {
              throw new RuntimeException("copy failed: {$item->getPathname()} -> {$target}");
          }
      }
  }
};

$synced = 0;
foreach ($targets as $target) {
    if (realpath($target) === realpath($root)) {
        $log("skip self {$target}");
        continue;
    }
    $log("copy -> {$target}");
    try {
        $copyTree($root, $target);
        @file_put_contents($target . '/.ratib-deploy-stamp', $stamp . PHP_EOL);
        $about = is_file($target . '/pages/about.php') ? 'yes' : 'no';
        $log("done target={$target} about={$about}");
        $synced++;
    } catch (Throwable $e) {
        $log("ERROR target={$target} " . $e->getMessage());
    }
}

$log("finished synced={$synced} targets");
exit($synced > 0 ? 0 : 1);
