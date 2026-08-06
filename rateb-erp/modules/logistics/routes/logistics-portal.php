<?php
declare(strict_types=1);

/**
 * Logistics Customer Portal routes — Phase 5
 *
 * @var Rateb\App\Core\Router $router
 */

use Rateb\App\Logistics\Controllers\CustomerLogisticsController;

require_once RATEB_ROOT . '/routes/middleware-helpers.php';

$cmw = rateb_website_portal_mw('customer');
$router->get('/site/customer/logistics', [CustomerLogisticsController::class, 'index'], $cmw);
