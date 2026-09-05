<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\Company;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\DatabaseErrorService;
use Rateb\App\Services\PlanLimitService;

/**
 * Platform-only: SaaS module entitlements per company (nav / feature gate).
 * Independent from user RBAC (roles / permissions).
 */
final class CompanyPermissionsController extends Controller
{
    private Company $companies;
    private PlanLimitService $limits;

    public function __construct()
    {
        $this->companies = new Company();
        $this->limits = new PlanLimitService();
    }

    public function index(): void
    {
        $this->noStore();
        $this->guardAccess('companies.view');

        $page = max(1, (int) $this->input('page', 1));
        $limit = rateb_list_per_page();
        $offset = ($page - 1) * $limit;
        $search = trim((string) $this->input('q', ''));

        $items = $this->companies->all($limit, $offset, [], $search);
        $catalog = PlanLimitService::moduleCatalog();

        $catalogKeys = array_keys($catalog);
        foreach ($items as &$row) {
            $id = (int) ($row['id'] ?? 0);
            $enabled = $this->enabledModulesForCompany($row, $catalogKeys);
            $labels = [];
            foreach ($enabled as $key) {
                $langKey = $catalog[$key] ?? $key;
                $labels[] = __(is_string($langKey) ? $langKey : $key);
            }
            $row['modules_enabled'] = $enabled;
            $row['modules_labels'] = $labels;
            $row['modules_count'] = count($enabled);
            $row['modules_total'] = count($catalog);
            $row['modules_pct'] = count($catalog) > 0
                ? (int) round((count($enabled) / count($catalog)) * 100)
                : 0;
        }
        unset($row);

        $this->view('admin/company-permissions/index', [
            'title' => __('company_permissions'),
            'items' => $items,
            'total' => $this->companies->count([], $search),
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'routePrefix' => 'admin/company-permissions',
            'csrf' => Csrf::token(),
            'canManage' => rateb_is_super_admin() || rateb_can('company_plans.manage') || rateb_can('companies.manage') || rateb_can('company_permissions.manage'),
            'moduleCatalog' => $catalog,
        ], 'main');
    }

    public function edit(array $params): void
    {
        $this->noStore();
        $this->guardAccess('company_plans.manage', 'companies.manage', 'company_permissions.manage');

        $id = (int) ($params['id'] ?? 0);
        $company = $this->companies->find($id);
        if (!$company) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }

        $catalog = PlanLimitService::moduleCatalog();
        $selected = $this->enabledModulesForCompany($company, array_keys($catalog));
        $limits = $this->limits->getLimits($id);
        $this->view('admin/company-permissions/edit', [
            'title' => __('company_permissions') . ' — ' . (string) ($company['name'] ?? ''),
            'company' => $company,
            'moduleCatalog' => $catalog,
            'selectedModules' => $selected,
            'limits' => $limits,
            'routePrefix' => 'admin/company-permissions',
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function update(array $params): void
    {
        $this->noStore();
        $this->guardAccess('company_plans.manage', 'companies.manage', 'company_permissions.manage');

        $id = (int) ($params['id'] ?? 0);
        $back = rateb_url('admin/company-permissions/' . max(0, $id));

        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            Response::redirect($id > 0 ? $back : rateb_url('admin/company-permissions'));
        }

        $company = $this->companies->find($id);
        if (!$company) {
            SessionManager::flash('error', __('no_records'));
            Response::redirect(rateb_url('admin/company-permissions'));
        }

        $raw = $_POST['modules'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }
        $modules = PlanLimitService::filterKnownModules($raw);
        foreach (['dashboard', 'notifications'] as $implied) {
            if (!in_array($implied, $modules, true)) {
                $modules[] = $implied;
            }
        }
        $modules = array_values(array_unique($modules));

        try {
            $ok = $this->companies->updateModules($id, $modules);
            if (!$ok) {
                SessionManager::flash('error', __('company_permissions_save_failed'));
                Response::redirect($back);
            }
            PlanLimitService::forgetCompanyLimits($id);

            $agencyPush = ['synced' => false, 'agency_id' => 0, 'agency_company_id' => 0];
            try {
                $agencyPush = (new \Rateb\App\Services\AgencyErpMigrationService())
                    ->pushModulesToLinkedAgency($id, $modules);
            } catch (\Throwable $agencyErr) {
                error_log('company permissions agency modules sync #' . $id . ': ' . $agencyErr->getMessage());
                SessionManager::flash(
                    'warning',
                    __('company_permissions_agency_sync_failed') . ' — ' . $agencyErr->getMessage()
                );
                $agencyPush = ['synced' => false, 'agency_id' => 0, 'agency_company_id' => 0];
            }

            (new AuditService())->log('update', 'company_permissions', $id, [
                'modules' => $modules,
                'company_name' => (string) ($company['name'] ?? ''),
                'agency_synced' => !empty($agencyPush['synced']),
                'agency_id' => (int) ($agencyPush['agency_id'] ?? 0),
                'agency_company_id' => (int) ($agencyPush['agency_company_id'] ?? 0),
            ]);
            SessionManager::flash(
                'success',
                !empty($agencyPush['synced'])
                    ? __('company_permissions_saved_agency_synced')
                    : __('company_permissions_saved')
            );
        } catch (\Throwable $e) {
            SessionManager::flash('error', DatabaseErrorService::userMessage($e));
            Response::redirect($back);
        }

        // Land on list with cache-buster so offline SW cannot serve stale edit HTML.
        Response::redirect(rateb_url('admin/company-permissions') . '?saved=' . $id . '&t=' . time());
    }

    /** @param list<string> $requiredAny */
    private function guardAccess(string ...$requiredAny): void
    {
        if (rateb_is_super_admin()) {
            return;
        }
        foreach ($requiredAny as $perm) {
            if ($perm !== '' && rateb_can($perm)) {
                return;
            }
        }
        SessionManager::flash('error', __('access_denied'));
        Response::redirect(rateb_url('admin'));
    }

    private function noStore(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }

    /**
     * Prefer explicit company.modules JSON; fall back to plan limits.
     *
     * @param array<string, mixed> $company
     * @param list<string> $catalogKeys
     * @return list<string>
     */
    private function enabledModulesForCompany(array $company, array $catalogKeys): array
    {
        $id = (int) ($company['id'] ?? 0);
        $explicit = [];
        $raw = $company['modules'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $explicit = array_values(array_filter(array_map('strval', $decoded)));
            }
        } elseif (is_array($raw)) {
            $explicit = array_values(array_filter(array_map('strval', $raw)));
        }

        $source = $explicit;
        if ($source === [] && $id > 0) {
            $source = $this->limits->getLimits($id)['modules'] ?? [];
            if (!is_array($source)) {
                $source = [];
            }
        }

        return array_values(array_filter(
            array_map('strval', $source),
            static fn(string $key): bool => $key !== '' && in_array($key, $catalogKeys, true)
        ));
    }
}
