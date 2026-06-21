<?php
/**
 * Serve RCC static assets through Control Panel (avoids .htaccess / MIME issues).
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/contact-center-bridge.php';

if (empty($_SESSION['control_logged_in'])) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Forbidden');
}

$file = (string) ($_GET['f'] ?? '');
$file = str_replace(['\\', "\0"], '/', $file);
$file = ltrim($file, '/');
if ($file === '' || strpos($file, '..') !== false) {
    http_response_code(400);
    exit('Bad request');
}

$root = control_contact_center_root_path() . '/public/assets/';
$path = realpath($root . $file);
$rootReal = realpath($root);
if ($path === false || $rootReal === false || strpos($path, $rootReal) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('Not found');
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
readfile($path);
