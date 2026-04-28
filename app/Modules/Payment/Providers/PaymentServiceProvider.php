<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/Providers/PaymentServiceProvider.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/Providers/PaymentServiceProvider.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\Providers;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Controllers\PaymentController;
use App\Modules\Payment\Controllers\WebhookController;
use App\Modules\Payment\Repositories\PaymentRepository;
use App\Modules\Payment\Services\PaymentService;
use App\Modules\Payment\Services\TapPaymentService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, TapPaymentService::class);
        $this->app->singleton(PaymentRepository::class);
        $this->app->singleton(PaymentService::class);
    }

    public function boot(): void
    {
        Route::prefix('api')->group(function (): void {
            Route::post('payments/charges', [PaymentController::class, 'createCharge']);
            Route::get('payments/verify', [PaymentController::class, 'verify']);
            Route::post('payments/webhooks/tap', [WebhookController::class, 'tap']);
        });
    }
}
