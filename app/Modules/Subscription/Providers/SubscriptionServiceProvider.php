<?php
/**
 * EN: Handles application behavior in `app/Modules/Subscription/Providers/SubscriptionServiceProvider.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Subscription/Providers/SubscriptionServiceProvider.php`.
 */

declare(strict_types=1);

namespace App\Modules\Subscription\Providers;

use App\Modules\Subscription\Controllers\SubscriptionController;
use App\Modules\Subscription\Controllers\SubscriptionPlanController;
use App\Modules\Subscription\Repositories\SubscriptionRepository;
use App\Modules\Subscription\Services\SubscriptionService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class SubscriptionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SubscriptionRepository::class);
        $this->app->singleton(SubscriptionService::class);
    }

    public function boot(): void
    {
        Route::prefix('api')->group(function (): void {
            Route::get('subscription-plans', [SubscriptionPlanController::class, 'index']);
            Route::post('subscription-plans', [SubscriptionPlanController::class, 'store']);

            Route::get('subscriptions', [SubscriptionController::class, 'index']);
            Route::get('subscriptions/{id}', [SubscriptionController::class, 'show']);
            Route::post('subscriptions/{id}/activate', [SubscriptionController::class, 'activate']);
            Route::post('subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);
            Route::get('customers/{customerId}/subscriptions', [SubscriptionController::class, 'byCustomer']);
            Route::post('customers/{customer}/subscriptions', [SubscriptionController::class, 'subscribe']);
        });
    }
}
