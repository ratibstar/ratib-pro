<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Models\Company;
use Rateb\App\Models\Plan;
use Rateb\App\Models\Subscription;

/** Read-only subscription / plan view for dedicated agency ERP hosts. */
final class CompanyPlanController extends Controller
{
    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $companyId = function_exists('rateb_resolve_ops_company_id') ? rateb_resolve_ops_company_id() : 0;
        $company = $companyId > 0 ? (new Company())->find($companyId) : null;
        $plan = null;
        $subscription = null;
        if ($company !== null) {
            $planId = (int) ($company['plan_id'] ?? 0);
            if ($planId > 0) {
                $plan = (new Plan())->find($planId);
            }
            $subscription = (new Subscription())->queryOne(
                'SELECT * FROM rateb_subscriptions WHERE company_id = :cid ORDER BY id DESC LIMIT 1',
                ['cid' => $companyId]
            );
        }

        $this->view('company/access/plan', [
            'title' => __('plans'),
            'company' => $company,
            'plan' => $plan,
            'subscription' => $subscription,
            'modules' => $modules,
            'moduleLines' => is_array($plan) ? Plan::marketingModuleHighlights($plan, 20) : [],
        ], 'main');
    }
}
