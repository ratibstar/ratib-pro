<?php
declare(strict_types=1);

use Rateb\App\Controllers\Api\CustomerPortalAuthController;
use Rateb\App\Core\Middleware\CustomerPortalAuthMiddleware;

/** @var Rateb\App\Core\Router $router */

/** Customer Portal app auth foundation — separate from staff /api/v1/auth/token. */
$router->post('/api/v1/portal/auth/login', [CustomerPortalAuthController::class, 'login']);
$router->post('/api/v1/portal/auth/logout', [CustomerPortalAuthController::class, 'logout'], [CustomerPortalAuthMiddleware::class]);
$router->get('/api/v1/portal/auth/session', [CustomerPortalAuthController::class, 'session'], [CustomerPortalAuthMiddleware::class]);
