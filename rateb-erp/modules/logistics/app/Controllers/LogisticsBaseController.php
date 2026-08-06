<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Support\LogisticsView;

abstract class LogisticsBaseController extends Controller
{
    protected function logisticsView(string $view, array $data = []): void
    {
        LogisticsView::render($view, $data, 'main');
    }

    protected function bootstrapLogistics(): void
    {
        $companyId = $this->companyId();
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
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

    protected function guardView(string $resource): void
    {
        if (function_exists('rateb_can_view_entity') && rateb_can_view_entity($resource)) {
            return;
        }
        $this->denyAccess($resource);
    }

    protected function guardManage(string $resource): void
    {
        if (function_exists('rateb_can_manage_entity') && rateb_can_manage_entity($resource)) {
            return;
        }
        $this->denyAccess($resource);
    }

    protected function denyAccess(string $resource): void
    {
        SessionManager::flash('error', __('access_denied'));
        $this->redirect(rateb_app_url($resource === 'logistics' ? 'logistics' : $resource));
    }

    protected function requireCsrf(string $fallbackUrl): void
    {
        if ($this->validateCsrf()) {
            return;
        }
        SessionManager::flash('error', __('invalid_request'));
        $this->redirect($fallbackUrl);
    }
}
