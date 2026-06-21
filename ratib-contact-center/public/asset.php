<?php
declare(strict_types=1);

/**
 * Serve RCC static assets with correct MIME (works even when .htaccess static rules fail).
 */
$file = (string) ($_GET['f'] ?? '');
$file = str_replace(['\\', "\0"], '/', $file);
$file = ltrim($file, '/');
if ($file === '' || strpos($file, '..') !== false) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Bad request');
}

$root = __DIR__ . '/assets/';
$path = realpath($root . $file);
$rootReal = realpath($root);
if ($path === false || $rootReal === false || strpos($path, $rootReal) !== 0 || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not found: ' . $file);
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = [
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'woff2' => 'font/woff2',
];
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($path);
