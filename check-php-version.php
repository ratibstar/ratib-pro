<?php
/**
 * حل مشكلة عدم توافق إصدار PHP
 * PHP Version Compatibility Fix
 * 
 * المشكلة: الامتدادات مجمّعة لـ PHP 8.3 لكن PHP 8.4 مثبتاً
 * Solution: 
 * 1. تحديث XAMPP إلى إصدار متطابق
 * 2. أو تثبيت PHP 8.3 بدلاً من 8.4
 * 3. أو استخدام PDO بدلاً من MySQLi
 */

header('Content-Type: application/json; charset=UTF-8');

$result = [
    'problem' => 'PHP API Version Mismatch',
    'xampp_php_version' => phpversion(),
    'xampp_php_api' => PHP_VERSION_ID,
    'extensions_api' => '20220829 (PHP 8.3)',
    'current_api' => PHP_VERSION_ID >= 80400 ? '20240924 (PHP 8.4)' : 'Unknown',
    'solution' => [
        'option_1' => 'تحديث XAMPP إلى إصدار يتضمن PHP 8.4 مع الامتدادات المتطابقة',
        'option_2' => 'تنزيل إصدار XAMPP الذي يحتوي على PHP 8.3',
        'option_3' => 'تثبيت الامتدادات المترجمة لـ PHP 8.4 يدويًا من php.net',
    ],
    'current_status' => [
        'pdo' => extension_loaded('pdo') ? 'محمل ✓' : 'غير محمل ✗',
        'pdo_mysql' => extension_loaded('pdo_mysql') ? 'محمل ✓' : 'غير محمل ✗',
        'mysqli' => extension_loaded('mysqli') ? 'محمل ✓' : 'غير محمل ✗',
    ],
    'workaround' => [
        'use_pdo' => 'يمكن استخدام PDO للاتصال بقاعدة البيانات بدلاً من MySQLi',
        'pdo_available' => extension_loaded('pdo_mysql'),
    ]
];

// Test PDO Connection
if (extension_loaded('pdo_mysql')) {
    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=admin_bangladesh;charset=utf8mb4',
            'admin_rateb',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $result['pdo_test'] = 'اتصال PDO ناجح ✓';
        $result['database_connected'] = true;
    } catch (Exception $e) {
        $result['pdo_test'] = 'فشل اتصال PDO: ' . $e->getMessage();
        $result['database_connected'] = false;
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
