<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Pos\Services\PosContextService;
use Rateb\App\Pos\Support\PosView;

abstract class PosBaseController extends Controller
{
    protected function posView(string $view, array $data = [], ?string $layout = 'pos-admin'): void
    {
        PosView::render($view, $data, $layout);
    }

    protected function bootstrapPos(): void
    {
        (new PosContextService())->bootstrapTenant();
    }

    protected function companyId(): int
    {
        if (function_exists('rateb_require_ops_company')) {
            return rateb_require_ops_company();
        }
        return (int) (SessionManager::get('rateb_company_id') ?? 0);
    }

    protected function userId(): int
    {
        return (int) (SessionManager::get('rateb_user_id') ?? 0);
    }

    protected function guardPosView(string $resource): void
    {
        if (function_exists('rateb_can_view_entity') && rateb_can_view_entity($resource)) {
            return;
        }
        $this->denyAccess($resource);
    }

    protected function guardPosManage(string $resource): void
    {
        if (function_exists('rateb_can_manage_entity') && rateb_can_manage_entity($resource)) {
            return;
        }
        $this->denyAccess($resource);
    }

    protected function guardPosPermission(string $slug, string $resource = 'pos'): void
    {
        if ($slug === '' || (function_exists('rateb_can') && rateb_can($slug))) {
            return;
        }
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return;
        }
        $this->denyAccess($resource);
    }

    protected function denyAccess(string $resource): void
    {
        SessionManager::flash('error', __('access_denied'));
        $this->redirect(rateb_app_url($resource === 'pos' ? 'pos/dashboard' : $resource));
    }

    protected function flashBack(string $type, string $message): void
    {
        SessionManager::flash($type, $message);
    }

    /** @return array<string, mixed> */
    protected function inputData(): array
    {
        return array_merge($_GET, $_POST);
    }
}
