<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/ledger.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/ledger.php`.
 */

declare(strict_types=1);

/**
 * Ledger account codes for standard chart of accounts.
 * Each agency must have these accounts seeded.
 *
 * 1100 Commission Receivable (asset)
 * 4100 Commission Revenue (revenue)
 */

return [
    'accounts' => [
        'cash' => '1000',
        'commission_receivable' => '1100',
        'commission_revenue' => '4100',
        'subscription_revenue' => '4200',
    ],
];
