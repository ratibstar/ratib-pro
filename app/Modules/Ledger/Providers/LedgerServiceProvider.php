<?php
/**
 * EN: Handles application behavior in `app/Modules/Ledger/Providers/LedgerServiceProvider.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Ledger/Providers/LedgerServiceProvider.php`.
 */

declare(strict_types=1);

namespace App\Modules\Ledger\Providers;

use App\Modules\Ledger\Repositories\LedgerRepository;
use App\Modules\Ledger\Services\LedgerService;
use Illuminate\Support\ServiceProvider;

final class LedgerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LedgerRepository::class);
        $this->app->singleton(LedgerService::class);
    }

    public function boot(): void
    {
        //
    }
}
