<?php
declare(strict_types=1);

function control_rateb_erp_root_candidates(): array
{
    $candidates = [];
    $fromBridge = dirname(__DIR__, 3) . '/rateb-erp';
    $candidates[] = $fromBridge;

  // control-panel/ may be the deploy root on some hosts
    $candidates[] = dirname(__DIR__, 2) . '/rateb-erp';

    $docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($docRoot !== '') {
        $candidates[] = rtrim($docRoot, '/\\') . '/rateb-erp';
        $candidates[] = dirname(rtrim($docRoot, '/\\')) . '/rateb-erp';
    }

    $unique = [];
    foreach ($candidates as $path) {
        $norm = str_replace('\\', '/', $path);
        if (!in_array($norm, $unique, true)) {
            $unique[] = $norm;
        }
    }
    return $unique;
}

function control_rateb_erp_root_path(): string
{
    foreach (control_rateb_erp_root_candidates() as $path) {
        if (is_file($path . '/public/index.php')) {
            return $path;
        }
    }
    return control_rateb_erp_root_candidates()[0] ?? (dirname(__DIR__, 3) . '/rateb-erp');
}

function control_rateb_erp_is_installed(): bool
{
    return is_file(control_rateb_erp_root_path() . '/public/index.php');
}

function control_rateb_erp_diagnostic(): array
{
    $resolved = control_rateb_erp_root_path();
    $candidates = control_rateb_erp_root_candidates();
    return [
        'installed' => control_rateb_erp_is_installed(),
        'resolved' => $resolved,
        'index_exists' => is_file($resolved . '/public/index.php'),
        'candidates' => $candidates,
        'document_root' => (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''),
    ];
}

/** Base CP app URL without &route= (used as RATEB_CP_APP_URL for rateb_url()). */
function control_rateb_erp_app_base_url(): string
{
    return function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/rateb-erp-app.php')
        : '/control-panel/pages/control/rateb-erp-app.php?control=1';
}

/**
 * Open ERP module inside Control Panel (embedded iframe-style via rateb-erp-app.php).
 */
function control_rateb_erp_app_url(string $route = 'admin'): string
{
    $route = trim($route, '/');
    if ($route === '') {
        $route = 'admin';
    }
    $base = control_rateb_erp_app_base_url();
    $sep = strpos($base, '?') !== false ? '&' : '?';
    return $base . $sep . 'route=' . rawurlencode($route);
}

/** Direct canonical ERP URL — always /rateb-erp/public/{route} on rateb.sa (never clean /admin). */
function control_rateb_erp_public_url(string $route = 'admin'): string
{
    $route = trim($route, '/');
    if ($route === '') {
        $route = 'admin';
    }
    if (function_exists('rateb_public_url')) {
        return rateb_public_url($route);
    }
    $site = rtrim(defined('SITE_URL') ? (string) SITE_URL : '', '/');
    if ($site === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $site = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'rateb.sa');
    }
    $host = strtolower(parse_url($site, PHP_URL_HOST) ?: '');
    $atRoot = in_array($host, ['rateb.sa', 'www.rateb.sa'], true);
    $marketing = $route === 'site' || str_starts_with($route, 'site/') || str_starts_with($route, 'locale/');
    if ($atRoot && $marketing) {
        if ($route === 'site') {
            return $site . '/';
        }

        return $site . '/' . $route;
    }
    $prefix = '/rateb-erp/public';

    return rtrim($site, '/') . $prefix . '/' . $route;
}

function control_rateb_erp_ensure_root(): string
{
    if (defined('RATEB_ROOT')) {
        return (string) RATEB_ROOT;
    }
    $erpRoot = control_rateb_erp_root_path();
    define('RATEB_ROOT', str_replace('\\', '/', realpath($erpRoot) ?: $erpRoot));
    return (string) RATEB_ROOT;
}

/** Bootstrap ERP autoloader for control-panel API calls (reset, seed, etc.). */
function control_rateb_erp_bootstrap_minimal(): void
{
    $erpRoot = control_rateb_erp_ensure_root();
    if (!defined('RATEB_ENV_NO_SESSION')) {
        define('RATEB_ENV_NO_SESSION', true);
    }
    if (!defined('RATEB_ERP_DEPLOYMENT_MODE')) {
        define('RATEB_ERP_DEPLOYMENT_MODE', 'dedicated');
    }
    if (!class_exists(\Rateb\App\Core\Bootstrap::class, false)) {
        require_once $erpRoot . '/app/Core/Bootstrap.php';
    }
    \Rateb\App\Core\Bootstrap::initMinimal($erpRoot);
}

function control_rateb_erp_schema_ready(): bool
{
    $test = control_rateb_erp_db_test();
    return $test['ok'] && $test['schema'];
}

/** @return array{ok:bool,schema:bool,db:string,error:string} */
function control_rateb_erp_db_test(): array
{
    $result = ['ok' => false, 'schema' => false, 'db' => control_rateb_erp_db_name(), 'error' => ''];
    if (!control_rateb_erp_is_installed()) {
        $result['error'] = 'ERP files missing on server.';
        return $result;
    }
    try {
        control_rateb_erp_ensure_root();
        require_once RATEB_ROOT . '/app/Core/Database.php';
        $agencyId = control_rateb_erp_resolve_agency_id();
        $cfg = $agencyId > 0 ? control_rateb_erp_agency_db_config($agencyId) : null;
        if ($cfg !== null) {
            \Rateb\App\Core\Database::useConnectionOverride($cfg);
        } elseif ($agencyId > 0) {
            $result['error'] = 'Agency ERP database not configured.';
            if (class_exists(\Rateb\App\Core\Database::class)) {
                \Rateb\App\Core\Database::clearConnectionOverride();
            }

            return $result;
        } else {
            require_once RATEB_ROOT . '/config/database.php';
        }
        $pdo = \Rateb\App\Core\Database::connection();
        $result['ok'] = true;
        $result['db'] = \Rateb\App\Core\Database::resolvedDatabaseName();
        $stmt = $pdo->query("SHOW TABLES LIKE 'rateb_companies'");
        $result['schema'] = $stmt !== false && $stmt->rowCount() > 0;
        if ($cfg !== null) {
            \Rateb\App\Core\Database::clearConnectionOverride();
        }
    } catch (Throwable $e) {
        if (class_exists(\Rateb\App\Core\Database::class)) {
            \Rateb\App\Core\Database::clearConnectionOverride();
        }
        $result['error'] = $e->getMessage();
        error_log('RATEB ERP DB test: ' . $e->getMessage());
    }
    return $result;
}

function control_rateb_erp_migrate_page_url(): string
{
    return function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/rateb-erp-migrate.php')
        : '/control-panel/pages/control/rateb-erp-migrate.php?control=1';
}

function control_rateb_erp_branches_hub_page_url(): string
{
    return function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/rateb-erp-branches.php')
        : '/control-panel/pages/control/rateb-erp-branches.php?control=1';
}

function control_rateb_erp_agency_site_origin(int $agencyId): string
{
    if ($agencyId < 1) {
        return '';
    }
    $lookup = dirname(__DIR__, 3) . '/config/env/agency_lookup.php';
    if (is_file($lookup)) {
        require_once $lookup;
    }
    if (!function_exists('rateb_lookup_agency_by_id')) {
        return '';
    }
    $row = rateb_lookup_agency_by_id($agencyId);
    if ($row === null) {
        return '';
    }
    $site = rtrim(trim((string) ($row['site_url'] ?? '')), '/');
    if ($site === '' || !preg_match('#^https?://#i', $site)) {
        return '';
    }

    return $site;
}

function control_rateb_erp_branch_portal_url(int $branchId, ?array $branchRow = null): string
{
    $path = '';
    if (is_array($branchRow)) {
        $slug = trim((string) ($branchRow['company_slug'] ?? ''));
        $code = trim((string) ($branchRow['code'] ?? ''));
        if ($slug !== '' && $code !== '') {
            $path = 'login?company=' . rawurlencode($slug) . '&branch=' . rawurlencode($code);
        }
    }
    if ($path === '') {
        if ($branchId < 1) {
            $path = 'login';
        } else {
            $path = 'login?branch_id=' . $branchId;
        }
    }
    $agencyId = control_rateb_erp_resolve_agency_id();
    $agencyOrigin = control_rateb_erp_agency_site_origin($agencyId);
    if ($agencyOrigin !== '') {
        return rtrim($agencyOrigin, '/') . '/rateb-erp/public/' . ltrim($path, '/');
    }
    if (function_exists('rateb_branch_portal_url')) {
        control_rateb_erp_ensure_root();
        require_once RATEB_ROOT . '/config/app.php';

        return rateb_branch_portal_url($branchId, $branchRow);
    }

    return control_rateb_erp_public_url($path);
}

function control_rateb_erp_branch_manage_url(int $companyId = 0): string
{
    $url = control_rateb_erp_branches_hub_page_url();
    $sep = strpos($url, '?') !== false ? '&' : '?';
    $url .= $sep . 'platform=1';
    if ($companyId > 0) {
        $url .= '&company_id=' . $companyId . '#company-branches-' . $companyId;
    }

    return $url;
}

/** Platform companies hub (rateb.sa) — ignore session agency when company_id is set without agency_id. */
function control_rateb_erp_is_platform_branch_context(): bool
{
    if (isset($_GET['platform']) && (string) $_GET['platform'] === '1') {
        return true;
    }
    if (isset($_POST['platform']) && (string) $_POST['platform'] === '1') {
        return true;
    }
    $hasAgencyParam = array_key_exists('agency_id', $_GET) || array_key_exists('agency_id', $_POST);
    if ($hasAgencyParam) {
        return false;
    }
    $companyId = (int) ($_GET['company_id'] ?? $_POST['company_id'] ?? 0);

    return $companyId > 0;
}

function control_rateb_erp_resolve_agency_id(): int
{
    if (control_rateb_erp_is_platform_branch_context()) {
        return 0;
    }
    if (array_key_exists('agency_id', $_POST) || array_key_exists('agency_id', $_GET)) {
        return (int) ($_POST['agency_id'] ?? $_GET['agency_id'] ?? 0);
    }

    return (int) ($_SESSION['control_agency_id'] ?? 0);
}

/** Platform ERP database — ignores control-panel agency session. */
function control_rateb_erp_pdo_platform(): ?\PDO
{
    if (!control_rateb_erp_is_installed()) {
        return null;
    }
    try {
        control_rateb_erp_ensure_root();
        require_once RATEB_ROOT . '/config/database.php';
        require_once RATEB_ROOT . '/app/Core/Database.php';
        \Rateb\App\Core\Database::clearConnectionOverride();
        return \Rateb\App\Core\Database::connection();
    } catch (\Throwable $e) {
        if (class_exists(\Rateb\App\Core\Database::class)) {
            \Rateb\App\Core\Database::clearConnectionOverride();
        }
        error_log('control_rateb_erp_pdo_platform: ' . $e->getMessage());

        return null;
    }
}

/** First company row inside an agency ERP database (dedicated = one company). */
function control_rateb_erp_agency_primary_company_id(int $agencyId): int
{
    if ($agencyId < 1) {
        return 0;
    }
    $pdo = control_rateb_erp_pdo_for_agency($agencyId);
    if (!$pdo) {
        return 0;
    }
    try {
        $stmt = $pdo->query('SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1');
        if ($stmt === false) {
            return 0;
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? (int) ($row['id'] ?? 0) : 0;
    } catch (\Throwable $e) {
        error_log('control_rateb_erp_agency_primary_company_id: ' . $e->getMessage());

        return 0;
    }
}

function control_rateb_erp_reapply_agency_db(int $agencyId): void
{
    if ($agencyId < 1) {
        return;
    }
    $cfg = control_rateb_erp_agency_db_config($agencyId);
    if ($cfg === null) {
        return;
    }
    control_rateb_erp_ensure_root();
    require_once RATEB_ROOT . '/app/Core/Database.php';
    \Rateb\App\Core\Database::useConnectionOverride($cfg);
}

function control_rateb_erp_agency_admin_url(int $agencyId): string
{
    if ($agencyId < 1) {
        return control_rateb_erp_public_url('admin');
    }

    return control_rateb_erp_public_url('admin') . '?agency_id=' . $agencyId;
}

function control_rateb_erp_agencies_page_url(int $countryId = 0): string
{
    $url = function_exists('control_panel_page_with_control')
        ? control_panel_page_with_control('control/agencies.php')
        : '/control-panel/pages/control/agencies.php?control=1';
    if ($countryId > 0) {
        $url .= '&country_id=' . $countryId;
    }

    return $url;
}

/** Branches hub scoped to an agency ERP database. */
function control_rateb_erp_agency_branch_manage_url(int $agencyId, int $companyId = 0): string
{
    if ($agencyId < 1) {
        return control_rateb_erp_branch_manage_url($companyId);
    }
    if ($companyId < 1) {
        $companyId = control_rateb_erp_agency_primary_company_id($agencyId);
    } else {
        $liveId = control_rateb_erp_agency_primary_company_id($agencyId);
        if ($liveId > 0 && $liveId !== $companyId) {
            $companyId = $liveId;
        }
    }
    $url = control_rateb_erp_branches_hub_page_url();
    $url .= (strpos($url, '?') !== false ? '&' : '?') . 'agency_id=' . $agencyId;
    if ($companyId > 0) {
        $url .= '&company_id=' . $companyId . '#company-branches-' . $companyId;
    }

    return $url;
}

function control_rateb_erp_agency_label(int $agencyId): string
{
    if ($agencyId < 1) {
        return '';
    }
    $lookup = dirname(__DIR__, 3) . '/config/env/agency_lookup.php';
    if (!is_file($lookup)) {
        return '';
    }
    require_once $lookup;
    if (!function_exists('rateb_lookup_agency_by_id')) {
        return '';
    }
    $row = rateb_lookup_agency_by_id($agencyId);
    if ($row === null) {
        return '';
    }
    $name = trim((string) ($row['name'] ?? $row['agency_name'] ?? ''));

    return $name !== '' ? $name : ('Agency #' . $agencyId);
}

function control_rateb_erp_pdo(): ?\PDO
{
    if (function_exists('control_rateb_erp_is_platform_branch_context') && control_rateb_erp_is_platform_branch_context()) {
        return control_rateb_erp_pdo_platform();
    }
    $agencyId = control_rateb_erp_resolve_agency_id();
    if ($agencyId > 0) {
        return control_rateb_erp_pdo_for_agency($agencyId);
    }
    if (!control_rateb_erp_schema_ready()) {
        return null;
    }
    control_rateb_erp_ensure_root();
    require_once RATEB_ROOT . '/config/database.php';
    require_once RATEB_ROOT . '/app/Core/Database.php';
    \Rateb\App\Core\Database::clearConnectionOverride();

    return \Rateb\App\Core\Database::connection();
}

function control_rateb_erp_load_branch_stack(): void
{
    control_rateb_erp_ensure_root();
    require_once RATEB_ROOT . '/config/app.php';
    require_once RATEB_ROOT . '/config/database.php';
    require_once RATEB_ROOT . '/app/Core/Database.php';
    require_once RATEB_ROOT . '/app/Core/Model.php';
    require_once RATEB_ROOT . '/app/Core/TenantContext.php';
    require_once RATEB_ROOT . '/app/models/Company.php';
    require_once RATEB_ROOT . '/app/models/Entities.php';
    require_once RATEB_ROOT . '/app/services/BranchService.php';
    $agencyId = control_rateb_erp_resolve_agency_id();
    if ($agencyId > 0) {
        control_rateb_erp_reapply_agency_db($agencyId);
    } elseif (function_exists('control_rateb_erp_is_platform_branch_context') && control_rateb_erp_is_platform_branch_context()) {
        \Rateb\App\Core\Database::clearConnectionOverride();
    }
}

/** @return list<array{id:int,name:string}> */
function control_rateb_erp_platform_companies(): array
{
    $pdo = control_rateb_erp_pdo();
    if (!$pdo) {
        return [];
    }
    try {
        $stmt = $pdo->query('SELECT id, name FROM rateb_companies ORDER BY name ASC LIMIT 500');
        if ($stmt === false) {
            return [];
        }
        $rows = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $rows[] = ['id' => $id, 'name' => trim((string) ($row['name'] ?? ''))];
        }

        return $rows;
    } catch (Throwable $e) {
        error_log('control_rateb_erp_platform_companies: ' . $e->getMessage());

        return [];
    }
}

/** @return array<int, array<string, mixed>> */
function control_rateb_erp_companies_branch_overview(): array
{
    $pdo = control_rateb_erp_pdo();
    if (!$pdo) {
        return [];
    }
    try {
        $stmt = $pdo->query(
            'SELECT c.id, c.name, c.slug, c.status, c.branch_limit, c.plan_id,
                    COUNT(b.id) AS branch_count
             FROM rateb_companies c
             LEFT JOIN rateb_branches b ON b.company_id = c.id
             GROUP BY c.id, c.name, c.slug, c.status, c.branch_limit, c.plan_id
             ORDER BY c.id DESC'
        );
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        if (!is_array($rows) || $rows === []) {
            return [];
        }
        control_rateb_erp_load_branch_stack();
        $svc = new \Rateb\App\Services\BranchService();
        foreach ($rows as &$row) {
            $cid = (int) ($row['id'] ?? 0);
            $stats = $svc->stats($cid);
            $row['branch_count'] = (int) ($stats['count'] ?? $row['branch_count'] ?? 0);
            $row['branch_limit_effective'] = (int) ($stats['limit'] ?? 0);
            $row['can_add_branch'] = $svc->canAddBranch($cid);
        }
        unset($row);
        return $rows;
    } catch (\Throwable $e) {
        error_log('control_rateb_erp_companies_branch_overview: ' . $e->getMessage());
        return [];
    }
}

/** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,pages:int} */
function control_rateb_erp_branch_list(int $companyId, array $opts = []): array
{
    control_rateb_erp_load_branch_stack();
    require_once RATEB_ROOT . '/app/services/PlatformCompanyBranchService.php';

    return \Rateb\App\Services\PlatformCompanyBranchService::listBranches($companyId, $opts);
}

/** @param array<string, mixed> $source */
function control_rateb_erp_branch_list_opts_from_request(array $source = []): array
{
    control_rateb_erp_load_branch_stack();
    require_once RATEB_ROOT . '/app/services/PlatformCompanyBranchService.php';
    $src = $source !== [] ? $source : $_GET;

    return \Rateb\App\Services\PlatformCompanyBranchService::listOptionsFromRequest($src);
}

/** @deprecated Use control_rateb_erp_branch_list() */
function control_rateb_erp_company_branches(int $companyId): array
{
    $result = control_rateb_erp_branch_list($companyId, ['per_page' => 500, 'page' => 1, 'archive' => 'all']);

    return $result['items'];
}

/** @return array{ok:bool, error?:string, noop?:bool} */
function control_rateb_erp_branch_archive(int $companyId, int $branchId): array
{
    control_rateb_erp_load_branch_stack();
    require_once RATEB_ROOT . '/app/services/PlatformCompanyBranchService.php';

    return \Rateb\App\Services\PlatformCompanyBranchService::archiveBranch($companyId, $branchId);
}

/** @return array{ok:bool, error?:string, noop?:bool} */
function control_rateb_erp_branch_restore(int $companyId, int $branchId): array
{
    control_rateb_erp_load_branch_stack();
    require_once RATEB_ROOT . '/app/services/PlatformCompanyBranchService.php';

    return \Rateb\App\Services\PlatformCompanyBranchService::restoreBranch($companyId, $branchId);
}

/** @param array<int, int|string> $branchIds @return array{ok:bool, success:int, failed:int, errors:array<int,string>} */
function control_rateb_erp_branch_bulk(int $companyId, array $branchIds, string $action): array
{
    control_rateb_erp_load_branch_stack();
    require_once RATEB_ROOT . '/app/services/PlatformCompanyBranchService.php';

    return \Rateb\App\Services\PlatformCompanyBranchService::bulkBranchAction($companyId, $branchIds, $action);
}

function control_rateb_erp_company_set_branch_limit(int $companyId, int $limit): bool
{
    $pdo = control_rateb_erp_pdo();
    if (!$pdo || $companyId < 1) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE rateb_companies SET branch_limit = :lim WHERE id = :id');
    return $stmt->execute(['lim' => max(0, $limit), 'id' => $companyId]);
}

/** @return array{ok:bool, branch?:array<string,mixed>, portal_url?:string, error?:string} */
function control_rateb_erp_branch_create(int $companyId, array $data): array
{
    control_rateb_erp_load_branch_stack();
    require_once RATEB_ROOT . '/app/services/PlatformCompanyBranchService.php';
    $result = \Rateb\App\Services\PlatformCompanyBranchService::createBranch($companyId, $data);
    if (!empty($result['ok']) && isset($result['branch'])) {
        $bid = (int) ($result['branch']['id'] ?? 0);
        if ($bid > 0) {
            $result['portal_url'] = control_rateb_erp_branch_portal_url($bid, $result['branch']);
        }
    }

    return $result;
}

/** @return array{ok:bool, branch?:array<string,mixed>, error?:string} */
function control_rateb_erp_branch_update(int $companyId, int $branchId, array $data): array
{
    control_rateb_erp_load_branch_stack();
    require_once RATEB_ROOT . '/app/services/PlatformCompanyBranchService.php';

    return \Rateb\App\Services\PlatformCompanyBranchService::updateBranch($companyId, $branchId, $data);
}

/** @return array{ok:bool, error?:string, noop?:bool} */
function control_rateb_erp_branch_set_status(int $companyId, int $branchId, string $status): array
{
    control_rateb_erp_load_branch_stack();
    require_once RATEB_ROOT . '/app/services/PlatformCompanyBranchService.php';

    return \Rateb\App\Services\PlatformCompanyBranchService::setBranchStatus($companyId, $branchId, $status);
}

/** @return array<int, array<string, mixed>> */
function control_rateb_erp_branches_catalog(): array
{
    if (!control_rateb_erp_schema_ready()) {
        return [];
    }
    try {
        control_rateb_erp_ensure_root();
        require_once RATEB_ROOT . '/config/database.php';
        require_once RATEB_ROOT . '/app/Core/Database.php';
        $pdo = \Rateb\App\Core\Database::connection();
        $stmt = $pdo->query(
            'SELECT b.id, b.name, b.code, b.status, b.is_main, b.company_id,
                    c.name AS company_name, c.slug AS company_slug
             FROM rateb_branches b
             INNER JOIN rateb_companies c ON c.id = b.company_id
             ORDER BY c.name ASC, b.is_main DESC, b.name ASC'
        );
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        return is_array($rows) ? $rows : [];
    } catch (\Throwable $e) {
        error_log('control_rateb_erp_branches_catalog: ' . $e->getMessage());
        return [];
    }
}

/** @return array<string, array{href:string,label:string,icon:string,key:string,description?:string,route:string}> */
function control_rateb_erp_nav_links(): array
{
    $routes = [
        'dashboard' => ['route' => 'admin', 'label' => 'Dashboard', 'icon' => 'fa-chart-line', 'description' => 'KPIs, revenue charts, and platform overview.'],
        'companies' => ['route' => 'admin/companies', 'label' => 'Companies', 'icon' => 'fa-building', 'description' => 'Create, activate, suspend, and manage tenant companies.'],
        'companies_approvals' => ['route' => 'admin/oversight/companies-approvals', 'label' => 'Company approvals', 'icon' => 'fa-building-circle-check', 'description' => 'Approve or reject pending company registrations.'],
        'approvals' => ['route' => 'admin/oversight/approvals', 'label' => 'Approvals oversight', 'icon' => 'fa-check-double', 'description' => 'Pending workflow and manager approvals across companies.'],
        'subscriptions' => ['route' => 'admin/subscriptions', 'label' => 'Subscriptions', 'icon' => 'fa-credit-card', 'description' => 'Billing cycles, plans, and subscription status.'],
        'procurement' => ['route' => 'admin/oversight/procurement', 'label' => 'Procurement', 'icon' => 'fa-cart-shopping', 'description' => 'Purchase requests and orders across all companies (read-only oversight).'],
        'inventory' => ['route' => 'admin/oversight/inventory', 'label' => 'Inventory', 'icon' => 'fa-boxes-stacked', 'description' => 'Stock levels, warehouses, and inventory value (read-only oversight).'],
        'suppliers' => ['route' => 'admin/oversight/supplier-evaluations', 'label' => 'Suppliers', 'icon' => 'fa-truck-field', 'description' => 'Supplier evaluations and status oversight.'],
        'assets' => ['route' => 'admin/assets', 'label' => 'Assets', 'icon' => 'fa-toolbox', 'description' => 'Fixed assets and medical equipment registry.'],
        'contracts' => ['route' => 'admin/contracts', 'label' => 'Contracts', 'icon' => 'fa-file-contract', 'description' => 'Healthcare and procurement contracts.'],
        'reports' => ['route' => 'admin/reports', 'label' => 'Reports', 'icon' => 'fa-chart-pie', 'description' => 'Platform analytics and export views.'],
        'accounting' => ['route' => 'admin/accounting', 'label' => 'Accounting', 'icon' => 'fa-calculator', 'description' => 'Full accounting: chart of accounts, journals, invoices, payments, trial balance.'],
        'settings' => ['route' => 'admin/settings', 'label' => 'Settings', 'icon' => 'fa-gear', 'description' => 'System settings, templates, and configuration.'],
    ];

    $links = [];
    foreach ($routes as $key => $item) {
        $links[$key] = [
            'href' => control_rateb_erp_app_url($item['route']),
            'label' => $item['label'],
            'icon' => $item['icon'],
            'key' => $key,
            'route' => $item['route'],
            'description' => $item['description'],
        ];
    }
    return $links;
}

/** @return array{host:string,port:int,user:string,pass:string} */
function control_rateb_erp_agency_resolve_credentials(array $row): array
{
    $host = trim((string) ($row['erp_db_host'] ?? ''));
    if ($host === '') {
        $host = trim((string) ($row['db_host'] ?? ''));
    }
    if ($host === '') {
        $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
    }
    $port = (int) ($row['db_port'] ?? (defined('DB_PORT') ? (int) DB_PORT : 3306));
    $user = trim((string) ($row['erp_db_user'] ?? ''));
    if ($user === '') {
        $user = trim((string) ($row['db_user'] ?? ''));
    }
    if ($user === '') {
        $user = defined('DB_USER') ? (string) DB_USER : '';
    }
    $agencyPass = (string) ($row['erp_db_pass'] ?? '');
    if ($agencyPass === '') {
        $agencyPass = (string) ($row['db_pass'] ?? '');
    }
    $envUser = defined('DB_USER') ? (string) DB_USER : '';
    $envPass = defined('DB_PASS') ? (string) DB_PASS : '';
    $pass = ($user !== '' && $user === $envUser) ? $envPass : ($agencyPass !== '' ? $agencyPass : $envPass);

    return [
        'host' => $host !== '' ? $host : 'localhost',
        'port' => $port > 0 ? $port : 3306,
        'user' => $user,
        'pass' => $pass,
    ];
}

function control_rateb_erp_agency_db_name(int $agencyId): string
{
    if ($agencyId < 1) {
        return '';
    }
    $lookup = dirname(__DIR__, 3) . '/config/env/agency_lookup.php';
    if (!is_file($lookup)) {
        return '';
    }
    require_once $lookup;
    $row = rateb_lookup_agency_by_id($agencyId);
    if ($row === null) {
        return '';
    }

    return trim((string) ($row['erp_db_name'] ?? ''));
}

/** @return array{host:string,port:int,user:string,pass:string,db:string}|null */
function control_rateb_erp_agency_db_config(int $agencyId): ?array
{
    if ($agencyId < 1) {
        return null;
    }
    $lookup = dirname(__DIR__, 3) . '/config/env/agency_lookup.php';
    if (!is_file($lookup)) {
        return null;
    }
    require_once $lookup;
    $row = rateb_lookup_agency_by_id($agencyId);
    if ($row === null) {
        return null;
    }
    $db = trim((string) ($row['erp_db_name'] ?? ''));
    if ($db === '') {
        return null;
    }
    $cred = control_rateb_erp_agency_resolve_credentials($row);

    return [
        'host' => $cred['host'],
        'port' => $cred['port'],
        'user' => $cred['user'],
        'pass' => $cred['pass'],
        'db' => $db,
    ];
}

function control_rateb_erp_pdo_for_agency(int $agencyId): ?\PDO
{
    $cfg = control_rateb_erp_agency_db_config($agencyId);
    if ($cfg === null) {
        return null;
    }
    control_rateb_erp_ensure_root();
    require_once RATEB_ROOT . '/app/Core/Database.php';
    $hosts = array_values(array_unique(array_filter([
        (string) ($cfg['host'] ?? 'localhost'),
        '127.0.0.1',
        'localhost',
    ])));
    foreach ($hosts as $host) {
        $tryCfg = $cfg;
        $tryCfg['host'] = $host;
        try {
            \Rateb\App\Core\Database::useConnectionOverride($tryCfg);
            $pdo = \Rateb\App\Core\Database::connection();
            $stmt = $pdo->query("SHOW TABLES LIKE 'rateb_companies'");
            if ($stmt !== false && $stmt->rowCount() > 0) {
                return $pdo;
            }
            \Rateb\App\Core\Database::clearConnectionOverride();
        } catch (Throwable $e) {
            if (class_exists(\Rateb\App\Core\Database::class)) {
                \Rateb\App\Core\Database::clearConnectionOverride();
            }
        }
    }
    error_log('control_rateb_erp_pdo_for_agency: could not connect agency #' . $agencyId . ' db=' . ($cfg['db'] ?? ''));

    return null;
}

function control_rateb_erp_db_name(): string
{
    $agencyId = control_rateb_erp_resolve_agency_id();
    if ($agencyId > 0) {
        $agencyDb = control_rateb_erp_agency_db_name($agencyId);
        if ($agencyDb !== '') {
            return $agencyDb;
        }
    }
    if (function_exists('rateb_erp_database_name')) {
        return rateb_erp_database_name();
    }
    if (defined('RATEB_ERP_DB_NAME') && (string) RATEB_ERP_DB_NAME !== '') {
        $name = (string) RATEB_ERP_DB_NAME;

        return $name === 'admin-rateb-erp' ? 'admin_rateb-erp' : ($name === 'admin-rateb-erp' ? 'admin_rateb-erp' : $name);
    }
    $env = getenv('RATEB_ERP_DB_NAME');
    $name = ($env !== false && $env !== '') ? (string) $env : (function_exists('rateb_erp_database_name') ? rateb_erp_database_name() : 'admin_rateb-erp');

    return $name === 'admin-rateb-erp' ? 'admin_rateb-erp' : ($name === 'admin-rateb-erp' ? 'admin_rateb-erp' : $name);
}

/** @return array<int, string> */
function control_rateb_erp_run_migrations(): array
{
    control_rateb_erp_ensure_root();
    require_once RATEB_ROOT . '/config/database.php';
    require_once RATEB_ROOT . '/app/Core/Database.php';
    require_once RATEB_ROOT . '/app/services/MigrationService.php';
    require_once RATEB_ROOT . '/app/services/AuthorizationService.php';
    require_once RATEB_ROOT . '/app/services/ErpDatabaseService.php';
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $agencyId = (int) ($_GET['agency_id'] ?? ($_SESSION['control_agency_id'] ?? 0));
    $cfg = $agencyId > 0 ? control_rateb_erp_agency_db_config($agencyId) : null;
    if ($cfg !== null) {
        return (new \Rateb\App\Services\MigrationService())->runAllForDatabase($cfg);
    }

    return (new \Rateb\App\Services\ErpDatabaseService())->fixErpDatabase();
}

/** @return array{erp:array<string,mixed>,control_panel:array<string,mixed>} */
function control_rateb_erp_db_diagnose(): array
{
    // Need ERP autoload (SchemaDiagnosticService) — require_once alone is not enough.
    control_rateb_erp_bootstrap_minimal();
    require_once RATEB_ROOT . '/config/database.php';
    require_once RATEB_ROOT . '/app/services/ErpDatabaseService.php';
    $svc = new \Rateb\App\Services\ErpDatabaseService();

    return [
        'erp' => $svc->diagnoseErp(),
        'control_panel' => $svc->diagnoseControlPanel(),
    ];
}

/**
 * Wipe agency ERP business data (preserve login passwords). Used from Control Panel → Agencies.
 *
 * @return array<string, mixed>
 */
function control_rateb_erp_reset_agency_data(int $agencyId, ?int $platformCompanyId = null, string $confirm = ''): array
{
    control_rateb_erp_bootstrap_minimal();
    $lookup = dirname(__DIR__, 3) . '/config/env/agency_lookup.php';
    if (is_file($lookup)) {
        require_once $lookup;
    }
    require_once RATEB_ROOT . '/config/database.php';
    require_once RATEB_ROOT . '/app/Core/Database.php';

    if ($agencyId < 1) {
        throw new InvalidArgumentException('Invalid agency id');
    }
    $agency = function_exists('rateb_lookup_agency_by_id') ? rateb_lookup_agency_by_id($agencyId) : null;
    if ($agency === null) {
        throw new RuntimeException('Agency not found');
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(600);
    }

    $override = $platformCompanyId !== null && $platformCompanyId > 0 ? $platformCompanyId : null;
    $linked = (int) ($agency['erp_company_id'] ?? 0);
    if ($linked > 0) {
        $override = $linked;
    }

    return (new \Rateb\App\Services\AgencyErpMigrationService())->resetAgencyData($agency, $override);
}

/**
 * Restore dedicated ERP admin login to admin / 123456 without wiping company data.
 *
 * @return array<string, mixed>
 */
function control_rateb_erp_restore_admin_login(int $agencyId): array
{
    control_rateb_erp_bootstrap_minimal();
    $lookup = dirname(__DIR__, 3) . '/config/env/agency_lookup.php';
    if (is_file($lookup)) {
        require_once $lookup;
    }
    require_once RATEB_ROOT . '/config/database.php';
    require_once RATEB_ROOT . '/app/Core/Database.php';

    if ($agencyId < 1) {
        throw new InvalidArgumentException('Invalid agency id');
    }
    $agency = function_exists('rateb_lookup_agency_by_id') ? rateb_lookup_agency_by_id($agencyId) : null;
    if ($agency === null) {
        throw new RuntimeException('Agency not found');
    }
    if (trim((string) ($agency['erp_db_name'] ?? '')) === '') {
        throw new RuntimeException('No ERP database — run Provision ERP first');
    }

    return (new \Rateb\App\Services\AgencyErpMigrationService())->restoreDedicatedAdminLogin($agency);
}

function control_rateb_erp_assets_base_url(): string
{
    $site = rtrim(defined('SITE_URL') ? (string) SITE_URL : '', '/');
    if ($site === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $site = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $assetsPrefix = function_exists('rateb_erp_assets_prefix') ? rateb_erp_assets_prefix() : '/rateb-erp/public';

    return $site . $assetsPrefix . '/assets';
}
