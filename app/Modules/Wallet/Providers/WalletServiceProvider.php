<?php
/**
 * EN: Handles application behavior in `app/Modules/Wallet/Providers/WalletServiceProvider.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Wallet/Providers/WalletServiceProvider.php`.
 */

declare(strict_types=1);

namespace App\Modules\Wallet\Providers;

use App\Modules\Wallet\Repositories\WalletRepository;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Support\ServiceProvider;

final class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WalletRepository::class);
        $this->app->singleton(WalletService::class);
    }

    public function boot(): void
    {
        //
    }
}
