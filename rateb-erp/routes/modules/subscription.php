<?php
declare(strict_types=1);

/**
 * Tenant subscription renewal / status routes (Phase 7B).
 * Platform subscription engine admin console (Phase 9).
 * No payment processing / auto-billing.
 */

use Rateb\App\Core\Middleware\ErpAuthMiddleware;
use Rateb\App\Subscription\Admin\SubscriptionAdminController;
use Rateb\App\Subscription\SubscriptionEnforcementMiddleware;
use Rateb\App\Subscription\SubscriptionRenewalController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

$subMw = [
    ErpAuthMiddleware::class,
    SubscriptionEnforcementMiddleware::class,
];

$router->get('/admin/subscription/renew', [SubscriptionRenewalController::class, 'renew'], $subMw);
$router->get('/admin/subscription/invoices', [SubscriptionRenewalController::class, 'invoices'], $subMw);
$router->get('/admin/subscription/payment-status', [SubscriptionRenewalController::class, 'paymentStatus'], $subMw);
$router->get('/admin/subscription/support', [SubscriptionRenewalController::class, 'support'], $subMw);
$router->get('/admin/support', [SubscriptionRenewalController::class, 'support'], $subMw);

// Phase 9 — ops console (platform host + RBAC). Distinct from billing /admin/subscriptions.
$viewMw = rateb_platform_oversight_mw('subscriptions.view');
$manageMw = rateb_platform_oversight_mw('subscriptions.manage');

$router->get('/admin/subscription-engine', [SubscriptionAdminController::class, 'index'], $viewMw);
$router->post('/admin/subscription-engine/create', [SubscriptionAdminController::class, 'create'], $manageMw);
$router->get('/admin/subscription-engine/{id}', [SubscriptionAdminController::class, 'show'], $viewMw);
$router->post('/admin/subscription-engine/{id}/renew', [SubscriptionAdminController::class, 'renew'], $manageMw);
$router->post('/admin/subscription-engine/{id}/extend', [SubscriptionAdminController::class, 'extend'], $manageMw);
$router->post('/admin/subscription-engine/{id}/push-agency', [SubscriptionAdminController::class, 'pushAgency'], $manageMw);
