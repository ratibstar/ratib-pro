<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/hr-currencies.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/hr-currencies.php`.
 */
/**
 * Control Panel HR — currencies without main Ratib settings DB.
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['control_logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currencies = [
    ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼', 'label' => 'SAR - Saudi Riyal'],
    ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'label' => 'USD - US Dollar'],
    ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'label' => 'EUR - Euro'],
    ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'label' => 'GBP - British Pound'],
    ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ', 'label' => 'AED - UAE Dirham'],
    ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك', 'label' => 'KWD - Kuwaiti Dinar'],
    ['code' => 'BHD', 'name' => 'Bahraini Dinar', 'symbol' => '.د.ب', 'label' => 'BHD - Bahraini Dinar'],
    ['code' => 'QAR', 'name' => 'Qatari Riyal', 'symbol' => '﷼', 'label' => 'QAR - Qatari Riyal'],
    ['code' => 'OMR', 'name' => 'Omani Rial', 'symbol' => '﷼', 'label' => 'OMR - Omani Rial'],
    ['code' => 'EGP', 'name' => 'Egyptian Pound', 'symbol' => 'E£', 'label' => 'EGP - Egyptian Pound'],
    ['code' => 'BDT', 'name' => 'Bangladeshi Taka', 'symbol' => '৳', 'label' => 'BDT - Bangladeshi Taka'],
    ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨', 'label' => 'PKR - Pakistani Rupee'],
    ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'label' => 'INR - Indian Rupee'],
];

echo json_encode(['success' => true, 'currencies' => $currencies]);
