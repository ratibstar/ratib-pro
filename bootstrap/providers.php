<?php
/**
 * EN: Handles application behavior in `bootstrap/providers.php`.
 * AR: يدير سلوك جزء من التطبيق في `bootstrap/providers.php`.
 */

declare(strict_types=1);

return [
    App\Modules\Core\Providers\CoreServiceProvider::class,
    App\Modules\Agency\Providers\AgencyServiceProvider::class,
    App\Modules\Ledger\Providers\LedgerServiceProvider::class,
    App\Modules\Wallet\Providers\WalletServiceProvider::class,
    App\Modules\Commission\Providers\CommissionServiceProvider::class,
    App\Modules\Subscription\Providers\SubscriptionServiceProvider::class,
    App\Modules\Payment\Providers\PaymentServiceProvider::class,
    App\Modules\Settlement\Providers\SettlementServiceProvider::class,
    App\Modules\Reporting\Providers\ReportingServiceProvider::class,
    App\Modules\Admin\Providers\AdminServiceProvider::class,
];
