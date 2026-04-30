<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/agencies.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/agencies.php`.
 */
/**
 * Control Panel API: Agencies (control_agencies table)
 * Requires control panel session.
 */
// EN: Force clean JSON output in API mode (no HTML/PHP warning leakage).
// AR: فرض إخراج JSON نظيف في وضع API (بدون تسريب تحذيرات PHP/HTML).
ini_set('display_errors', 0); // Prevent PHP warnings from breaking JSON response
ob_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../includes/control-api-same-origin-cors.php';
applyControlApiSameOriginCors();
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../../core/TenantExecutionContext.php';
require_once __DIR__ . '/../../../core/query/QueryGateway.php';
require_once __DIR__ . '/../../../admin/core/EventBus.php';
require_once __DIR__ . '/../../../admin/core/ControlCenterAccess.php';
require_once __DIR__ . '/../../../admin/core/ProvisioningService.php';
require_once __DIR__ . '/agency-db-helper.php';
ini_set('display_errors', 0);
error_reporting(0);

function jsonOut($data) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

// EN: Guard endpoint so only authenticated control-panel users can access it.
// AR: حماية نقطة النهاية للسماح فقط لمستخدمي لوحة التحكم المصادق عليهم.
$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    jsonOut(['success' => false, 'message' => 'Unauthorized']);
}
require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('view_control_agencies')) {
    jsonOut(['success' => false, 'message' => 'Access denied']);
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) {
    jsonOut(['success' => false, 'message' => 'Database unavailable']);
}
QueryGateway::setConnection($ctrl);

if (!defined('ADMIN_CONTROL_MODE')) {
    define('ADMIN_CONTROL_MODE', true);
}

function isControlSuperAdmin(): bool {
    if (class_exists('ControlCenterAccess')) {
        return ControlCenterAccess::role() === ControlCenterAccess::SUPER_ADMIN;
    }
    $u = strtolower(trim((string) ($_SESSION['control_username'] ?? '')));
    return $u === 'admin';
}

// EN: Set tenant execution context for downstream queries/events.
// AR: ضبط سياق تنفيذ المستأجر للاستعلامات والأحداث اللاحقة.
function setTenantContextById(int $tenantId): void {
    try {
        if ($tenantId > 0) {
            TenantExecutionContext::setTenant($tenantId);
        } else {
            TenantExecutionContext::setSystemContext();
        }
    } catch (Throwable $e) {
        // Context might already be locked by request bootstrap.
        TenantExecutionContext::markSystemContext(true);
    }
}

function requestUserId(): ?int {
    if (isset($_SESSION['control_admin_id']) && (int) $_SESSION['control_admin_id'] > 0) {
        return (int) $_SESSION['control_admin_id'];
    }
    if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
        return (int) $_SESSION['user_id'];
    }
    return null;
}

function eventMeta(array $meta = []): array {
    $base = [
        'request_id' => getRequestId(),
        'user_id' => requestUserId(),
        'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'source' => 'control_agencies_api',
        'query' => null,
        'mode' => 'SAFE',
        'duration_ms' => null,
        'action' => null,
    ];
    return array_merge($base, $meta);
}

function agencyById(int $agencyId): ?array {
    if ($agencyId <= 0) {
        return null;
    }
    return qOne("SELECT * FROM control_agencies WHERE id = ? LIMIT 1", [$agencyId]);
}

function agencyTenantId(int $agencyId): int {
    $row = agencyById($agencyId);
    if (!$row) {
        return 0;
    }
    return (int) ($row['tenant_id'] ?? 0);
}

function setTenantContextByAgencyId(int $agencyId): int {
    $tenantId = agencyTenantId($agencyId);
    try {
        if ($tenantId > 0) {
            TenantExecutionContext::setTenant($tenantId);
            return $tenantId;
        }
        TenantExecutionContext::setSystemContext();
        return 0;
    } catch (Throwable $e) {
        TenantExecutionContext::markSystemContext(true);
        return $tenantId > 0 ? $tenantId : 0;
    }
}

// EN: Self-heal schema to ensure agency↔tenant relation column/index exists.
// AR: إصلاح المخطط تلقائياً لضمان وجود عمود/فهرس ربط الوكالة بالمستأجر.
function ensureAgencyTenantLinkColumn(): void {
    $tenantCol = qOne("SHOW COLUMNS FROM control_agencies LIKE 'tenant_id'");
    if (!$tenantCol) {
        qStmt("ALTER TABLE control_agencies ADD COLUMN tenant_id INT NULL");
        qStmt("ALTER TABLE control_agencies ADD INDEX idx_control_agencies_tenant_id (tenant_id)");
    } else {
        $idx = qOne("SHOW INDEX FROM control_agencies WHERE Key_name = 'idx_control_agencies_tenant_id'");
        if (!$idx) {
            qStmt("ALTER TABLE control_agencies ADD INDEX idx_control_agencies_tenant_id (tenant_id)");
        }
    }
}

function securityBlock(string $message, array $meta = []): void {
    emitEvent('SECURITY_BLOCK', 'warn', $message, eventMeta($meta));
}

function qStmt(string $sql, array $params = []) {
    return QueryGateway::execute($sql, $params);
}

function qAll(string $sql, array $params = []): array {
    $st = qStmt($sql, $params);
    $res = $st->get_result();
    return $res ? ($res->fetch_all(MYSQLI_ASSOC) ?: []) : [];
}

function qOne(string $sql, array $params = []): ?array {
    $st = qStmt($sql, $params);
    $res = $st->get_result();
    if (!$res) return null;
    $row = $res->fetch_assoc();
    return $row ?: null;
}

// EN: Bootstrap tenant-aware request state before handling CRUD actions.
// AR: تهيئة حالة الطلب المرتبطة بالمستأجر قبل تنفيذ عمليات CRUD.
ensureAgencyTenantLinkColumn();
$requestedAgency = (int) ($_GET['agency_id'] ?? 0);
if ($requestedAgency > 0) {
    setTenantContextByAgencyId($requestedAgency);
} else {
    TenantExecutionContext::setSystemContext();
}

$chk = qOne("SHOW TABLES LIKE 'control_agencies'");
if (!$chk) {
    jsonOut(['success' => false, 'message' => 'control_agencies table not found']);
}

$allowedCountryIds = getControlPanelCountryScopeIds($ctrl);

// Check if country_id column exists
$hasCountryId = false;
$cols = qOne("SHOW COLUMNS FROM control_agencies LIKE 'country_id'");
if ($cols) $hasCountryId = true;

// Check if renewal_date / is_suspended columns exist
$hasRenewalDate = false;
$hasIsSuspended = false;
$cols2 = qAll("SHOW COLUMNS FROM control_agencies");
if ($cols2) {
    foreach ($cols2 as $c) {
        if ($c['Field'] === 'renewal_date') $hasRenewalDate = true;
        if ($c['Field'] === 'is_suspended') $hasIsSuspended = true;
    }
}

$method = $_SERVER['REQUEST_METHOD'];

function normalizeSlug($value) {
    $slug = strtolower(trim((string)$value));
    $slug = preg_replace('/[_\s]+/', '-', $slug);
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * Create/link tenant with graceful fallback when ProvisioningService cannot run
 * (e.g. missing PDO driver on shared hosting).
 */
function provisionAgencyTenantLink(array $agency, int $agencyId): int
{
    $name = trim((string) ($agency['name'] ?? ('Agency #' . $agencyId)));
    $slug = normalizeSlug((string) ($agency['slug'] ?? $name));
    $host = preg_replace('/[^a-z0-9.-]+/i', '-', $slug !== '' ? $slug : ('agency-' . $agencyId)) . '.agency.local';
    $domain = strtolower((string) $host);
    $dbName = (string) ($agency['db_name'] ?? '');
    $dbHost = (string) ($agency['db_host'] ?? '');
    $dbUser = (string) ($agency['db_user'] ?? '');
    $dbPass = (string) ($agency['db_pass'] ?? '');
    // New/linked agencies should become active by default.
    $status = 'active';

    try {
        $createdTenant = ProvisioningService::createTenant(
            getControlDB(),
            $name,
            $domain,
            [
                'database_name' => $dbName,
                'db_host' => $dbHost,
                'db_user' => $dbUser,
                'db_password' => $dbPass,
                'status' => $status,
            ]
        );
        $tenantId = (int) ($createdTenant['tenant_id'] ?? 0);
        if ($tenantId > 0) {
            return $tenantId;
        }
    } catch (Throwable $e) {
        // fallback below
    }

    // Fallback: create tenant row via QueryGateway only (no DB provisioning).
    $inserted = false;
    $tenantId = 0;
    $attemptedDomain = $domain;
    for ($i = 0; $i < 2 && !$inserted; $i++) {
        if ($i === 1) {
            $attemptedDomain = 'agency-' . $agencyId . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.agency.local';
        }
        try {
            qStmt(
                "INSERT INTO tenants (name, domain, database_name, db_host, db_user, db_password, status, created_at)
                 VALUES (?,?,?,?,?,?,?, NOW())",
                [$name, $attemptedDomain, $dbName, ($dbHost !== '' ? $dbHost : null), $dbUser, $dbPass, $status]
            );
            $idRow = qOne('SELECT LAST_INSERT_ID() AS id');
            $tenantId = (int) ($idRow['id'] ?? 0);
            $inserted = $tenantId > 0;
        } catch (Throwable $e) {
            $inserted = false;
        }
    }
    if ($tenantId <= 0) {
        throw new RuntimeException('Tenant link failed: provisioning unavailable and fallback insert failed');
    }

    emitEvent('TENANT_CREATED', 'warn', 'Tenant row created via fallback link path', eventMeta([
        'tenant_id' => $tenantId,
        'agency_id' => $agencyId,
        'domain' => $attemptedDomain,
        'action' => 'tenant_fallback_create',
    ]));
    return $tenantId;
}

// Run renewal/suspend maintenance before GET (backfill renewal_date, auto-suspend past grace)
if ($method === 'GET' && ($hasRenewalDate || $hasIsSuspended)) {
    if ($hasRenewalDate) {
        @qStmt("UPDATE control_agencies SET renewal_date = DATE_ADD(DATE(COALESCE(created_at, NOW())), INTERVAL 1 YEAR) WHERE renewal_date IS NULL");
    }
    if ($hasIsSuspended) {
        @qStmt("UPDATE control_agencies SET is_suspended = 1 WHERE renewal_date IS NOT NULL AND DATE_ADD(renewal_date, INTERVAL 15 DAY) < CURDATE() AND (is_suspended = 0 OR is_suspended IS NULL)");
    }
}

// GET - list with pagination
if ($method === 'GET') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
    $search = trim($_GET['search'] ?? '');
    $countryId = (int)($_GET['country_id'] ?? 0);
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];
    $types = '';
    if ($allowedCountryIds !== null && !empty($allowedCountryIds)) {
        $ph = implode(',', array_fill(0, count($allowedCountryIds), '?'));
        $where[] = "country_id IN ($ph)";
        $params = array_merge($params, $allowedCountryIds);
        $types .= str_repeat('i', count($allowedCountryIds));
    } elseif ($allowedCountryIds === []) {
        jsonOut(['success' => true, 'list' => [], 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => 0, 'pages' => 0]]);
    }
    if ($hasCountryId && $countryId > 0) {
        $where[] = 'country_id = ?';
        $params[] = $countryId;
        $types .= 'i';
    }
    if ($search !== '') {
        $where[] = '(name LIKE ? OR slug LIKE ? OR site_url LIKE ?)';
        $p = '%' . $ctrl->real_escape_string($search) . '%';
        $params = array_merge($params, [$p, $p, $p]);
        $types .= 'sss';
    }
    $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countSql = "SELECT COUNT(*) as total FROM control_agencies $whereClause";
    $resRow = qOne($countSql, $params);
    $total = (int)($resRow['total'] ?? 0);

    if ($hasCountryId) {
        $sql = "SELECT a.*, c.name as country_name FROM control_agencies a LEFT JOIN control_countries c ON a.country_id = c.id $whereClause ORDER BY a.id DESC LIMIT $limit OFFSET $offset";
    } else {
        $sql = "SELECT * FROM control_agencies $whereClause ORDER BY id DESC LIMIT $limit OFFSET $offset";
    }
    $rows = qAll($sql, $params);

    // Remove db_pass from response (security)
    foreach ($rows as &$row) {
        unset($row['db_pass']);
    }
    unset($row);

    jsonOut([
        'success' => true,
        'list' => $rows,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => (int)ceil($total / $limit)]
    ]);
}

// POST - create (requires add)
if ($method === 'POST') {
    if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('add_control_agency')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $countryId = (int)($input['country_id'] ?? 0);
    if ($hasCountryId && $countryId <= 0) {
        jsonOut(['success' => false, 'message' => 'Country is required']);
    }
    if ($allowedCountryIds !== null && !in_array($countryId, $allowedCountryIds, true)) {
        jsonOut(['success' => false, 'message' => 'You do not have permission to add agencies in this country']);
    }
    $name = trim((string)($input['name'] ?? ''));
    $slugInput = trim((string)($input['slug'] ?? ''));
    $slug = $slugInput !== '' ? normalizeSlug($slugInput) : normalizeSlug($name);
    $dbHost = trim((string)($input['db_host'] ?? 'localhost'));
    $dbPort = (int)($input['db_port'] ?? 3306);
    $dbUser = trim((string)($input['db_user'] ?? ''));
    $dbPass = trim((string)($input['db_pass'] ?? ''));
    $dbName = trim((string)($input['db_name'] ?? ''));
    $siteUrl = trim((string)($input['site_url'] ?? ''));
    $baseUrl = trim((string)($input['base_url'] ?? ''));
    $sortOrder = (int)($input['sort_order'] ?? 0);
    $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;
    $renewalDate = null;
    if ($hasRenewalDate && isset($input['renewal_date']) && trim((string)$input['renewal_date']) !== '') {
        $rd = date_create(trim($input['renewal_date']));
        if ($rd) $renewalDate = $rd->format('Y-m-d');
    }

    if ($name === '') jsonOut(['success' => false, 'message' => 'Name is required']);
    if ($dbUser === '' || $dbPass === '' || $dbName === '') {
        jsonOut(['success' => false, 'message' => 'DB user, pass and name are required']);
    }
    if ($dbPort < 1 || $dbPort > 65535) jsonOut(['success' => false, 'message' => 'DB Port must be between 1 and 65535']);

    try {
        TenantExecutionContext::setSystemContext();
        if ($hasCountryId && $hasRenewalDate) {
            qStmt(
                "INSERT INTO control_agencies (country_id, name, slug, db_host, db_port, db_user, db_pass, db_name, site_url, base_url, is_active, sort_order, renewal_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                [$countryId, $name, $slug, $dbHost, $dbPort, $dbUser, $dbPass, $dbName, $siteUrl, $baseUrl, $isActive, $sortOrder, $renewalDate]
            );
        } elseif ($hasCountryId) {
            qStmt(
                "INSERT INTO control_agencies (country_id, name, slug, db_host, db_port, db_user, db_pass, db_name, site_url, base_url, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                [$countryId, $name, $slug, $dbHost, $dbPort, $dbUser, $dbPass, $dbName, $siteUrl, $baseUrl, $isActive, $sortOrder]
            );
        } elseif ($hasRenewalDate) {
            qStmt(
                "INSERT INTO control_agencies (name, slug, db_host, db_port, db_user, db_pass, db_name, site_url, base_url, is_active, sort_order, renewal_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                [$name, $slug, $dbHost, $dbPort, $dbUser, $dbPass, $dbName, $siteUrl, $baseUrl, $isActive, $sortOrder, $renewalDate]
            );
        } else {
            qStmt(
                "INSERT INTO control_agencies (name, slug, db_host, db_port, db_user, db_pass, db_name, site_url, base_url, is_active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                [$name, $slug, $dbHost, $dbPort, $dbUser, $dbPass, $dbName, $siteUrl, $baseUrl, $isActive, $sortOrder]
            );
        }
        $idRow = qOne('SELECT LAST_INSERT_ID() AS id');
        $newId = (int) ($idRow['id'] ?? 0);
        TenantExecutionContext::setSystemContext();
        $tenantId = provisionAgencyTenantLink([
            'name' => $name,
            'slug' => $slug,
            'db_name' => $dbName,
            'db_host' => $dbHost,
            'db_user' => $dbUser,
            'db_pass' => $dbPass,
            'is_active' => $isActive ? 1 : 0,
        ], $newId);
        if ($tenantId > 0 && $newId > 0) {
            qStmt("UPDATE control_agencies SET tenant_id = ? WHERE id = ?", [$tenantId, $newId]);
            if ($hasIsSuspended) {
                qStmt("UPDATE control_agencies SET is_active = 1, is_suspended = 0 WHERE id = ?", [$newId]);
            } else {
                qStmt("UPDATE control_agencies SET is_active = 1 WHERE id = ?", [$newId]);
            }
            setTenantContextById($tenantId);
            emitEvent('AGENCY_LINKED_TENANT', 'info', 'Agency linked to tenant', eventMeta([
                'tenant_id' => $tenantId,
                'agency_id' => $newId,
                'action' => 'agency_create_link_tenant',
            ]));
        }
        emitEvent('AGENCY_CREATED', 'info', 'Agency created', eventMeta([
            'tenant_id' => $tenantId > 0 ? $tenantId : null,
            'agency_id' => $newId,
            'agency_name' => $name,
            'action' => 'agency_create',
        ]));
        jsonOut(['success' => true, 'id' => $newId, 'message' => 'Agency created']);
    } catch (Throwable $e) {
        jsonOut(['success' => false, 'message' => $e->getMessage()]);
    }
}

// PUT - update (requires edit)
if ($method === 'PUT') {
    if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('edit_control_agency')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid ID']);

        if ($allowedCountryIds !== null) {
            $chkRow = qOne("SELECT country_id FROM control_agencies WHERE id = ? LIMIT 1", [$id]);
            if (!$chkRow) jsonOut(['success' => false, 'message' => 'Agency not found']);
            $agencyCountry = (int)($chkRow['country_id'] ?? 0);
            if (!in_array($agencyCountry, $allowedCountryIds, true)) {
                jsonOut(['success' => false, 'message' => 'You do not have permission to edit this agency']);
            }
        }

        $countryId = (int)($input['country_id'] ?? 0);
        if ($hasCountryId && $countryId <= 0) jsonOut(['success' => false, 'message' => 'Country is required']);
        if ($allowedCountryIds !== null && $countryId > 0 && !in_array($countryId, $allowedCountryIds, true)) {
            jsonOut(['success' => false, 'message' => 'You do not have permission to assign agencies to this country']);
        }
        $nameRaw = trim((string)($input['name'] ?? ''));
        $dbUserRaw = trim((string)($input['db_user'] ?? ''));
        $dbNameRaw = trim((string)($input['db_name'] ?? ''));
        $dbPort = (int)($input['db_port'] ?? 3306);
        if ($nameRaw === '') jsonOut(['success' => false, 'message' => 'Name is required']);
        if ($dbUserRaw === '') jsonOut(['success' => false, 'message' => 'DB User is required']);
        if ($dbNameRaw === '') jsonOut(['success' => false, 'message' => 'DB Name is required']);
        if ($dbPort < 1 || $dbPort > 65535) jsonOut(['success' => false, 'message' => 'DB Port must be between 1 and 65535']);

        $set = [];
        $params = [];
        $name = $nameRaw;
        $slugInput = trim((string)($input['slug'] ?? ''));
        $slugNorm = $slugInput !== '' ? normalizeSlug($slugInput) : normalizeSlug($nameRaw);
        $dbHost = trim((string)($input['db_host'] ?? 'localhost'));
        $dbUser = $dbUserRaw;
        $dbName = $dbNameRaw;
        $siteUrl = trim((string)($input['site_url'] ?? ''));
        $baseUrl = trim((string)($input['base_url'] ?? ''));
        $sortOrder = (int)($input['sort_order'] ?? 0);
        $isActive = isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1;
        $isSuspended = $hasIsSuspended && isset($input['is_suspended']) ? (int)(bool)$input['is_suspended'] : null;
        $renewalDateVal = null;
        if ($hasRenewalDate && isset($input['renewal_date'])) {
            $rd = trim((string)$input['renewal_date']);
            if ($rd === '' || $rd === 'null') {
                $renewalDateVal = null;
            } else {
                $d = date_create($rd);
                if ($d) $renewalDateVal = $d->format('Y-m-d');
            }
        }

        $dbPass = trim((string)($input['db_pass'] ?? ''));
        if ($hasCountryId) {
            $set[] = "country_id=?";
            $params[] = $countryId;
        }
        $set[] = "name=?";
        $params[] = $name;
        $set[] = "slug=?";
        $params[] = $slugNorm;
        $set[] = "db_host=?";
        $params[] = $dbHost;
        $set[] = "db_port=?";
        $params[] = $dbPort;
        $set[] = "db_user=?";
        $params[] = $dbUser;
        if ($dbPass !== '') {
            $set[] = "db_pass=?";
            $params[] = $dbPass;
        }
        $set[] = "db_name=?";
        $params[] = $dbName;
        $set[] = "site_url=?";
        $params[] = $siteUrl;
        $set[] = "base_url=?";
        $params[] = $baseUrl;
        $set[] = "is_active=?";
        $params[] = $isActive;
        $set[] = "sort_order=?";
        $params[] = $sortOrder;
        if ($hasRenewalDate && isset($input['renewal_date'])) {
            $set[] = "renewal_date=?";
            $params[] = $renewalDateVal;
        }
        if ($hasIsSuspended && $isSuspended !== null) {
            $set[] = "is_suspended=?";
            $params[] = (int) $isSuspended;
        }
        $params[] = $id;
        $sql = "UPDATE control_agencies SET " . implode(', ', $set) . " WHERE id=?";
        qStmt($sql, $params);
        $tenantId = setTenantContextByAgencyId($id);
        emitEvent('AGENCY_UPDATED', 'info', 'Agency updated', eventMeta([
            'tenant_id' => $tenantId > 0 ? $tenantId : null,
            'agency_id' => $id,
            'action' => 'agency_update',
        ]));
        jsonOut(['success' => true, 'message' => 'Updated']);
    } catch (Throwable $e) {
        jsonOut(['success' => false, 'message' => 'Update failed: ' . $e->getMessage()]);
    }
}

// PATCH - bulk activate/deactivate/suspend/unsuspend (requires edit)
if ($method === 'PATCH') {
    if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('edit_control_agency')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $ids = $input['agency_ids'] ?? ($input['ids'] ?? []);
    if (empty($ids)) jsonOut(['success' => false, 'message' => 'No IDs']);
    $ids = array_map('intval', $ids);
    if ($allowedCountryIds !== null) {
        $ph = implode(',', $ids);
        $rowsCheck = qAll("SELECT id, country_id FROM control_agencies WHERE id IN ($ph)");
        foreach ($rowsCheck as $row) {
            if (!in_array((int)$row['country_id'], $allowedCountryIds, true)) {
                jsonOut(['success' => false, 'message' => 'You do not have permission to edit one or more of these agencies']);
            }
        }
    }
    $action = trim((string)($input['action'] ?? ''));
    if ($action === '') {
        if (isset($input['is_suspended'])) {
            $action = ((int) $input['is_suspended'] === 1) ? 'suspend' : 'activate';
        } elseif (isset($input['is_active'])) {
            $action = ((int) $input['is_active'] === 1) ? 'activate' : 'deactivate';
        }
    }
    $isSuperAdmin = isControlSuperAdmin();
    $isAdminRole = $isSuperAdmin || hasControlPermission('edit_control_agency') || hasControlPermission(CONTROL_PERM_AGENCIES);
    $bulkStartAt = microtime(true);
    $success = 0;
    $failed = 0;
    $overrideSuspended = !empty($input['override_suspended']);
    if ($overrideSuspended && !$isSuperAdmin) {
        securityBlock('Blocked override_suspended without SUPER_ADMIN', ['action' => $action]);
        jsonOut(['success' => false, 'message' => 'SUPER_ADMIN required for override_suspended']);
    }
    $allowedActions = ['activate', 'deactivate', 'suspend', 'delete', 'sync', 'rebuild_db', 'run_migration', 'test_db_connection', 'open_control_center', 'view_events', 'view_db_status', 'view_query_activity', 'mark_paid', 'repair_tenant_link'];
    if (!in_array($action, $allowedActions, true)) {
        jsonOut(['success' => false, 'message' => 'Unknown bulk action']);
    }
    if (in_array($action, ['delete', 'rebuild_db', 'repair_tenant_link'], true) && !$isSuperAdmin) {
        securityBlock('Blocked privileged bulk action', ['action' => $action]);
        jsonOut(['success' => false, 'message' => 'SUPER_ADMIN required']);
    }
    if (in_array($action, ['activate', 'deactivate', 'suspend', 'sync', 'run_migration', 'test_db_connection', 'mark_paid'], true) && !$isAdminRole) {
        securityBlock('Blocked unauthorized admin action', ['action' => $action]);
        jsonOut(['success' => false, 'message' => 'ADMIN role required']);
    }
    if ($action === 'delete' && strtoupper(trim((string) ($input['confirm'] ?? ''))) !== 'DELETE') {
        securityBlock('Blocked bulk delete without confirmation', ['action' => $action]);
        jsonOut(['success' => false, 'message' => 'Bulk delete requires confirm=DELETE']);
    }
    emitEvent('BULK_OPERATION_STARTED', 'info', 'Bulk operation started', eventMeta([
        'action' => $action,
        'total' => count($ids),
        'success' => 0,
        'failed' => 0,
    ]));
    $itemErrors = [];
    foreach ($ids as $agencyIdRaw) {
        $agencyId = (int) $agencyIdRaw;
        $itemStart = microtime(true);
        try {
            $agency = agencyById($agencyId);
            if (!$agency) {
                throw new RuntimeException('Agency not found');
            }
            $tenantId = (int) ($agency['tenant_id'] ?? 0);
            if ($tenantId > 0) {
                setTenantContextById($tenantId);
            } else {
                TenantExecutionContext::setSystemContext();
            }
            $tenantStatus = '';
            if ($tenantId > 0) {
                $t = qOne("SELECT status FROM tenants WHERE id = ? LIMIT 1", [$tenantId]);
                $tenantStatus = strtolower(trim((string) ($t['status'] ?? '')));
            }
            if ($tenantStatus === 'suspended' && !$overrideSuspended && !in_array($action, ['deactivate', 'suspend'], true)) {
                securityBlock('Blocked action on suspended tenant', [
                    'action' => $action,
                    'tenant_id' => $tenantId,
                    'agency_id' => $agencyId,
                ]);
                throw new RuntimeException('Tenant is suspended. Use override to continue.');
            }
            if ($action === 'activate' || $action === 'mark_paid') {
                if ($hasIsSuspended) {
                    qStmt("UPDATE control_agencies SET is_active = 1, is_suspended = 0 WHERE id = ?", [$agencyId]);
                } else {
                    qStmt("UPDATE control_agencies SET is_active = 1 WHERE id = ?", [$agencyId]);
                }
            } elseif ($action === 'deactivate') {
                if ($hasIsSuspended) {
                    qStmt("UPDATE control_agencies SET is_active = 0, is_suspended = 0 WHERE id = ?", [$agencyId]);
                } else {
                    qStmt("UPDATE control_agencies SET is_active = 0 WHERE id = ?", [$agencyId]);
                }
            } elseif ($action === 'suspend') {
                if ($hasIsSuspended) {
                    qStmt("UPDATE control_agencies SET is_suspended = 1, is_active = 0 WHERE id = ?", [$agencyId]);
                } else {
                    qStmt("UPDATE control_agencies SET is_active = 0 WHERE id = ?", [$agencyId]);
                }
            } elseif ($action === 'delete') {
                qStmt("DELETE FROM control_agencies WHERE id = ?", [$agencyId]);
            } elseif ($action === 'test_db_connection') {
                $dbc = getAgencyDbConnection($agency, (int) ($agency['country_id'] ?? 0));
                if (!($dbc && isset($dbc['conn']) && $dbc['conn'] instanceof mysqli)) {
                    throw new RuntimeException(function_exists('getAgencyDbConnectionLastError') ? getAgencyDbConnectionLastError() : 'Connection failed');
                }
                @$dbc['conn']->close();
            } elseif ($action === 'sync') {
                // Intentional observability-only sync trigger; downstream workers consume events.
            } elseif ($action === 'repair_tenant_link') {
                if ($tenantId <= 0) {
                    $tenantId = provisionAgencyTenantLink($agency, $agencyId);
                    if ($tenantId <= 0) {
                        throw new RuntimeException('Tenant provisioning returned empty tenant_id');
                    }
                    qStmt("UPDATE control_agencies SET tenant_id = ? WHERE id = ?", [$tenantId, $agencyId]);
                    if ($hasIsSuspended) {
                        qStmt("UPDATE control_agencies SET is_active = 1, is_suspended = 0 WHERE id = ?", [$agencyId]);
                    } else {
                        qStmt("UPDATE control_agencies SET is_active = 1 WHERE id = ?", [$agencyId]);
                    }
                    setTenantContextById($tenantId);
                    emitEvent('AGENCY_LINKED_TENANT', 'info', 'Agency tenant link repaired', eventMeta([
                        'tenant_id' => $tenantId,
                        'agency_id' => $agencyId,
                        'action' => 'repair_tenant_link',
                    ]));
                }
            } elseif ($action === 'run_migration') {
                emitEvent('AGENCY_MIGRATION_REQUESTED', 'warn', 'Migration run requested', eventMeta([
                    'tenant_id' => $tenantId > 0 ? $tenantId : null,
                    'agency_id' => $agencyId,
                    'action' => $action,
                ]));
            } elseif ($action === 'rebuild_db') {
                emitEvent('AGENCY_REBUILD_REQUESTED', 'critical', 'DB rebuild requested', eventMeta([
                    'tenant_id' => $tenantId > 0 ? $tenantId : null,
                    'agency_id' => $agencyId,
                    'action' => $action,
                ]));
            } elseif (in_array($action, ['open_control_center', 'view_events', 'view_db_status', 'view_query_activity'], true)) {
                emitEvent('AGENCY_CONTROL_OPENED', 'info', 'Agency control-plane deep link opened', eventMeta([
                    'tenant_id' => $tenantId > 0 ? $tenantId : null,
                    'agency_id' => $agencyId,
                    'action' => $action,
                ]));
            }
            $success++;
            emitEvent('BULK_OPERATION_ITEM_SUCCESS', 'info', 'Bulk operation item success', eventMeta([
                'tenant_id' => $tenantId > 0 ? $tenantId : null,
                'agency_id' => $agencyId,
                'action' => $action,
                'duration_ms' => (int) round((microtime(true) - $itemStart) * 1000),
            ]));
            usleep(120000);
        } catch (Throwable $e) {
            $failed++;
            if (count($itemErrors) < 10) {
                $itemErrors[] = [
                    'agency_id' => $agencyId,
                    'error' => $e->getMessage(),
                ];
            }
            emitEvent('BULK_OPERATION_ITEM_FAILED', 'error', 'Bulk operation item failed', eventMeta([
                'agency_id' => $agencyId,
                'action' => $action,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $itemStart) * 1000),
            ]));
        }
    }
    $durationMs = (int) round((microtime(true) - $bulkStartAt) * 1000);
    emitEvent('BULK_OPERATION_COMPLETED', $failed > 0 ? 'warn' : 'info', 'Bulk operation completed', eventMeta([
        'action' => $action,
        'total' => count($ids),
        'success' => $success,
        'failed' => $failed,
        'duration_ms' => $durationMs,
    ]));
    jsonOut([
        'success' => $failed === 0,
        'action' => $action,
        'total' => count($ids),
        'success_count' => $success,
        'failed_count' => $failed,
        'duration_ms' => $durationMs,
        'request_id' => getRequestId(),
        'first_error' => $itemErrors[0]['error'] ?? null,
        'errors' => $itemErrors,
    ]);
}

// DELETE - single or bulk (requires delete)
if ($method === 'DELETE') {
    if (!hasControlPermission(CONTROL_PERM_AGENCIES) && !hasControlPermission('delete_control_agency')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    if (!isControlSuperAdmin()) {
        jsonOut(['success' => false, 'message' => 'SUPER_ADMIN required for delete']);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $ids = $input['agency_ids'] ?? ($input['ids'] ?? []);
    if (empty($ids)) {
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) $ids = [$id];
    }
    if (empty($ids)) jsonOut(['success' => false, 'message' => 'No IDs']);

    $ids = array_map('intval', $ids);
    $confirm = (string) ($input['confirm'] ?? '');
    if (count($ids) > 1 && $confirm !== 'DELETE') {
        securityBlock('Blocked bulk delete without confirm=DELETE', ['action' => 'delete']);
        jsonOut(['success' => false, 'message' => 'Bulk delete requires confirm=DELETE']);
    }
    if ($allowedCountryIds !== null) {
        $ph = implode(',', $ids);
        $rowsCheck = qAll("SELECT id, country_id FROM control_agencies WHERE id IN ($ph)");
        foreach ($rowsCheck as $row) {
            if (!in_array((int)$row['country_id'], $allowedCountryIds, true)) {
                jsonOut(['success' => false, 'message' => 'You do not have permission to delete one or more of these agencies']);
            }
        }
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    qStmt("DELETE FROM control_agencies WHERE id IN ($placeholders)", $ids);
    foreach ($ids as $id) {
        $tenantId = setTenantContextByAgencyId((int) $id);
        emitEvent('AGENCY_DELETED', 'critical', count($ids) > 1 ? 'Bulk delete' : 'Agency deleted', eventMeta([
            'tenant_id' => $tenantId > 0 ? $tenantId : null,
            'agency_id' => (int) $id,
            'action' => 'delete',
        ]));
    }
    jsonOut(['success' => true, 'deleted' => count($ids)]);
}

jsonOut(['success' => false, 'message' => 'Method not allowed']);
