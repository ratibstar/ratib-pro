<?php
declare(strict_types=1);

/**
 * Root deploy probe — served directly when the file exists on disk (no front-controller).
 * URL: https://out.ratib.sa/ratib-deploy-probe.php
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$root = __DIR__;
$aboutPath = $root . '/pages/about.php';
$chromePath = $root . '/includes/ratib-home-public-chrome-top.php';
$buildPath = $root . '/public/ratib-build.txt';
$homePath = $root . '/pages/home.php';
$homeSample = is_file($homePath) ? (string) file_get_contents($homePath, false, null, 0, 12000) : '';
$chromeSample = is_file($chromePath) ? (string) file_get_contents($chromePath, false, null, 0, 12000) : '';

echo "ratib-deploy-probe-root\n";
echo 'host=' . ($_SERVER['HTTP_HOST'] ?? '') . "\n";
echo 'document_root=' . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n";
echo 'probe_root=' . $root . "\n";
echo 'script=' . ($_SERVER['SCRIPT_FILENAME'] ?? '') . "\n";
echo 'git_marker=' . (is_file($buildPath) ? trim((string) file_get_contents($buildPath)) : 'missing') . "\n";
echo 'stamp_file=' . (is_file($root . '/.ratib-deploy-stamp') ? trim((string) file_get_contents($root . '/.ratib-deploy-stamp')) : 'missing') . "\n";
echo 'about_php=' . (is_file($aboutPath) ? 'yes bytes=' . filesize($aboutPath) : 'no') . "\n";
echo 'home_open_about=' . (str_contains($homeSample, "=== 'about'") ? 'yes' : 'no') . "\n";
echo 'chrome_about_link=' . (str_contains($chromeSample, 'ratib-nav__link--about') ? 'yes' : 'no') . "\n";
