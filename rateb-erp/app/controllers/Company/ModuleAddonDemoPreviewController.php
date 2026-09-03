<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
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
            'locksUrl' => rateb_url('admin/billing/addon-locks'),
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
            'locksUrl' => rateb_url('admin/billing/addon-locks'),
        ], 'main');
    }

    public function locks(): void
    {
        if (!ModuleAddonDemoPreviewService::sessionCanManageDemoLocks()) {
            $this->notFound();
            return;
        }

        $preview = new ModuleAddonDemoPreviewService();

        $this->view('billing/module-demo-locks', [
            'title' => __('module_addon_demo_locks'),
            'csrf' => Csrf::token(),
            'action' => rateb_url('admin/billing/addon-locks'),
            'rows' => $preview->lockBoard(),
            'context' => $preview->lockBoardContext(),
            'returnTo' => 'locks',
        ], 'main');
    }

    public function toggleLocks(): void
    {
        if (!ModuleAddonDemoPreviewService::sessionCanManageDemoLocks()) {
            $this->notFound();
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', function_exists('__') ? __('invalid_request') : 'Invalid request');
            $this->redirect($this->locksReturnUrl());
            return;
        }

        $action = strtolower(trim((string) ($_POST['lock_action'] ?? '')));
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        unset($_POST['company_id'], $_POST['modules']);
        $result = (new ModuleAddonDemoPreviewService())->setLocks($action, $slug);
        if (!empty($result['ok'])) {
            SessionManager::flash('success', __('module_addon_demo_locks_saved'));
        } else {
            SessionManager::flash('error', (string) ($result['code'] ?? 'error'));
        }
        $this->redirect($this->locksReturnUrl());
    }

    private function locksReturnUrl(): string
    {
        $to = strtolower(trim((string) ($_POST['return_to'] ?? 'locks')));
        if ($to === 'dashboard') {
            return rateb_url('admin');
        }
        if ($to === 'platform') {
            return rateb_url('admin');
        }

        return rateb_url('admin/billing/addon-locks');
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
