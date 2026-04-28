<?php
/**
 * EN: Handles application behavior in `app/Modules/Commission/Providers/CommissionServiceProvider.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Commission/Providers/CommissionServiceProvider.php`.
 */

declare(strict_types=1);

namespace App\Modules\Commission\Providers;

use App\Modules\Commission\Listeners\ProcessCommissionOnPaymentVerified;
use App\Modules\Commission\Repositories\CommissionRepository;
use App\Modules\Commission\Services\CommissionService;
use App\Modules\Payment\Events\PaymentVerified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class CommissionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommissionRepository::class);
        $this->app->singleton(CommissionService::class);
    }

    public function boot(): void
    {
        Event::listen(PaymentVerified::class, ProcessCommissionOnPaymentVerified::class);
    }
}
