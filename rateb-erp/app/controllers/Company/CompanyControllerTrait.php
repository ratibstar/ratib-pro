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
        if (function_exists('rateb_flash_access_denied')) {
            rateb_flash_access_denied();
        } elseif (!function_exists('rateb_is_non_document_request') || !rateb_is_non_document_request()) {
            \Rateb\App\Core\SessionManager::flash('error', __('access_denied'));
        }
        $this->redirect(rateb_app_url($resource));
    }

    protected function companyLayout(): string
    {
        return 'main';
    }
}
