<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Auth;
use Rateb\App\Core\TenantContext;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AuthorizationService;

final class AiController extends Controller
{
    public function index(): void
    {
        // Auth bootstrap
        Auth::bootstrapFromSession();

        // Check if user has access to AI module
        $user = Auth::user();
        if (!$user) {
            $this->redirect(rateb_url('login'));
            return;
        }

        $companyId = TenantContext::companyId();
        if (!$companyId) {
            $this->redirect(rateb_url('admin'));
            return;
        }

        // Check if company has any module that uses AI (procurement for now)
        $planLimits = new \Rateb\App\Services\PlanLimitService();
        $hasProcurement = $planLimits->companyHasModule($companyId, 'procurement');

        if (!$hasProcurement) {
            // No AI-enabled modules available
            http_response_code(403);
            $this->view('errors/403', ['title' => '403'], 'main');
            return;
        }

        $this->view('company/ai/index', [
            'title' => __('rateb_ai') ?? 'RATEB AI',
            'locale' => SessionManager::get('rateb_locale', 'en'),
            'csrf' => \Rateb\App\Core\Csrf::token(),
        ], 'main');
    }
}