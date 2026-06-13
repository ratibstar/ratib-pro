<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Marketing;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\CmsService;
use Rateb\App\Services\CustomerPortalService;

final class CustomerPortalController extends Controller
{
    public function index(): void
    {
        $portal = new CustomerPortalService();
        $data = $portal->snapshot();
        $cms = new CmsService();

        $this->view('marketing/portal/index', [
            'title' => __('portal_dashboard'),
            'meta' => $cms->metaTags('portal', __('portal_dashboard')),
            'menuItems' => $cms->menuItems(),
            'theme' => $cms->theme(),
            'analytics' => $cms->analytics(),
            'csrf' => Csrf::token(),
            'portal' => $data,
            'isPortalPage' => true,
        ], 'marketing');
    }

    public function logout(): void
    {
        Auth::logout();
        SessionManager::flash('success', __('logout_ok'));
        Response::redirect(rateb_url('site'));
    }
}
