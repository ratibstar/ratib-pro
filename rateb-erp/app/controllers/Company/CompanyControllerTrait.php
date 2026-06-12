<?php
declare(strict_types=1);

/** Shared helpers for company operation controllers. */
trait CompanyControllerTrait
{
    protected function guardEntityManage(string $resource): void
    {
        if (rateb_can_manage_entity($resource)) {
            return;
        }
        \Rateb\App\Core\SessionManager::flash('error', __('access_denied'));
        $this->redirect(rateb_app_url($resource));
    }

    protected function companyLayout(): string
    {
        return 'main';
    }
}
