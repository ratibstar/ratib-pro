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
            $enabled = $id > 0 ? $this->limits->getLimits($id)['modules'] : [];
            if (!is_array($enabled)) {
                $enabled = [];
            }
            // Count only known catalog modules (ignore stray legacy keys).
            $enabledKnown = array_values(array_filter(
                array_map('strval', $enabled),
                static fn(string $key): bool => $key !== '' && in_array($key, $catalogKeys, true)
            ));
            $row['modules_enabled'] = $enabledKnown;
            $row['modules_count'] = count($enabledKnown);
            $row['modules_total'] = count($catalog);
            [$summary, $summaryFull] = $this->formatSummary($enabledKnown, $catalog);
            $row['modules_summary'] = $summary;
            $row['modules_summary_full'] = $summaryFull;
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
            'canManage' => rateb_can('company_plans.manage') || rateb_can('companies.manage'),
        ], 'main');
    }

    public function edit(array $params): void
    {
        $this->guardAccess('company_plans.manage', 'companies.manage');

        $id = (int) ($params['id'] ?? 0);
        $company = $this->companies->find($id);
        if (!$company) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }

        $limits = $this->limits->getLimits($id);
        $this->view('admin/company-permissions/edit', [
            'title' => __('company_permissions') . ' — ' . (string) ($company['name'] ?? ''),
            'company' => $company,
            'moduleCatalog' => PlanLimitService::moduleCatalog(),
            'selectedModules' => $limits['modules'],
            'limits' => $limits,
            'routePrefix' => 'admin/company-permissions',
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function update(array $params): void
    {
        $this->guardAccess('company_plans.manage', 'companies.manage');

        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('admin/company-permissions'));
        }

        $id = (int) ($params['id'] ?? 0);
        $company = $this->companies->find($id);
        if (!$company) {
            SessionManager::flash('error', __('no_records'));
            Response::redirect(rateb_url('admin/company-permissions'));
        }

        $raw = $this->input('modules', []);
        if (!is_array($raw)) {
            $raw = [];
        }
        $modules = PlanLimitService::filterKnownModules($raw);
        // Keep implied core modules so tenant dashboards stay reachable.
        foreach (['dashboard', 'notifications'] as $implied) {
            if (!in_array($implied, $modules, true)) {
                $modules[] = $implied;
            }
        }

        try {
            $this->companies->update($id, [
                'modules' => json_encode(array_values($modules), JSON_UNESCAPED_UNICODE),
            ]);
            (new AuditService())->log('update', 'company_permissions', $id, [
                'modules' => $modules,
                'company_name' => (string) ($company['name'] ?? ''),
            ]);
            SessionManager::flash('success', __('company_permissions_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', DatabaseErrorService::userMessage($e));
            Response::redirect(rateb_url('admin/company-permissions/' . $id));
        }

        Response::redirect(rateb_url('admin/company-permissions/' . $id));
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

    /**
     * @param list<string> $enabled
     * @param array<string, string> $catalog
     * @return array{0:string,1:string} short summary, full summary
     */
    private function formatSummary(array $enabled, array $catalog): array
    {
        if ($enabled === []) {
            return ['—', '—'];
        }
        $labels = [];
        foreach ($enabled as $key) {
            $langKey = $catalog[$key] ?? $key;
            $labels[] = __(is_string($langKey) ? $langKey : $key);
        }
        $full = implode('، ', $labels);
        $shortLabels = array_slice($labels, 0, 4);
        $short = implode('، ', $shortLabels);
        $extra = count($labels) - count($shortLabels);
        if ($extra > 0) {
            $short .= ' +' . $extra;
        }

        return [$short, $full];
    }
}
