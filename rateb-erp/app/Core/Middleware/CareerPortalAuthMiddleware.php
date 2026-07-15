<?php
declare(strict_types=1);

namespace Rateb\App\Core\Middleware;

use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Website\Career\CareerPortalAuthService;
use Rateb\App\Website\WebsiteContext;

/**
 * Phase WEBSITE-06 — Career candidate portal session guard (tenant-isolated).
 */
final class CareerPortalAuthMiddleware implements MiddlewareInterface
{
    public function handle(): bool
    {
        if (class_exists(WebsiteContext::class)) {
            WebsiteContext::bootFromRequest();
        }
        $auth = new CareerPortalAuthService();
        if (!$auth->isLoggedIn()) {
            SessionManager::flash('error', __('login_required') ?: 'Login required');
            Response::redirect(rateb_url('site/candidate/login'));

            return false;
        }

        return true;
    }
}
