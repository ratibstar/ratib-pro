<?php
declare(strict_types=1);

/**
 * Payment gateway routes — webhooks (public) and admin console.
 *
 * @var Rateb\App\Core\Router $router
 */

use Rateb\App\Controllers\Admin\PaymentGatewaysController;
use Rateb\App\Controllers\Api\PaymentWebhookController;

$router->post('/api/v1/payments/webhooks/moyasar', [PaymentWebhookController::class, 'moyasar']);

$pgMw = rateb_platform_oversight_mw('billing.manage');
$router->get('/admin/payment-gateways', [PaymentGatewaysController::class, 'index'], $pgMw);
$router->post('/admin/payment-gateways/save', [PaymentGatewaysController::class, 'save'], $pgMw);
$router->post('/admin/payment-gateways/health', [PaymentGatewaysController::class, 'healthCheck'], $pgMw);
$router->get('/admin/payment-gateways/transactions', [PaymentGatewaysController::class, 'transactions'], $pgMw);
$router->get('/admin/payment-gateways/failed', [PaymentGatewaysController::class, 'failed'], $pgMw);
$router->post('/admin/payment-gateways/refund', [PaymentGatewaysController::class, 'refund'], $pgMw);
$router->post('/admin/payment-gateways/retry', [PaymentGatewaysController::class, 'retry'], $pgMw);
