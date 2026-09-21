<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Website / CMS Tool Executor — tenant-scoped READ against live ERP tables.
 */
final class WebsiteToolExecutor
{
    /** @var array<string, array<string, mixed>> */
    private const TOOLS = array (
  'list_cms_pages' => 
  array (
    'desc' => 'List CMS pages (tenant-scoped).',
    'table' => 'rateb_cms_pages',
    'sql' => 'SELECT id, slug, title, status, published_at, updated_at, created_at
                          FROM rateb_cms_pages WHERE company_id = :cid',
    'search_cols' => 
    array (
      0 => 'slug',
      1 => 'title',
    ),
    'status_col' => 'status',
    'order' => 'id DESC',
  ),
  'list_cms_leads' => 
  array (
    'desc' => 'List CMS leads (tenant-scoped).',
    'table' => 'rateb_cms_leads',
    'sql' => 'SELECT id, name, email, phone, status, source, created_at
                          FROM rateb_cms_leads WHERE company_id = :cid',
    'search_cols' => 
    array (
      0 => 'name',
      1 => 'email',
      2 => 'phone',
    ),
    'status_col' => 'status',
    'order' => 'id DESC',
  ),
  'list_website_form_submissions' => 
  array (
    'desc' => 'List website form submissions (tenant-scoped).',
    'table' => 'rateb_website_form_submissions',
    'sql' => 'SELECT id, form_id, status, created_at
                          FROM rateb_website_form_submissions WHERE company_id = :cid',
    'status_col' => 'status',
    'order' => 'id DESC',
  ),
  'website_content_summary' => 
  array (
    'desc' => 'Website/CMS content summary from live pages and leads.',
    'kind' => 'website_summary',
  ),
);

    public static function execute(string $toolName, array $arguments, ProcurementAgentContext $ctx): array
    {
        $companyId = (int) $ctx->companyId;
        if ($companyId < 1) {
            return ErpAiDb::fail('tenant_mismatch');
        }
        if (!isset(self::TOOLS[$toolName])) {
            return ErpAiDb::fail('tool_not_implemented');
        }
        try {
            return self::run($toolName, self::TOOLS[$toolName], $arguments, $companyId);
        } catch (\Throwable $e) {
            return ErpAiDb::fail('tool_exception');
        }
    }

    /**
     * @param array<string, mixed> $tool
     * @param array<string, mixed> $args
     */
    private static function run(string $toolName, array $tool, array $args, int $companyId): array
    {
        $kind = (string) ($tool['kind'] ?? 'list');
        if ($kind === 'get') {
            return self::getById($tool, $args, $companyId);
        }
        if ($kind === 'group_count') {
            return self::groupCount($tool, $companyId);
        }
        if ($kind === 'expiring') {
            return self::expiring($tool, $args, $companyId);
        }
        if ($kind === 'summary_hr') {
            return self::hrSummary($companyId);
        }
        if ($kind === 'payroll_summary') {
            return self::payrollSummary($companyId);
        }
        if ($kind === 'quality_summary') {
            return self::qualitySummary($companyId);
        }
        if ($kind === 'marketplace_summary') {
            return self::marketplaceSummary($companyId);
        }
        if ($kind === 'notifications_digest' || $kind === 'unread_notifications') {
            return self::notifications($kind, $args, $companyId);
        }
        if ($kind === 'bi_summary') {
            return self::biSummary($companyId);
        }
        if ($kind === 'website_summary') {
            return self::websiteSummary($companyId);
        }
        if ($kind === 'assets_list' || $kind === 'assets_get' || $kind === 'assets_summary') {
            return self::assets($kind, $args, $companyId);
        }
        if ($kind === 'pending_eap') {
            return self::pendingEap($args, $companyId);
        }
        return self::listRows($tool, $args, $companyId);
    }

    private static function listRows(array $tool, array $args, int $companyId): array
    {
        $table = (string) ($tool['table'] ?? '');
        if ($table === '' || !ErpAiDb::tableExists($table)) {
            return ErpAiDb::ok(['rows' => [], 'note' => 'table_unavailable', 'table' => $table]);
        }
        $limit = ErpAiDb::clampLimit($args['limit'] ?? 50);
        $sql = rtrim((string) $tool['sql']);
        $params = ['cid' => $companyId];
        $statusCol = (string) ($tool['status_col'] ?? '');
        $status = trim((string) ($args['status'] ?? ''));
        if ($statusCol !== '' && $status !== '') {
            $sql .= ' AND ' . $statusCol . ' = :st';
            $params['st'] = $status;
        }
        $searchCols = $tool['search_cols'] ?? [];
        $search = trim((string) ($args['search'] ?? ''));
        if ($search !== '' && is_array($searchCols) && $searchCols !== []) {
            $parts = [];
            foreach ($searchCols as $i => $col) {
                $col = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $col) ?? '';
                if ($col === '') continue;
                $parts[] = $col . ' LIKE :q';
            }
            if ($parts !== []) {
                $sql .= ' AND (' . implode(' OR ', $parts) . ')';
                $params['q'] = '%' . $search . '%';
            }
        }
        $extra = $tool['extra_int'] ?? [];
        if (is_array($extra)) {
            foreach ($extra as $arg => $col) {
                $val = (int) ($args[$arg] ?? 0);
                $col = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $col) ?? '';
                if ($val > 0 && $col !== '') {
                    $sql .= ' AND ' . $col . ' = :' . $arg;
                    $params[$arg] = $val;
                }
            }
        }
        $order = (string) ($tool['order'] ?? 'id DESC');
        $order = preg_replace('/[^a-zA-Z0-9_,\s]/', '', $order) ?? 'id DESC';
        $sql .= ' ORDER BY ' . $order . ' LIMIT ' . $limit;
        return ErpAiDb::ok(['rows' => ErpAiDb::query($sql, $params), 'table' => $table]);
    }

    private static function getById(array $tool, array $args, int $companyId): array
    {
        $table = (string) ($tool['table'] ?? '');
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return ErpAiDb::fail('invalid_id');
        }
        if ($table === '' || !ErpAiDb::tableExists($table)) {
            return ErpAiDb::fail('table_unavailable');
        }
        $rows = ErpAiDb::query((string) $tool['sql'], ['id' => $id, 'cid' => $companyId]);
        if ($rows === []) {
            return ErpAiDb::fail('not_found');
        }
        return ErpAiDb::ok($rows[0]);
    }

    private static function groupCount(array $tool, int $companyId): array
    {
        $table = (string) ($tool['table'] ?? '');
        $group = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($tool['group'] ?? 'status')) ?? 'status';
        if ($table === '' || !ErpAiDb::tableExists($table)) {
            return ErpAiDb::ok(['by_status' => [], 'total' => 0, 'note' => 'table_unavailable', 'table' => $table]);
        }
        $rows = ErpAiDb::query(
            'SELECT ' . $group . ' AS bucket, COUNT(*) AS cnt FROM ' . $table . ' WHERE company_id = :cid GROUP BY ' . $group,
            ['cid' => $companyId]
        );
        $total = 0;
        $map = [];
        foreach ($rows as $r) {
            $k = (string) ($r['bucket'] ?? '');
            $c = (int) ($r['cnt'] ?? 0);
            $map[$k !== '' ? $k : 'unknown'] = $c;
            $total += $c;
        }
        return ErpAiDb::ok(['by_status' => $map, 'total' => $total, 'table' => $table, 'as_of' => date('c')]);
    }

    private static function expiring(array $tool, array $args, int $companyId): array
    {
        $table = (string) ($tool['table'] ?? '');
        $dateCol = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($tool['date_col'] ?? 'end_date')) ?? 'end_date';
        $days = max(1, min(365, (int) ($args['days'] ?? 60)));
        $limit = ErpAiDb::clampLimit($args['limit'] ?? 50);
        if ($table === '' || !ErpAiDb::tableExists($table)) {
            return ErpAiDb::ok(['rows' => [], 'note' => 'table_unavailable']);
        }
        $rows = ErpAiDb::query(
            'SELECT * FROM ' . $table . ' WHERE company_id = :cid
             AND ' . $dateCol . ' IS NOT NULL
             AND ' . $dateCol . ' BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ' . $days . ' DAY)
             ORDER BY ' . $dateCol . ' ASC LIMIT ' . $limit,
            ['cid' => $companyId]
        );
        return ErpAiDb::ok(['rows' => $rows, 'days' => $days]);
    }

    private static function hrSummary(int $companyId): array
    {
        $employees = ErpAiDb::tableExists('rateb_employees')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_employees WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0))
            : 0;
        $active = ErpAiDb::tableExists('rateb_employees')
            ? (int) ((ErpAiDb::query("SELECT COUNT(*) AS c FROM rateb_employees WHERE company_id = :cid AND status IN ('active','Active','ACTIVE')", ['cid' => $companyId])[0]['c'] ?? 0))
            : 0;
        $openLeave = ErpAiDb::tableExists('rateb_leave_requests')
            ? (int) ((ErpAiDb::query("SELECT COUNT(*) AS c FROM rateb_leave_requests WHERE company_id = :cid AND status IN ('pending','Pending','approved','Approved')", ['cid' => $companyId])[0]['c'] ?? 0))
            : 0;
        $departments = ErpAiDb::tableExists('rateb_hr_departments')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_hr_departments WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0))
            : 0;
        return ErpAiDb::ok([
            'employees_total' => $employees,
            'employees_active' => $active,
            'leave_openish' => $openLeave,
            'departments' => $departments,
            'as_of' => date('c'),
            'data_source' => 'live_tenant',
        ]);
    }

    private static function payrollSummary(int $companyId): array
    {
        $cycles = ErpAiDb::tableExists('rateb_payroll_cycles')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_payroll_cycles WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        $batches = ErpAiDb::tableExists('rateb_payroll_batches')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_payroll_batches WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        $payslips = ErpAiDb::tableExists('rateb_payroll_payslips')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_payroll_payslips WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        return ErpAiDb::ok([
            'cycles' => $cycles,
            'batches' => $batches,
            'payslips' => $payslips,
            'as_of' => date('c'),
            'data_source' => 'live_tenant',
        ]);
    }

    private static function qualitySummary(int $companyId): array
    {
        $inspections = ErpAiDb::tableExists('rateb_qms_inspections')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_qms_inspections WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        $ncr = ErpAiDb::tableExists('rateb_qms_nonconformities')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_qms_nonconformities WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        $openNcr = ErpAiDb::tableExists('rateb_qms_nonconformities')
            ? (int) ((ErpAiDb::query("SELECT COUNT(*) AS c FROM rateb_qms_nonconformities WHERE company_id = :cid AND status IN ('open','Open','pending','Pending')", ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        return ErpAiDb::ok([
            'inspections' => $inspections,
            'ncrs_total' => $ncr,
            'ncrs_openish' => $openNcr,
            'as_of' => date('c'),
            'data_source' => 'live_tenant',
        ]);
    }

    private static function marketplaceSummary(int $companyId): array
    {
        $providers = ErpAiDb::tableExists('rateb_mp_providers')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_mp_providers WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        $services = ErpAiDb::tableExists('rateb_mp_services')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_mp_services WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        $orders = ErpAiDb::tableExists('rateb_mp_orders')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_mp_orders WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        return ErpAiDb::ok([
            'providers' => $providers,
            'services' => $services,
            'orders' => $orders,
            'as_of' => date('c'),
            'data_source' => 'live_tenant',
        ]);
    }

    private static function notifications(string $kind, array $args, int $companyId): array
    {
        if (!ErpAiDb::tableExists('rateb_notifications')) {
            return ErpAiDb::ok(['rows' => [], 'note' => 'table_unavailable']);
        }
        $limit = ErpAiDb::clampLimit($args['limit'] ?? 50);
        if ($kind === 'unread_notifications') {
            $rows = ErpAiDb::query(
                'SELECT id, user_id, title, body, category, is_read, created_at
                 FROM rateb_notifications WHERE company_id = :cid AND (is_read = 0 OR is_read IS NULL)
                 ORDER BY id DESC LIMIT ' . $limit,
                ['cid' => $companyId]
            );
            return ErpAiDb::ok(['rows' => $rows]);
        }
        $unread = (int) ((ErpAiDb::query(
            'SELECT COUNT(*) AS c FROM rateb_notifications WHERE company_id = :cid AND (is_read = 0 OR is_read IS NULL)',
            ['cid' => $companyId]
        )[0]['c'] ?? 0));
        $recent = ErpAiDb::query(
            'SELECT id, title, category, is_read, created_at FROM rateb_notifications
             WHERE company_id = :cid ORDER BY id DESC LIMIT 10',
            ['cid' => $companyId]
        );
        return ErpAiDb::ok(['unread' => $unread, 'recent' => $recent, 'as_of' => date('c')]);
    }

    private static function biSummary(int $companyId): array
    {
        $dash = ErpAiDb::tableExists('rateb_bi_dashboards')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_bi_dashboards WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        $kpis = ErpAiDb::tableExists('rateb_bi_kpis')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_bi_kpis WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        $alerts = ErpAiDb::tableExists('rateb_bi_alerts')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_bi_alerts WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        return ErpAiDb::ok([
            'dashboards' => $dash,
            'kpis' => $kpis,
            'alerts' => $alerts,
            'as_of' => date('c'),
            'data_source' => 'live_tenant',
        ]);
    }

    private static function websiteSummary(int $companyId): array
    {
        $pages = ErpAiDb::tableExists('rateb_cms_pages')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_cms_pages WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        $leads = ErpAiDb::tableExists('rateb_cms_leads')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_cms_leads WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        $subs = ErpAiDb::tableExists('rateb_website_form_submissions')
            ? (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM rateb_website_form_submissions WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0)) : 0;
        return ErpAiDb::ok([
            'cms_pages' => $pages,
            'cms_leads' => $leads,
            'form_submissions' => $subs,
            'as_of' => date('c'),
            'data_source' => 'live_tenant',
        ]);
    }

    private static function assets(string $kind, array $args, int $companyId): array
    {
        $eam = ErpAiDb::tableExists('rateb_eam_assets');
        $legacy = ErpAiDb::tableExists('rateb_assets');
        if ($kind === 'assets_summary') {
            $table = $eam ? 'rateb_eam_assets' : ($legacy ? 'rateb_assets' : '');
            if ($table === '') {
                return ErpAiDb::ok(['total' => 0, 'note' => 'table_unavailable']);
            }
            $total = (int) ((ErpAiDb::query('SELECT COUNT(*) AS c FROM ' . $table . ' WHERE company_id = :cid', ['cid' => $companyId])[0]['c'] ?? 0));
            return ErpAiDb::ok(['total' => $total, 'table' => $table, 'as_of' => date('c')]);
        }
        if ($kind === 'assets_get') {
            $id = (int) ($args['id'] ?? 0);
            if ($id < 1) return ErpAiDb::fail('invalid_id');
            if ($eam) {
                $rows = ErpAiDb::query('SELECT * FROM rateb_eam_assets WHERE id = :id AND company_id = :cid LIMIT 1', ['id' => $id, 'cid' => $companyId]);
                if ($rows !== []) return ErpAiDb::ok($rows[0]);
            }
            if ($legacy) {
                $rows = ErpAiDb::query('SELECT * FROM rateb_assets WHERE id = :id AND company_id = :cid LIMIT 1', ['id' => $id, 'cid' => $companyId]);
                if ($rows !== []) return ErpAiDb::ok($rows[0]);
            }
            return ErpAiDb::fail('not_found');
        }
        $limit = ErpAiDb::clampLimit($args['limit'] ?? 50);
        if ($eam) {
            $rows = ErpAiDb::query(
                'SELECT id, asset_tag, name, status, location_id, created_at FROM rateb_eam_assets
                 WHERE company_id = :cid ORDER BY id DESC LIMIT ' . $limit,
                ['cid' => $companyId]
            );
            return ErpAiDb::ok(['rows' => $rows, 'table' => 'rateb_eam_assets']);
        }
        if ($legacy) {
            $rows = ErpAiDb::query(
                'SELECT id, asset_code, name, status, created_at FROM rateb_assets
                 WHERE company_id = :cid ORDER BY id DESC LIMIT ' . $limit,
                ['cid' => $companyId]
            );
            return ErpAiDb::ok(['rows' => $rows, 'table' => 'rateb_assets']);
        }
        return ErpAiDb::ok(['rows' => [], 'note' => 'table_unavailable']);
    }

    private static function pendingEap(array $args, int $companyId): array
    {
        if (!ErpAiDb::tableExists('rateb_eap_requests')) {
            return ErpAiDb::ok(['rows' => [], 'note' => 'table_unavailable']);
        }
        $limit = ErpAiDb::clampLimit($args['limit'] ?? 50);
        $rows = ErpAiDb::query(
            "SELECT id, request_no, template_id, status, requester_id, entity_type, entity_id, created_at
             FROM rateb_eap_requests
             WHERE company_id = :cid AND status IN ('pending','Pending','in_progress','In Progress','open','Open')
             ORDER BY id DESC LIMIT " . $limit,
            ['cid' => $companyId]
        );
        return ErpAiDb::ok(['rows' => $rows]);
    }
}
