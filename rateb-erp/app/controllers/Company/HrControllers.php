<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\User;

final class HrDashboardController extends Controller
{
    public function index(): void
    {
        $companyId = rateb_resolve_ops_company_id();
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $stats = $this->stats($companyId);
        $this->view('company/hr/dashboard', [
            'title' => __('human_resources'),
            'stats' => $stats,
        ], 'main');
    }

    public function employees(): void
    {
        $companyId = rateb_require_ops_company();
        TenantContext::setCompanyId($companyId);
        $rows = (new User())->query(
            "SELECT id, name, email, status, locale, created_at
             FROM rateb_users
             WHERE company_id = :cid AND is_super_admin = 0
             ORDER BY name ASC LIMIT 500",
            ['cid' => $companyId]
        );
        $this->view('company/hr/employees', [
            'title' => __('hr_employees'),
            'rows' => $rows,
        ], 'main');
    }

    public function attendance(): void
    {
        rateb_require_ops_company();
        $this->view('company/hr/section', [
            'title' => __('hr_attendance'),
            'sectionKey' => 'attendance',
        ], 'main');
    }

    public function leaves(): void
    {
        rateb_require_ops_company();
        $this->view('company/hr/section', [
            'title' => __('hr_leaves'),
            'sectionKey' => 'leaves',
        ], 'main');
    }

    public function payroll(): void
    {
        rateb_require_ops_company();
        $this->view('company/hr/section', [
            'title' => __('hr_payroll'),
            'sectionKey' => 'payroll',
        ], 'main');
    }

    /** @return array{employees:int,active:int} */
    private function stats(int $companyId): array
    {
        if ($companyId < 1) {
            return ['employees' => 0, 'active' => 0];
        }
        $row = (new User())->queryOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active
             FROM rateb_users
             WHERE company_id = :cid AND is_super_admin = 0",
            ['cid' => $companyId]
        );
        return [
            'employees' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
        ];
    }
}
