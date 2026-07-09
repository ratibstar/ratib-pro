<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Pos\Services\PosDemoDataSetupService;
use Throwable;

final class PosSettingsController extends PosBaseController
{
    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/settings');
        $this->posView('settings/index', [
            'title' => __('pos_settings'),
            'csrf' => Csrf::token(),
            'demoSetupUrl' => rateb_app_url('pos/settings/demo-setup'),
        ], 'pos-pages-shell');
    }

    public function setupDemoData(): void
    {
        $this->bootstrapPos();
        $this->guardPosManage('pos/settings');
        $this->requireSessionCsrfOrAbort();

        $companyId = $this->companyId();
        if ($companyId < 1) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url('pos/settings'));
            return;
        }

        try {
            $result = (new PosDemoDataSetupService())->run($companyId);
            SessionManager::flash('success', __('pos_demo_setup_done', [
                'warehouse_id' => (string) ($result['warehouse_id'] ?? 0),
                'created' => (string) ($result['products_created'] ?? 0),
                'updated' => (string) ($result['products_updated'] ?? 0),
            ]));
        } catch (Throwable $e) {
            SessionManager::flash('error', __('pos_demo_setup_failed'));
        }

        $this->redirect(rateb_app_url('pos/settings'));
    }
}
