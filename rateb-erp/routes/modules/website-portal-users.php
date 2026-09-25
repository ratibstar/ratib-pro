<?php
declare(strict_types=1);

/**
 * Website module — Customer Portal user administration (company-scoped, no hard delete).
 * Required from routes/modules/ops.php inside the Website block; expects $router and $app.
 */

use Rateb\App\Controllers\Company\WebsitePortalUsersController;

$websitePortalViewMw = rateb_erp_mw('website', 'website.portal.view', 'website-portal-users');
$websitePortalManageMw = rateb_erp_mw('website', 'website.portal.manage', 'website-portal-users');

$router->get($app('website/portal-users'), [WebsitePortalUsersController::class, 'index'], $websitePortalViewMw);
$router->get($app('website/portal-users/create'), [WebsitePortalUsersController::class, 'create'], $websitePortalManageMw);
$router->post($app('website/portal-users'), [WebsitePortalUsersController::class, 'store'], $websitePortalManageMw);
$router->get($app('website/portal-users/{id}/edit'), [WebsitePortalUsersController::class, 'edit'], $websitePortalManageMw);
$router->post($app('website/portal-users/{id}'), [WebsitePortalUsersController::class, 'update'], $websitePortalManageMw);
$router->post($app('website/portal-users/{id}/password'), [WebsitePortalUsersController::class, 'password'], $websitePortalManageMw);
$router->post($app('website/portal-users/{id}/status'), [WebsitePortalUsersController::class, 'status'], $websitePortalManageMw);
$router->post($app('website/portal-users/{id}/app-access/approve'), [WebsitePortalUsersController::class, 'approveAppAccess'], $websitePortalManageMw);
$router->post($app('website/portal-users/{id}/app-access/revoke'), [WebsitePortalUsersController::class, 'revokeAppAccess'], $websitePortalManageMw);
$router->post($app('website/portal-users/{id}/sessions/revoke'), [WebsitePortalUsersController::class, 'revokeSessions'], $websitePortalManageMw);
