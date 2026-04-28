<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/test-config.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/test-config.php`.
 */
/**
 * Diagnostic: Find the 500 error source
 * Visit: https://out.ratib.sa/pages/test-config.php
 * DELETE this file after fixing.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "1. Starting...<br>";

try {
    require_once __DIR__ . '/../config/env/load.php';
    echo "2. load.php OK<br>";
} catch (Throwable $e) {
    die("ERROR at load.php: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}

try {
    require_once __DIR__ . '/../includes/config.php';
    echo "3. config.php OK<br>";
} catch (Throwable $e) {
    die("ERROR at config.php: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}

echo "4. DB_HOST=" . (defined('DB_HOST') ? DB_HOST : 'NOT SET') . "<br>";
echo "5. conn=" . (isset($GLOBALS['conn']) && $GLOBALS['conn'] ? 'OK' : 'NULL') . "<br>";

echo "<br><strong>Config OK.</strong> If login.php still gives 500, upload ALL reverted files to server.";
