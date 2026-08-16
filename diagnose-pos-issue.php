<?php
/**
 * تشخيص مشاكل شاشة البيع
 * POS Screen Diagnostics
 */

header('Content-Type: application/json; charset=UTF-8');

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'extensions' => [],
    'database' => [],
    'pos_config' => [],
];

// 1. Check PHP Extensions
$diagnostics['extensions'] = [
    'mysqli' => [
        'loaded' => extension_loaded('mysqli'),
        'info' => extension_loaded('mysqli') ? 'تم تحميل المتداخل ✓' : 'لم يتم تحميل المتداخل ✗ (المشكلة الأساسية)',
    ],
    'pdo' => [
        'loaded' => extension_loaded('pdo'),
        'info' => extension_loaded('pdo') ? 'تم تحميل المتداخل ✓' : 'لم يتم تحميل المتداخل ✗',
    ],
    'pdo_mysql' => [
        'loaded' => extension_loaded('pdo_mysql'),
        'info' => extension_loaded('pdo_mysql') ? 'تم تحميل المتداخل ✓' : 'لم يتم تحميل المتداخل ✗',
    ],
];

// 2. Check Database Configuration
require_once __DIR__ . '/config/env/load.php';
require_once __DIR__ . '/includes/config.php';

$diagnostics['database'] = [
    'configured_host' => defined('DB_HOST') ? DB_HOST : 'غير معرّف',
    'configured_port' => defined('DB_PORT') ? DB_PORT : 'غير معرّف',
    'configured_database' => defined('DB_NAME') ? DB_NAME : 'غير معرّف',
    'configured_user' => defined('DB_USER') ? (DB_USER !== '' ? '✓ معرّف' : '✗ فارغ') : '✗ غير معرّف',
];

// 3. Try to connect
if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
    try {
        $dsn = 'mysql:host=' . (defined('DB_HOST') ? DB_HOST : 'localhost') . 
               ';dbname=' . (defined('DB_NAME') ? DB_NAME : 'test') . 
               ';charset=utf8mb4';
        $pdo = new PDO(
            $dsn,
            defined('DB_USER') ? DB_USER : 'root',
            defined('DB_PASS') ? DB_PASS : '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $diagnostics['database']['connection'] = 'نجح الاتصال بقاعدة البيانات ✓';
        $diagnostics['database']['status'] = 'ok';
        
        // Test POS tables
        $tables = ['rateb_pos_terminals', 'rateb_pos_shifts', 'rateb_pos_orders'];
        $diagnostics['database']['pos_tables'] = [];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                $exists = (bool)$stmt->fetch();
                $diagnostics['database']['pos_tables'][$table] = $exists ? 'موجود ✓' : 'غير موجود ✗';
            } catch (Exception $e) {
                $diagnostics['database']['pos_tables'][$table] = 'خطأ: ' . $e->getMessage();
            }
        }
    } catch (PDOException $e) {
        $diagnostics['database']['connection'] = 'فشل الاتصال ✗';
        $diagnostics['database']['error'] = $e->getMessage();
        $diagnostics['database']['status'] = 'error';
    }
} else {
    $diagnostics['database']['connection'] = 'لا يمكن الاتصال - PDO غير متاح';
    $diagnostics['database']['status'] = 'unavailable';
}

// 4. Check POS Module
$diagnostics['pos_config'] = [
    'module_exists' => is_dir(__DIR__ . '/rateb-erp/modules/pos'),
    'bootstrap_file' => is_file(__DIR__ . '/rateb-erp/modules/pos/PosModule.php'),
    'routes_file' => is_file(__DIR__ . '/rateb-erp/modules/pos/routes/pos.php'),
    'views_dir' => is_dir(__DIR__ . '/rateb-erp/modules/pos/views'),
];

// Summary
$critical_issues = [];
if (!extension_loaded('mysqli')) {
    $critical_issues[] = 'MySQLi extension not loaded - This is the main issue blocking POS';
}
if (!extension_loaded('pdo')) {
    $critical_issues[] = 'PDO extension not loaded - Database connection impossible';
}
if (!extension_loaded('pdo_mysql')) {
    $critical_issues[] = 'PDO MySQL driver not loaded - Cannot connect to MySQL via PDO';
}

$diagnostics['summary'] = [
    'php_version_ok' => PHP_VERSION_ID >= 80200,
    'php_version_required' => '8.2+',
    'critical_issues' => $critical_issues,
    'total_issues' => count($critical_issues),
];

echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
