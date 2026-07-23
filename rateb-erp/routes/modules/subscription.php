<?php
declare(strict_types=1);

/**
 * Tenant subscription renewal / status routes (Phase 7B).
 * Accessible while suspended (allow-listed). No payment processing.
 */

use Rateb\App\Core\Middleware\ErpAuthMiddleware;
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
