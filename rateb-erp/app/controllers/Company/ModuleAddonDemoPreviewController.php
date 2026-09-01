<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Services\ModuleAddonDemoPreviewService;
use Rateb\App\Services\ModuleAddonService;

/**
 * Super Admin, admin.rateb.sa preview host only.
 * Does not purchase, invoice, or start payment.
 */
final class ModuleAddonDemoPreviewController extends Controller
{
    public function show(): void
    {
        if (!$this->allowed()) {
            $this->notFound();
            return;
        }

        $this->view('billing/module-demo-preview', [
            'title' => 'Module add-on demo user',
            'csrf' => Csrf::token(),
            'action' => rateb_url('admin/billing/addon-preview-user'),
            'email' => ModuleAddonDemoPreviewService::DEMO_EMAIL,
            'result' => null,
            'password' => '',
        ], 'main');
    }

    public function bootstrap(): void
    {
        if (!$this->allowed()) {
            $this->notFound();
            return;
        }
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_url('admin/billing/addon-preview-user'));
            return;
        }

        $password = $this->randomPassword();
        $result = (new ModuleAddonDemoPreviewService())->ensureDemoUser($password);
        $this->view('billing/module-demo-preview', [
            'title' => 'Module add-on demo user',
            'csrf' => Csrf::token(),
            'action' => rateb_url('admin/billing/addon-preview-user'),
            'email' => ModuleAddonDemoPreviewService::DEMO_EMAIL,
            'result' => $result,
            'password' => ($result['ok'] ?? false) ? $password : '',
        ], 'main');
    }

    private function allowed(): bool
    {
        $addons = new ModuleAddonService();

        return $addons->isEnabled() && $addons->previewDemoHostAllowed();
    }

    private function randomPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < 16; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out . '#1a';
    }
}
