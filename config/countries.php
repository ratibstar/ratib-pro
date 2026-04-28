<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/countries.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/countries.php`.
 */

declare(strict_types=1);

/**
 * Multi-country configuration for Accounting SaaS
 * Prepared for ISO 3166-1 alpha-2 support
 */

return [
    'default' => env('DEFAULT_COUNTRY', 'SA'),

    'supported' => [
        'SA' => ['name' => 'Saudi Arabia', 'code' => 'SA', 'currency' => 'SAR'],
        'BD' => ['name' => 'Bangladesh', 'code' => 'BD', 'currency' => 'BDT'],
        'UG' => ['name' => 'Uganda', 'code' => 'UG', 'currency' => 'UGX'],
        'KE' => ['name' => 'Kenya', 'code' => 'KE', 'currency' => 'KES'],
        'LK' => ['name' => 'Sri Lanka', 'code' => 'LK', 'currency' => 'LKR'],
        'PH' => ['name' => 'Philippines', 'code' => 'PH', 'currency' => 'PHP'],
        'ID' => ['name' => 'Indonesia', 'code' => 'ID', 'currency' => 'IDR'],
        'ET' => ['name' => 'Ethiopia', 'code' => 'ET', 'currency' => 'ETB'],
        'NG' => ['name' => 'Nigeria', 'code' => 'NG', 'currency' => 'NGN'],
        'RW' => ['name' => 'Rwanda', 'code' => 'RW', 'currency' => 'RWF'],
        'TH' => ['name' => 'Thailand', 'code' => 'TH', 'currency' => 'THB'],
        'NP' => ['name' => 'Nepal', 'code' => 'NP', 'currency' => 'NPR'],
    ],
];
