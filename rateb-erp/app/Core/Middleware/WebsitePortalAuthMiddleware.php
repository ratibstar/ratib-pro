<?php
declare(strict_types=1);

namespace Rateb\App\Core\Middleware;

use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Website\Portal\PortalAuthService;
use Rateb\App\Website\WebsiteContext;

/**
 * Phase WEBSITE-07 — Portal session guard (employer|customer|partner).
 */
final class WebsitePortalAuthMiddleware implements MiddlewareInterface
{
    private string $portalType;

    public function __construct(string $portalType = '')
    {
        $this->portalType = $portalType;
    }

    public function handle(): bool
    {
        if (class_exists(WebsiteContext::class)) {
            WebsiteContext::bootFromRequest();
        }
        $expected = $this->portalType;
        if (!PortalAuthService::isValidType($expected)) {
            $expected = $this->detectTypeFromPath();
        }
        $expected = PortalAuthService::isValidType($expected) ? $expected : null;
        $auth = new PortalAuthService();
        if (!$auth->isLoggedIn($expected)) {
            SessionManager::flash('error', __('login_required') ?: 'Login required');
            $base = $expected !== null ? 'site/' . $expected . '/login' : 'site/employer/login';
            Response::redirect(rateb_url($base));

            return false;
        }

        return true;
    }

    private function detectTypeFromPath(): string
    {
        $path = '';
        if (isset($_GET['route']) && is_string($_GET['route'])) {
            $path = '/' . trim($_GET['route'], '/');
        } else {
            $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
        }
        if (preg_match('#/(employer|customer|partner)(/|$)#', $path, $m)) {
            return $m[1];
        }

        return '';
    }
}
