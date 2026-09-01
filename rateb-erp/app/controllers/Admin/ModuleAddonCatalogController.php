<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\ModuleAddonCheckoutService;
use Rateb\App\Services\ModuleAddonService;

/**
 * Platform Super Admin: commercial catalog (availability + prices).
 * Not tenant self-service. Does not grant company.modules or change RBAC.
 */
final class ModuleAddonCatalogController extends Controller
{
    public function index(): void
    {
        $addons = new ModuleAddonService();
        if (!$addons->canManagePlatformCatalog()) {
            $this->notFound();
            return;
        }

        $checkout = new ModuleAddonCheckoutService($addons);
        $modules = [];
        foreach ($addons->catalog() as $slug => $item) {
            $monthly = (float) ($item['monthly'] ?? 0);
            $yearly = (float) ($item['yearly'] ?? 0);
            $modules[] = [
                'item' => $item,
                'slug' => $slug,
                'saving' => ModuleAddonCheckoutService::annualSaving($monthly, $yearly),
                'purchasable' => $addons->isPurchasable($slug),
                'features_text' => ModuleAddonService::featuresToTextarea((array) ($item['features'] ?? [])),
            ];
        }

        $preview = $addons->previewDemoHostAllowed();
        $this->view('admin/module-addons/index', [
            'title' => __('module_addon_catalog'),
            'csrf' => Csrf::token(),
            'modules' => $modules,
            'commerceEnabled' => $addons->isEnabled(),
            'isPreviewHost' => $preview,
            'isPlatformHost' => function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host(),
            'unpaidInvoices' => $preview ? $checkout->listUnpaidAddonInvoices() : [],
            'saveAction' => rateb_url('admin/module-addons'),
            'voidAction' => rateb_url('admin/module-addons/void-invoice'),
        ], 'main');
    }

    public function save(): void
    {
        $addons = new ModuleAddonService();
        if (!$addons->canManagePlatformCatalog()) {
            $this->notFound();
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            $this->redirect(rateb_url('admin/module-addons'));
            return;
        }

        $posted = $_POST['modules'] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }
        unset(
            $_POST['price'],
            $_POST['monthly_price'],
            $_POST['yearly_price'],
            $_POST['discount'],
            $_POST['tax'],
            $_POST['tax_amount'],
            $_POST['total'],
            $_POST['total_amount'],
            $_POST['saving'],
            $_POST['savings']
        );

        $result = $addons->saveCommerceOverrides($posted);
        if (!($result['ok'] ?? false)) {
            SessionManager::flash('error', __('module_addon_catalog_save_failed'));
            $this->redirect(rateb_url('admin/module-addons'));
            return;
        }

        (new AuditService())->log('update', 'module_addon_catalog', null, [
            'slugs' => array_keys($posted),
        ]);
        SessionManager::flash('success', __('module_addon_catalog_saved'));
        $this->redirect(rateb_url('admin/module-addons'));
    }

    public function voidInvoice(): void
    {
        $addons = new ModuleAddonService();
        if (!$addons->canManagePlatformCatalog() || !$addons->previewDemoHostAllowed()) {
            $this->notFound();
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            $this->redirect(rateb_url('admin/module-addons'));
            return;
        }

        $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
        $result = (new ModuleAddonCheckoutService($addons))->voidUnpaidAddonInvoice($invoiceId);
        if (!($result['ok'] ?? false)) {
            SessionManager::flash('error', __('module_addon_catalog_void_refused'));
            $this->redirect(rateb_url('admin/module-addons'));
            return;
        }

        (new AuditService())->log('update', 'module_addon_invoice_void', $invoiceId, [
            'code' => (string) ($result['code'] ?? ''),
        ]);
        SessionManager::flash('success', __('module_addon_catalog_voided'));
        $this->redirect(rateb_url('admin/module-addons'));
    }
}
