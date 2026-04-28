<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/currencies.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/currencies.php`.
 */

declare(strict_types=1);

/**
 * Multi-currency configuration for Accounting SaaS
 * Prepared for ISO 4217 support and exchange rates
 */

return [
    'default' => env('DEFAULT_CURRENCY', 'SAR'),

    'supported' => [
        'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'ر.س', 'decimals' => 2],
        'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'decimals' => 2],
        'EUR' => ['name' => 'Euro', 'symbol' => '€', 'decimals' => 2],
        'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => '৳', 'decimals' => 2],
        'UGX' => ['name' => 'Ugandan Shilling', 'symbol' => 'USh', 'decimals' => 0],
        'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'decimals' => 2],
        'LKR' => ['name' => 'Sri Lankan Rupee', 'symbol' => 'Rs', 'decimals' => 2],
        'PHP' => ['name' => 'Philippine Peso', 'symbol' => '₱', 'decimals' => 2],
        'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'decimals' => 0],
        'ETB' => ['name' => 'Ethiopian Birr', 'symbol' => 'Br', 'decimals' => 2],
        'NGN' => ['name' => 'Nigerian Naira', 'symbol' => '₦', 'decimals' => 2],
        'RWF' => ['name' => 'Rwandan Franc', 'symbol' => 'FRw', 'decimals' => 0],
        'THB' => ['name' => 'Thai Baht', 'symbol' => '฿', 'decimals' => 2],
        'NPR' => ['name' => 'Nepalese Rupee', 'symbol' => '₨', 'decimals' => 2],
    ],
];
