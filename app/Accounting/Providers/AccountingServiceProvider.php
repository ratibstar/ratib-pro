<?php
declare(strict_types=1);

namespace App\Accounting\Providers;

use App\Accounting\Adapters\ControlPanelAccountingAdapter;
use App\Accounting\Adapters\LedgerAccountingAdapter;
use App\Accounting\Adapters\MainSiteAccountingAdapter;
use App\Accounting\Adapters\RatebErpAccountingAdapter;
use App\Accounting\Core\AccountingEventValidator;
use App\Accounting\Core\AccountingGateway;
use Illuminate\Support\ServiceProvider;

final class AccountingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AccountingEventValidator::class);

        $this->app->singleton(AccountingGateway::class, static function (): AccountingGateway {
            return new AccountingGateway([
                new RatebErpAccountingAdapter(),
                new MainSiteAccountingAdapter(),
                new ControlPanelAccountingAdapter(),
                new LedgerAccountingAdapter(),
            ]);
        });
    }

    public function boot(): void
    {
        $bootstrap = dirname(__DIR__) . '/Support/post_accounting_event.php';
        if (is_file($bootstrap)) {
            require_once $bootstrap;
        }
    }
}
