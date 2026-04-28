<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/payment.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/payment.php`.
 */

declare(strict_types=1);

return [
    'tap' => [
        'api_url' => env('TAP_API_URL', 'https://api.tap.company/v2/charges'),
        'secret_key' => env('TAP_SECRET_KEY', ''),
        'webhook_secret' => env('TAP_WEBHOOK_SECRET', ''),
        'min_amount' => (float) env('TAP_MIN_AMOUNT', 0.10),
        'max_amount' => (float) env('TAP_MAX_AMOUNT', 100000.00),
    ],
];
