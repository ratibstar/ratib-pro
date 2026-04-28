<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/commission.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/commission.php`.
 */

declare(strict_types=1);

/**
 * Commission configuration for Accounting SaaS
 * Rule: Agency commission = 10%
 */

return [
    'agency_rate' => (float) env('AGENCY_COMMISSION_RATE', 10.00),
];
