<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/test-error.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/test-error.php`.
 */
/**
 * Diagnostic script - upload to server and visit:
 * https://out.ratib.sa/pages/test-error.php
 * or https://out.ratib.sa/pages/test-error.php
 * 
 * Delete this file after debugging.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: text/plain; charset=utf-8');

echo "Step 1: PHP OK\n";

try {
    require_once __DIR__ . '/../config/env/load.php';
    echo "Step 2: load.php OK\n";
    echo "  DB_NAME=" . (defined('DB_NAME') ? DB_NAME : 'NOT SET') . "\n";
    echo "  SITE_URL=" . (defined('SITE_URL') ? SITE_URL : 'NOT SET') . "\n";
    echo "  (Ratib Pro only)\n";
} catch (Throwable $e) {
    echo "Step 2 FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit;
}

try {
    require_once __DIR__ . '/../includes/config.php';
    echo "Step 3: config.php OK\n";
    echo "  conn=" . (isset($GLOBALS['conn']) && $GLOBALS['conn'] ? 'OK' : 'NULL') . "\n";
} catch (Throwable $e) {
    echo "Step 3 FAILED: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit;
}

echo "\nAll steps passed. Login page should work.\n";
