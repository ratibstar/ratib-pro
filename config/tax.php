<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/tax.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/tax.php`.
 */

declare(strict_types=1);

/**
 * Tax configuration - single source for tax rate
 */

return [
    'rate' => (float) env('TAX_RATE', 15.00),
];
