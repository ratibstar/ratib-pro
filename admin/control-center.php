<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/control-center.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/control-center.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// EN: Mark request as control-center mode for shared access helpers.
// AR: تعليم الطلب كوضع مركز التحكم لاستخدام مساعدات الصلاحيات المشتركة.
if (!defined('ADMIN_CONTROL_MODE')) {
    define('ADMIN_CONTROL_MODE', true);
}

require_once __DIR__ . '/core/ControlCenterAccess.php';

function isControlCenterEnabled(): bool
{
    return ControlCenterAccess::isEnabled();
}

function isControlCenterSuperAdmin(): bool
{
    if (class_exists('Auth') && Auth::isSuperAdmin()) {
        return true;
    }

    // Ratib app-session admin compatibility (when opening control-center directly).
    $hasValidProgramSession = function_exists('ratib_program_session_is_valid_user')
        ? ratib_program_session_is_valid_user()
        : (!empty($_SESSION['logged_in']) && (int) ($_SESSION['user_id'] ?? 0) > 0);
    if ($hasValidProgramSession) {
        $roleId = (int) ($_SESSION['role_id'] ?? 0);
        $roleName = strtolower(trim((string) ($_SESSION['role'] ?? '')));
        $username = strtolower(trim((string) ($_SESSION['username'] ?? '')));
        if ($username === 'admin' || $roleId === 1 || $roleId === 2 || strpos($roleName, 'admin') !== false) {
            return true;
        }
        $userPerms = $_SESSION['user_permissions'] ?? [];
        if (is_array($userPerms)) {
            $norm = array_map(static function ($p) {
                return strtolower(trim((string) $p));
            }, $userPerms);
            if (in_array('super_admin', $norm, true)
                || in_array('manage_system_settings', $norm, true)
                || in_array('control_system_settings', $norm, true)
            ) {
                return true;
            }
        }
    }

    // Control Panel authenticated admin compatibility:
    // allow only privileged control accounts, not any generic control login.
    if (empty($_SESSION['control_logged_in'])) {
        return false;
    }

    $controlUsername = strtolower(trim((string) ($_SESSION['control_username'] ?? '')));
    if ($controlUsername === 'admin') {
        return true;
    }

    $perms = $_SESSION['control_permissions'] ?? [];
    if (is_array($perms)) {
        $norm = array_map(static function ($p) {
            return strtolower(trim((string) $p));
        }, $perms);
        if (in_array('control_system_settings', $norm, true)
            || in_array('view_control_system_settings', $norm, true)
            || in_array('manage_control_roles', $norm, true)
        ) {
            return true;
        }
    }

    return false;
}

// EN: Fallback asset proxy when direct static serving is blocked by hosting/server rules.
// AR: وكيل ملفات احتياطي عندما يمنع الخادم تقديم الملفات الثابتة مباشرة.
// Asset proxy fallback: helps when direct /admin/assets static serving is blocked by server config.
$assetParam = (string) ($_GET['asset'] ?? '');
if ($assetParam === 'css' || $assetParam === 'js') {
    if (!isControlCenterEnabled()) {
        http_response_code(403);
        exit('403 Forbidden');
    }
    $assetFile = $assetParam === 'css'
        ? (__DIR__ . '/assets/css/control-center.css')
        : (__DIR__ . '/assets/js/control-center.js');
    if (!file_exists($assetFile)) {
        http_response_code(404);
        exit('Asset not found');
    }
    if ($assetParam === 'css') {
        header('Content-Type: text/css; charset=UTF-8');
    } else {
        header('Content-Type: application/javascript; charset=UTF-8');
    }
    readfile($assetFile);
    exit;
}

// EN: Hard access gate before executing any control-center logic.
// AR: بوابة صلاحيات صارمة قبل تنفيذ أي منطق خاص بمركز التحكم.
if (!ControlCenterAccess::canAccessControlCenter()) {
    http_response_code(403);
    exit('403 Forbidden');
}

require_once __DIR__ . '/../core/query/QueryGateway.php';
require_once __DIR__ . '/core/EventBus.php';
getRequestId();
require_once __DIR__ . '/core/AdminAuditLog.php';
require_once __DIR__ . '/core/SystemAlerts.php';
require_once __DIR__ . '/core/ControlCenterRateLimiter.php';
require_once __DIR__ . '/core/ControlCenterQueryValidator.php';
require_once __DIR__ . '/core/ControlCenterMetrics.php';
require_once __DIR__ . '/core/ProvisioningService.php';
require_once __DIR__ . '/core/BackupService.php';

/** @var string Control-center RBAC role for this request */
$ccRole = ControlCenterAccess::role();
$isSuperAdminCc = ($ccRole === ControlCenterAccess::SUPER_ADMIN);
$isAdminOrAboveCc = in_array($ccRole, [ControlCenterAccess::SUPER_ADMIN, ControlCenterAccess::ADMIN], true);

function ccAudit(PDO $pdo, string $action, array $ctx = []): void
{
    try {
        logAdminAudit($pdo, $action, array_merge([
            'user_id' => class_exists('Auth') ? Auth::userId() : null,
            'role' => class_exists('Auth') ? Auth::role() : ControlCenterAccess::role(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ], $ctx));
    } catch (Throwable $e) {
        /* ignore */
    }
}

function ccPdo(string $dbName): PDO
{
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
    $user = defined('DB_USER') ? DB_USER : '';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

// EN: Resolve best control DB candidate with compatibility fallbacks.
// AR: تحديد قاعدة التحكم الأنسب مع بدائل متوافقة عند الفشل.
function ccControlPdo(): PDO
{
    $candidates = [];
    $push = static function (string $name) use (&$candidates): void {
        $name = trim($name);
        if ($name !== '' && !in_array($name, $candidates, true)) {
            $candidates[] = $name;
        }
    };

    if (defined('CONTROL_PANEL_DB_NAME')) {
        $push((string) CONTROL_PANEL_DB_NAME);
    }
    if (defined('CONTROL_DB_NAME')) {
        $push((string) CONTROL_DB_NAME);
    }
    $envControlPanel = getenv('CONTROL_PANEL_DB_NAME');
    if ($envControlPanel !== false) {
        $push((string) $envControlPanel);
    }
    $envControlDb = getenv('CONTROL_DB_NAME');
    if ($envControlDb !== false) {
        $push((string) $envControlDb);
    }
    if (defined('DB_NAME')) {
        $push((string) DB_NAME);
    }

    if (empty($candidates)) {
        throw new RuntimeException('Control DB is not configured.');
    }

    $lastError = null;
    foreach ($candidates as $dbName) {
        try {
            $pdo = ccPdo($dbName);
            $hasAgencies = ccTableExists($pdo, 'control_agencies');
            $hasTenants = ccTableExists($pdo, 'tenants');
            if ($hasAgencies || $hasTenants) {
                return $pdo;
            }
            if ($lastError === null) {
                $lastError = new RuntimeException('Control tables not found in DB: ' . $dbName);
            }
        } catch (Throwable $e) {
            $lastError = $e;
        }
    }

    // Fallback: return first connectable DB for backward compatibility.
    foreach ($candidates as $dbName) {
        try {
            return ccPdo($dbName);
        } catch (Throwable $e) {
            $lastError = $e;
        }
    }

    if ($lastError instanceof Throwable) {
        throw new RuntimeException('Control DB connection failed: ' . $lastError->getMessage(), 0, $lastError);
    }
    throw new RuntimeException('Control DB connection failed.');
}

function ccTableExists(PDO $pdo, string $table): bool
{
    try {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($safe === '') {
            return false;
        }
        $pdo->query("SELECT 1 FROM `{$safe}` LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function ccLogEvent(PDO $pdo, string $message, string $level = 'info', ?int $tenantId = null): void
{
    try {
        emitEvent('CONTROL_LOG', $level, $message, [
            'tenant_id' => $tenantId,
            'source' => 'control_center',
            'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'cli'),
        ], $pdo);
    } catch (Throwable $e) {
        error_log('control-center log event failed: ' . $e->getMessage());
    }
}

// EN: Open PDO connection to selected tenant database using stored tenant credentials.
// AR: فتح اتصال PDO بقاعدة المستأجر باستخدام بيانات الاتصال المحفوظة للمستأجر.
function ccTenantPdo(array $tenant): PDO
{
    $host = trim((string) ($tenant['db_host'] ?? '')) ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
    $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
    $dbName = (string) ($tenant['database_name'] ?? '');
    $user = (string) ($tenant['db_user'] ?? '');
    $pass = (string) ($tenant['db_password'] ?? '');
    if ($dbName === '' || $user === '') {
        throw new RuntimeException('Tenant DB credentials incomplete.');
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $dbName);
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

// EN: Issue per-session CSRF token for state-changing control-center requests.
// AR: إنشاء رمز CSRF للجلسة لحماية طلبات التعديل داخل مركز التحكم.
function ccCsrfToken(): string
{
    if (empty($_SESSION['cc_csrf'])) {
        $_SESSION['cc_csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['cc_csrf'];
}

// EN: Validate CSRF token before accepting mutating admin actions.
// AR: التحقق من رمز CSRF قبل قبول أي عملية تعديل إدارية.
function ccRequireCsrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['cc_csrf'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        throw new RuntimeException('Invalid CSRF token.');
    }
}

// EN: Unified JSON response envelope with request metadata.
// AR: غلاف استجابة JSON موحّد يتضمن بيانات تعريف الطلب.
function ccJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Request-Id: ' . getRequestId());
    if (!array_key_exists('success', $payload)) {
        $payload['success'] = $status < 400;
    }
    if (!array_key_exists('data', $payload)) {
        $payload['data'] = [];
    }
    $existingMeta = [];
    if (isset($payload['meta']) && is_array($payload['meta'])) {
        $existingMeta = $payload['meta'];
    }
    $payload['meta'] = array_merge([
        'request_id' => getRequestId(),
        'event_count' => is_array($payload['data']) ? count($payload['data']) : 0,
    ], $existingMeta);
    unset($payload['request_id']);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// EN: Safe error adapter: logs internal details while returning controlled public message.
// AR: معالج أخطاء آمن: يسجل التفاصيل الداخلية ويرجع رسالة عامة آمنة للمستخدم.
function ccJsonSafeError(Throwable $e, string $publicMessage = 'Request failed'): void
{
    $msg = $e->getMessage();
    if ($msg === 'CONTROL_CENTER_FORBIDDEN') {
        ccJsonResponse(['success' => false, 'message' => 'Forbidden'], 403);
    }
    emitEvent('CONTROL_CENTER_ERROR', 'error', 'Control center JSON error', [
        'source' => 'control_center',
        'error' => $msg,
    ]);
    error_log('control-center: ' . $msg);
    ccJsonResponse(['success' => false, 'message' => $publicMessage], 500);
}

// EN: Fetch tenant row by numeric ID with null-safe semantics.
// AR: جلب سجل المستأجر عبر المعرف الرقمي مع إرجاع null عند عدم وجوده.
function ccFindTenant(PDO $pdo, int $tenantId): ?array
{
    if ($tenantId <= 0) {
        return null;
    }
    $st = $pdo->prepare("SELECT * FROM tenants WHERE id = :id LIMIT 1");
    $st->execute([':id' => $tenantId]);
    $row = $st->fetch();
    return $row ?: null;
}

// EN: Normalize agency identifiers from mixed formats into positive integer ID.
// AR: توحيد معرف الوكالة من صيغ متعددة إلى رقم صحيح موجب.
function ccAgencyNumericId($raw): int
{
    if (is_int($raw)) {
        return $raw > 0 ? $raw : 0;
    }
    $s = trim((string) $raw);
    if ($s === '') {
        return 0;
    }
    if (ctype_digit($s)) {
        return (int) $s;
    }
    if (preg_match('/(\d+)/', $s, $m)) {
        return (int) $m[1];
    }
    return 0;
}

/**
 * Build a unified managed-resource list for Control Center:
 * - Real tenants from tenants table
 * - Agencies from control_agencies (including unlinked agencies)
 *
 * @return array<int, array<string, mixed>>
 */
// EN: Build consolidated resource list by merging tenant-backed and agency-only records.
// AR: بناء قائمة موارد موحّدة عبر دمج السجلات المرتبطة بالمستأجر والسجلات الخاصة بالوكالات فقط.
function ccManagedResources(PDO $pdo): array
{
    $items = [];
    try {
        $rows = $pdo->query(
            "SELECT t.id, t.name, t.domain, t.status, t.created_at, t.database_name, t.db_host, t.db_user, t.db_password,
                    COALESCE(
                        a.id,
                        (
                            SELECT a2.id
                            FROM control_agencies a2
                            WHERE a2.db_name = t.database_name
                            ORDER BY a2.id DESC
                            LIMIT 1
                        )
                    ) AS agency_id,
                    COALESCE(
                        a.is_active,
                        (
                            SELECT a2.is_active
                            FROM control_agencies a2
                            WHERE a2.db_name = t.database_name
                            ORDER BY a2.id DESC
                            LIMIT 1
                        ),
                        1
                    ) AS agency_is_active,
                    COALESCE(
                        a.is_suspended,
                        (
                            SELECT a2.is_suspended
                            FROM control_agencies a2
                            WHERE a2.db_name = t.database_name
                            ORDER BY a2.id DESC
                            LIMIT 1
                        ),
                        0
                    ) AS agency_is_suspended
             FROM tenants t
             LEFT JOIN control_agencies a ON a.tenant_id = t.id
             ORDER BY t.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $tid = (int) ($r['id'] ?? 0);
            $agencyId = ccAgencyNumericId($r['agency_id'] ?? 0);
            if ($agencyId > 0) {
                $agencyActive = (int) ($r['agency_is_active'] ?? 1) === 1;
                $agencySuspended = (int) ($r['agency_is_suspended'] ?? 0) === 1;
                $r['status'] = $agencySuspended ? 'suspended' : ($agencyActive ? 'active' : 'inactive');
            }
            $displayId = $agencyId > 0
                ? ('AG' . str_pad((string) $agencyId, 4, '0', STR_PAD_LEFT))
                : ((string) $tid);
            $items[] = array_merge($r, [
                'display_id' => $displayId,
                'tenant_id' => $tid,
                'agency_id' => $agencyId > 0 ? $agencyId : null,
                'resource_type' => 'tenant',
                'has_tenant' => true,
                'has_db_config' => trim((string) ($r['database_name'] ?? '')) !== '' && trim((string) ($r['db_user'] ?? '')) !== '',
            ]);
        }
    } catch (Throwable $e) {
        // tenants table might not exist in all deployments.
    }
    try {
        $agencyRows = $pdo->query(
            "SELECT a.id AS agency_id, a.name AS agency_name, a.slug, a.site_url, a.db_name, a.db_host, a.db_user, a.created_at, a.tenant_id,
                    a.is_active, a.is_suspended,
                    t.id AS tenant_row_id
             FROM control_agencies a
             LEFT JOIN tenants t ON t.id = a.tenant_id
             ORDER BY a.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($agencyRows as $a) {
            $tenantId = (int) ($a['tenant_id'] ?? 0);
            $tenantExists = (int) ($a['tenant_row_id'] ?? 0) > 0;
            if ($tenantId > 0 && $tenantExists) {
                continue; // already represented by tenants list
            }
            $agencyId = ccAgencyNumericId($a['agency_id'] ?? 0);
            $agencyActive = (int) ($a['is_active'] ?? 1) === 1;
            $agencySuspended = (int) ($a['is_suspended'] ?? 0) === 1;
            $displayStatus = $agencySuspended ? 'suspended' : ($agencyActive ? 'active' : 'inactive');
            $items[] = [
                'id' => 0,
                'name' => (string) ($a['agency_name'] ?? ('Agency #' . $agencyId)),
                'domain' => (string) ($a['site_url'] ?? ''),
                'status' => $displayStatus,
                'created_at' => (string) ($a['created_at'] ?? ''),
                'database_name' => (string) ($a['db_name'] ?? ''),
                'db_host' => (string) ($a['db_host'] ?? ''),
                'db_user' => (string) ($a['db_user'] ?? ''),
                'db_password' => '',
                'display_id' => 'AG' . str_pad((string) $agencyId, 4, '0', STR_PAD_LEFT),
                'tenant_id' => $tenantId > 0 ? $tenantId : null,
                'agency_id' => $agencyId,
                'resource_type' => 'agency',
                'has_tenant' => $tenantId > 0,
                'has_db_config' => trim((string) ($a['db_name'] ?? '')) !== '' && trim((string) ($a['db_user'] ?? '')) !== '',
            ];
        }
    } catch (Throwable $e) {
        // control_agencies table might not exist in all deployments.
    }
    return $items;
}

/**
 * Provision and hard-link a single agency to a tenant.
 *
 * @return array{tenant_id:int,database_name:string,db_user:string,db_password:string}
 */
// EN: Link agency to tenant with reuse-first strategy, then provisioning fallback.
// AR: ربط الوكالة بالمستأجر باستراتيجية إعادة الاستخدام أولاً ثم الإنشاء كحل بديل.
function ccLinkAgencyToTenant(PDO $pdo, int $agencyId): array
{
    if ($agencyId <= 0) {
        throw new RuntimeException('agency_id is required');
    }
    if (!ccTableExists($pdo, 'control_agencies')) {
        throw new RuntimeException('control_agencies table not found');
    }
    $agencyStmt = $pdo->prepare(
        "SELECT id, name, slug, site_url, db_name, db_host, db_user, db_pass AS db_password, tenant_id
         FROM control_agencies
         WHERE id = :id
         LIMIT 1"
    );
    $agencyStmt->execute([':id' => $agencyId]);
    $agency = $agencyStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$agency) {
        throw new RuntimeException('Agency not found');
    }

    $existingTenantId = (int) ($agency['tenant_id'] ?? 0);
    if ($existingTenantId > 0) {
        $existingTenant = ccFindTenant($pdo, $existingTenantId);
        if ($existingTenant) {
            return [
                'tenant_id' => $existingTenantId,
                'database_name' => (string) ($existingTenant['database_name'] ?? ''),
                'db_user' => (string) ($existingTenant['db_user'] ?? ''),
                'db_password' => (string) ($existingTenant['db_password'] ?? ''),
            ];
        }
    }

    $agencyName = trim((string) ($agency['name'] ?? ''));
    if ($agencyName === '') {
        $agencyName = 'Agency #' . $agencyId;
    }
    $domainCandidate = ccNormalizeTenantDomain((string) ($agency['site_url'] ?? ''));
    if ($domainCandidate === '') {
        $slug = trim((string) ($agency['slug'] ?? ''));
        $domainCandidate = $slug !== '' ? strtolower($slug) . '.agency.local' : ('agency-' . $agencyId . '.agency.local');
    }

    // Fast-path hard link: if tenant already exists for this agency DB/domain, reuse it.
    $lookupSql = "SELECT id, database_name, db_user, db_password FROM tenants
                  WHERE (database_name = :db_name AND :db_name <> '')
                     OR (domain = :domain AND :domain <> '')
                  ORDER BY id DESC
                  LIMIT 1";
    try {
        $lk = $pdo->prepare($lookupSql);
        $lk->execute([
            ':db_name' => trim((string) ($agency['db_name'] ?? '')),
            ':domain' => $domainCandidate,
        ]);
        $tenantRow = $lk->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($tenantRow) {
            $linkedTenantId = (int) ($tenantRow['id'] ?? 0);
            if ($linkedTenantId > 0) {
                $hasSuspendedCol = false;
                try {
                    $cst = $pdo->query("SHOW COLUMNS FROM control_agencies LIKE 'is_suspended'");
                    $hasSuspendedCol = (bool) ($cst && $cst->fetchColumn());
                } catch (Throwable $e) {
                    $hasSuspendedCol = false;
                }
                $updSql = $hasSuspendedCol
                    ? "UPDATE control_agencies SET tenant_id = :tenant_id, is_active = 1, is_suspended = 0 WHERE id = :id"
                    : "UPDATE control_agencies SET tenant_id = :tenant_id, is_active = 1 WHERE id = :id";
                $upd = $pdo->prepare($updSql);
                $upd->execute([
                    ':tenant_id' => $linkedTenantId,
                    ':id' => $agencyId,
                ]);
                $pdo->prepare("UPDATE tenants SET status = 'active' WHERE id = :id")->execute([':id' => $linkedTenantId]);

                emitEvent('AGENCY_LINKED_TENANT', 'info', 'Agency linked to existing tenant from control center', [
                    'source' => 'control_center',
                    'agency_id' => $agencyId,
                    'tenant_id' => $linkedTenantId,
                    'action' => 'tenant_link_existing',
                    'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'admin/control-center.php'),
                ], $pdo);

                return [
                    'tenant_id' => $linkedTenantId,
                    'database_name' => (string) ($tenantRow['database_name'] ?? ''),
                    'db_user' => (string) ($tenantRow['db_user'] ?? ''),
                    'db_password' => (string) ($tenantRow['db_password'] ?? ''),
                ];
            }
        }
    } catch (Throwable $e) {
        // Continue with provisioning/fallback path below.
    }

    $out = null;
    try {
        $out = ProvisioningService::createTenant($pdo, $agencyName, $domainCandidate, [
            'database_name' => trim((string) ($agency['db_name'] ?? '')),
            'db_host' => trim((string) ($agency['db_host'] ?? '')),
            'db_user' => trim((string) ($agency['db_user'] ?? '')),
            'db_password' => (string) ($agency['db_password'] ?? ''),
            'status' => 'active',
        ]);
    } catch (Throwable $e) {
        // Fallback path for hosts where provisioning cannot run (e.g. PDO driver/permissions).
        $fallbackDomain = $domainCandidate;
        $inserted = false;
        for ($i = 0; $i < 2 && !$inserted; $i++) {
            if ($i === 1) {
                $fallbackDomain = 'agency-' . $agencyId . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.agency.local';
            }
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO tenants (name, domain, database_name, db_host, db_user, db_password, status, created_at)
                     VALUES (:name, :domain, :database_name, :db_host, :db_user, :db_password, 'active', NOW())"
                );
                $stmt->execute([
                    ':name' => $agencyName,
                    ':domain' => $fallbackDomain,
                    ':database_name' => trim((string) ($agency['db_name'] ?? '')),
                    ':db_host' => trim((string) ($agency['db_host'] ?? '')) ?: null,
                    ':db_user' => trim((string) ($agency['db_user'] ?? '')),
                    ':db_password' => (string) ($agency['db_password'] ?? ''),
                ]);
                $inserted = true;
            } catch (Throwable $ie) {
                $inserted = false;
            }
        }
        if (!$inserted) {
            throw $e;
        }
        $out = [
            'tenant_id' => (int) $pdo->lastInsertId(),
            'database_name' => trim((string) ($agency['db_name'] ?? '')),
            'db_user' => trim((string) ($agency['db_user'] ?? '')),
            'db_password' => (string) ($agency['db_password'] ?? ''),
        ];
    }

    $newTenantId = (int) ($out['tenant_id'] ?? 0);
    if ($newTenantId <= 0) {
        throw new RuntimeException('Provisioning failed: missing tenant id');
    }

    $hasSuspendedCol = false;
    try {
        $cst = $pdo->query("SHOW COLUMNS FROM control_agencies LIKE 'is_suspended'");
        $hasSuspendedCol = (bool) ($cst && $cst->fetchColumn());
    } catch (Throwable $e) {
        $hasSuspendedCol = false;
    }

    $updSql = $hasSuspendedCol
        ? "UPDATE control_agencies SET tenant_id = :tenant_id, is_active = 1, is_suspended = 0 WHERE id = :id"
        : "UPDATE control_agencies SET tenant_id = :tenant_id, is_active = 1 WHERE id = :id";
    $upd = $pdo->prepare($updSql);
    $upd->execute([
        ':tenant_id' => $newTenantId,
        ':id' => $agencyId,
    ]);

    emitEvent('AGENCY_LINKED_TENANT', 'info', 'Agency linked to tenant from control center', [
        'source' => 'control_center',
        'agency_id' => $agencyId,
        'tenant_id' => $newTenantId,
        'action' => 'tenant_link_agency',
        'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'admin/control-center.php'),
    ], $pdo);

    return [
        'tenant_id' => $newTenantId,
        'database_name' => (string) ($out['database_name'] ?? ''),
        'db_user' => (string) ($out['db_user'] ?? ''),
        'db_password' => (string) ($out['db_password'] ?? ''),
    ];
}

/**
 * Best-effort auto-link for legacy unlinked agencies.
 *
 * @return array{attempted:int,linked:int,failed:int}
 */
function ccAutoLinkUnlinkedAgencies(PDO $pdo, int $limit = 25): array
{
    $limit = max(1, min(100, $limit));
    $out = ['attempted' => 0, 'linked' => 0, 'failed' => 0];
    if (!ccTableExists($pdo, 'control_agencies')) {
        return $out;
    }

    $st = $pdo->prepare(
        "SELECT id
         FROM control_agencies
         WHERE tenant_id IS NULL OR tenant_id <= 0
         ORDER BY id ASC
         LIMIT {$limit}"
    );
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        return $out;
    }

    emitEvent('BULK_OPERATION_STARTED', 'info', 'Auto-link unlinked agencies started', [
        'source' => 'control_center',
        'action' => 'auto_link_unlinked_agencies',
        'total' => count($rows),
        'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'admin/control-center.php'),
    ], $pdo);

    $start = microtime(true);
    foreach ($rows as $r) {
        $agencyId = (int) ($r['id'] ?? 0);
        if ($agencyId <= 0) {
            continue;
        }
        $out['attempted']++;
        try {
            $linked = ccLinkAgencyToTenant($pdo, $agencyId);
            $out['linked']++;
            emitEvent('BULK_OPERATION_ITEM_SUCCESS', 'info', 'Auto-link item success', [
                'source' => 'control_center',
                'action' => 'auto_link_unlinked_agencies',
                'agency_id' => $agencyId,
                'tenant_id' => (int) ($linked['tenant_id'] ?? 0),
            ], $pdo);
        } catch (Throwable $e) {
            $out['failed']++;
            SystemAlerts::create($pdo, 'MEDIUM', 'agency_tenant_auto_link_failed', $e->getMessage(), ['agency_id' => $agencyId], null);
            emitEvent('BULK_OPERATION_ITEM_FAILED', 'warn', 'Auto-link item failed', [
                'source' => 'control_center',
                'action' => 'auto_link_unlinked_agencies',
                'agency_id' => $agencyId,
                'error' => $e->getMessage(),
            ], $pdo);
        }
        usleep(120000);
    }

    $durationMs = (int) round((microtime(true) - $start) * 1000);
    emitEvent('BULK_OPERATION_COMPLETED', 'info', 'Auto-link unlinked agencies completed', [
        'source' => 'control_center',
        'action' => 'auto_link_unlinked_agencies',
        'total' => $out['attempted'],
        'success' => $out['linked'],
        'failed' => $out['failed'],
        'duration_ms' => $durationMs,
        'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'admin/control-center.php'),
    ], $pdo);

    return $out;
}

function ccRateIdentity(): string
{
    if (class_exists('Auth') && Auth::userId() !== null && (int) Auth::userId() > 0) {
        return 'u:' . Auth::userId();
    }
    $u = strtolower(trim((string) ($_SESSION['control_username'] ?? '')));
    if ($u !== '') {
        return 'c:' . $u;
    }
    return 's:' . session_id();
}

function ccNormalizeTenantDomain(string $input): string
{
    $v = trim(strtolower($input));
    if ($v === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $v)) {
        $host = (string) parse_url($v, PHP_URL_HOST);
        if ($host !== '') {
            return strtolower(trim($host));
        }
    }
    $v = preg_replace('#^https?://#i', '', $v);
    $v = preg_replace('#/.*$#', '', (string) $v);
    return trim((string) $v);
}

/**
 * @param array<string, mixed> $src
 * @return array<string, mixed>
 */
function ccRedactPayloadForAudit(array $src): array
{
    $out = $src;
    foreach (['db_password', 'password', 'confirm_text', 'confirm_text_second', 'sql', 'query'] as $k) {
        if (isset($out[$k]) && (string) $out[$k] !== '') {
            $out[$k] = '[redacted]';
        }
    }
    return $out;
}

function ccBackupStorageDir(): string
{
    if (defined('CONTROL_CENTER_BACKUP_DIR') && is_string(CONTROL_CENTER_BACKUP_DIR) && CONTROL_CENTER_BACKUP_DIR !== '') {
        return rtrim(CONTROL_CENTER_BACKUP_DIR, '/\\');
    }
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'ratib_cc_backup';
}

function ccResolveTenantBackupFile(string $basename): ?string
{
    $base = basename($basename);
    if ($base === '' || str_contains($base, '..')) {
        return null;
    }
    $dir = realpath(ccBackupStorageDir());
    if ($dir === false) {
        return null;
    }
    $full = $dir . DIRECTORY_SEPARATOR . $base;
    return is_readable($full) ? $full : null;
}

/**
 * @return array{result: array<int, array<string, mixed>>, rows_affected: int, execution_ms: int}
 */
function ccExecuteControlQuery(PDO $controlPdo, string $query, int $tenantId, string $mode, string $confirmWrite): array
{
    $mode = strtoupper(trim($mode));
    if (!in_array($mode, ['SAFE', 'STRICT', 'SYSTEM'], true)) {
        $mode = 'SAFE';
    }
    $cw = strtolower(trim($confirmWrite));
    $query = trim($query);
    if ($query === '') {
        throw new RuntimeException('query is required');
    }
    if (!ControlCenterQueryValidator::isSafe($query)) {
        SystemAlerts::create($controlPdo, 'HIGH', 'unsafe_query', 'Blocked unsafe query attempt', ['mode' => $mode, 'tenant_id' => $tenantId], $tenantId > 0 ? $tenantId : null);
        ControlCenterMetrics::bump($controlPdo, 'safety_warn', 1);
        ccAudit($controlPdo, 'unsafe_query_blocked', ['tenant_id' => $tenantId > 0 ? $tenantId : null, 'payload' => ['mode' => $mode, 'query' => substr($query, 0, 500)]]);
        throw new RuntimeException('Unsafe query blocked by validator');
    }
    $isReadOnly = ControlCenterQueryValidator::isReadOnlyStatement($query);
    if ($mode === 'SAFE') {
        ControlCenterAccess::requireRole([
            ControlCenterAccess::SUPER_ADMIN,
            ControlCenterAccess::ADMIN,
            ControlCenterAccess::VIEWER,
        ]);
        if (!$isReadOnly) {
            throw new RuntimeException('SAFE mode allows read-only statements only');
        }
    } elseif ($mode === 'STRICT') {
        ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN, ControlCenterAccess::ADMIN]);
        if (!$isReadOnly) {
            if (!in_array($cw, ['1', 'true', 'yes', 'on'], true)) {
                throw new RuntimeException('Write query requires explicit confirmation');
            }
            if (stripos($query, 'tenant_id') === false) {
                throw new RuntimeException('STRICT write requires tenant_id in SQL');
            }
        }
    } else {
        ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
        if (!$isReadOnly) {
            if (!in_array($cw, ['1', 'true', 'yes', 'on'], true)) {
                throw new RuntimeException('Write query requires explicit confirmation');
            }
        }
    }
    if ($tenantId > 0) {
        $trow = ccFindTenant($controlPdo, $tenantId);
        if ($trow && (string) ($trow['status'] ?? '') === 'suspended') {
            SystemAlerts::create($controlPdo, 'MEDIUM', 'tenant_inactive_access', 'Query attempted on suspended tenant', ['tenant_id' => $tenantId], $tenantId);
            throw new RuntimeException('Tenant is suspended. Query execution blocked.');
        }
    }
    $conn = ($mode === 'SYSTEM' || $tenantId <= 0) ? $controlPdo : ccGetTenantDbById($tenantId);
    QueryGateway::setConnection($conn);
    $start = microtime(true);
    try {
        $stmt = QueryGateway::execute($query, []);
    } catch (Throwable $qe) {
        ControlCenterMetrics::bump($controlPdo, 'query_fail', 1);
        ccAudit($controlPdo, 'run_query_failed', ['tenant_id' => $tenantId > 0 ? $tenantId : null, 'payload' => ['mode' => $mode]]);
        throw $qe;
    }
    $elapsedMs = (int) round((microtime(true) - $start) * 1000);
    $rows = [];
    $affected = 0;
    if ($stmt instanceof PDOStatement) {
        try {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $rows = [];
        }
        $affected = $stmt->rowCount();
    }
    ControlCenterMetrics::bump($controlPdo, 'query_ok', 1);
    ccAudit($controlPdo, 'run_query', ['tenant_id' => $tenantId > 0 ? $tenantId : null, 'payload' => ['mode' => $mode, 'rows_affected' => $affected]]);
    logSystemEvent('ADMIN_ACTION', ['action' => 'run_query', 'mode' => $mode, 'tenant_id' => $tenantId, 'affected' => $affected]);
    return ['result' => $rows, 'rows_affected' => $affected, 'execution_ms' => $elapsedMs];
}

function ccAssertTenantDataPlaneActive(PDO $controlPdo, int $tenantId): void
{
    if ($tenantId <= 0) {
        return;
    }
    $t = ccFindTenant($controlPdo, $tenantId);
    if (!$t) {
        throw new RuntimeException('Tenant not found.');
    }
    if ((string) ($t['status'] ?? '') === 'suspended') {
        SystemAlerts::create($controlPdo, 'MEDIUM', 'tenant_inactive_access', 'Attempted data-plane operation on suspended tenant', ['tenant_id' => $tenantId], $tenantId);
        throw new RuntimeException('Tenant is suspended. This operation is blocked.');
    }
}

function ccGetTenantDbById(int $tenantId)
{
    if ($tenantId <= 0) {
        return ratib_app_default_db_connection();
    }
    if (!class_exists('TenantDatabaseManager', false)) {
        require_once __DIR__ . '/../api/core/TenantDatabaseManager.php';
    }
    return TenantDatabaseManager::pdoForTenantId($tenantId);
}

$alerts = [];
$queryResult = null;
$queryError = null;
$controlPdo = null;

try {
    $controlPdo = ccControlPdo();
    emitEvent('REQUEST_START', 'info', 'Control center request started', [
        'source' => 'control_center',
        'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'control-center'),
        'role' => $ccRole,
    ], $controlPdo);
} catch (Throwable $e) {
    $alerts[] = ['type' => 'danger', 'text' => 'Control DB unavailable: ' . $e->getMessage()];
}

// JSON API handler (same endpoint)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $controlPdo instanceof PDO) {
    $raw = file_get_contents('php://input');
    $json = json_decode((string) $raw, true);
    $isJsonRequest = is_array($json) || (isset($_SERVER['CONTENT_TYPE']) && stripos((string) $_SERVER['CONTENT_TYPE'], 'application/json') !== false);
    $apiAction = is_array($json) ? (string) ($json['action'] ?? '') : '';
    if ($isJsonRequest || (isset($_POST['api_action']) && $_POST['api_action'] === '1')) {
        try {
            if (!$isJsonRequest) {
                ccRequireCsrf();
            }
            $action = $apiAction !== '' ? $apiAction : (string) ($_POST['action'] ?? '');
            $rateIdentity = ccRateIdentity();

            if ($action === 'create_tenant') {
                ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
                if (!ControlCenterRateLimiter::check('tenant_actions', $rateIdentity, 5, 60)) {
                    ccJsonResponse(['success' => false, 'message' => 'Too many tenant actions. Try again shortly.'], 429);
                }
                $name = trim((string) ($json['name'] ?? $_POST['name'] ?? ''));
                $domain = ccNormalizeTenantDomain((string) ($json['domain'] ?? $_POST['domain'] ?? ''));
                if ($name === '' || $domain === '') {
                    ccJsonResponse(['success' => false, 'message' => 'name and domain are required'], 422);
                }
                $options = [
                    'database_name' => trim((string) ($json['database_name'] ?? $_POST['database_name'] ?? '')),
                    'db_host' => trim((string) ($json['db_host'] ?? $_POST['db_host'] ?? '')),
                    'db_user' => trim((string) ($json['db_user'] ?? $_POST['db_user'] ?? '')),
                    'db_password' => (string) ($json['db_password'] ?? $_POST['db_password'] ?? ''),
                    'status' => strtolower(trim((string) ($json['status'] ?? $_POST['status'] ?? 'active'))),
                ];
                try {
                    $out = ProvisioningService::createTenant($controlPdo, $name, $domain, $options);
                } catch (Throwable $e) {
                    SystemAlerts::create($controlPdo, 'HIGH', 'tenant_provision_failed', $e->getMessage(), ['domain' => $domain], null);
                    error_log('control-center create_tenant failed: ' . $e->getMessage());
                    $isPrivileged = in_array($ccRole, [ControlCenterAccess::SUPER_ADMIN, ControlCenterAccess::ADMIN], true);
                    ccJsonResponse([
                        'success' => false,
                        'message' => $isPrivileged ? ('Provisioning failed: ' . $e->getMessage()) : 'Provisioning failed',
                    ], 500);
                }
                ccAudit($controlPdo, 'create_tenant', ['tenant_id' => $out['tenant_id'], 'payload' => ['domain' => $domain, 'database_name' => $out['database_name']]]);
                logSystemEvent('ADMIN_ACTION', ['action' => 'create_tenant', 'tenant_id' => $out['tenant_id'], 'domain' => $domain]);
                ccJsonResponse([
                    'success' => true,
                    'tenant_id' => $out['tenant_id'],
                    'database_name' => $out['database_name'],
                    'db_user' => $out['db_user'],
                    'db_password' => $out['db_password'],
                ]);
            }

            if ($action === 'get_tenants') {
                $rows = ccManagedResources($controlPdo);
                ccJsonResponse(['success' => true, 'tenants' => $rows, 'role' => $ccRole]);
            }

            if ($action === 'configure_db') {
                ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN, ControlCenterAccess::ADMIN]);
                if (!ControlCenterRateLimiter::check('tenant_actions', $rateIdentity, 5, 60)) {
                    ccJsonResponse(['success' => false, 'message' => 'Too many tenant actions. Try again shortly.'], 429);
                }
                $tenantId = (int) ($json['tenant_id'] ?? $_POST['tenant_id'] ?? 0);
                $databaseName = trim((string) ($json['database_name'] ?? $_POST['database_name'] ?? ''));
                $dbHost = trim((string) ($json['db_host'] ?? $_POST['db_host'] ?? ''));
                $dbUser = trim((string) ($json['db_user'] ?? $_POST['db_user'] ?? ''));
                $dbPassword = (string) ($json['db_password'] ?? $_POST['db_password'] ?? '');
                if ($tenantId <= 0) {
                    ccJsonResponse(['success' => false, 'message' => 'tenant_id is required'], 422);
                }
                if ($databaseName === '' || $dbUser === '') {
                    ccJsonResponse(['success' => false, 'message' => 'database_name and db_user are required'], 422);
                }
                $tenant = ccFindTenant($controlPdo, $tenantId);
                if (!$tenant) {
                    ccJsonResponse(['success' => false, 'message' => 'Tenant not found'], 404);
                }
                $stmt = $controlPdo->prepare(
                    "UPDATE tenants
                     SET database_name = :database_name,
                         db_host = :db_host,
                         db_user = :db_user,
                         db_password = :db_password,
                         updated_at = NOW()
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':id' => $tenantId,
                    ':database_name' => $databaseName,
                    ':db_host' => $dbHost !== '' ? $dbHost : null,
                    ':db_user' => $dbUser,
                    ':db_password' => $dbPassword,
                ]);
                ccAudit($controlPdo, 'configure_db', ['tenant_id' => $tenantId, 'payload' => ['database_name' => $databaseName]]);
                logSystemEvent('ADMIN_ACTION', ['action' => 'configure_db', 'tenant_id' => $tenantId, 'database_name' => $databaseName]);
                ccJsonResponse(['success' => true, 'message' => 'Tenant DB configuration updated']);
            }

            if ($action === 'link_agency_tenant') {
                ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
                if (!ControlCenterRateLimiter::check('tenant_actions', $rateIdentity, 5, 60)) {
                    ccJsonResponse(['success' => false, 'message' => 'Too many tenant actions. Try again shortly.'], 429);
                }
                $agencyId = (int) ($json['agency_id'] ?? $_POST['agency_id'] ?? 0);
                try {
                    $out = ccLinkAgencyToTenant($controlPdo, $agencyId);
                } catch (Throwable $e) {
                    SystemAlerts::create($controlPdo, 'HIGH', 'agency_tenant_link_failed', $e->getMessage(), ['agency_id' => $agencyId], null);
                    throw $e;
                }
                ccAudit($controlPdo, 'tenant_link_agency', ['tenant_id' => $out['tenant_id'], 'payload' => ['agency_id' => $agencyId]]);
                logSystemEvent('ADMIN_ACTION', ['action' => 'tenant_link_agency', 'tenant_id' => $out['tenant_id'], 'agency_id' => $agencyId]);
                ccJsonResponse(['success' => true, 'tenant_id' => $out['tenant_id']]);
            }

            if ($action === 'run_query') {
                if (!ControlCenterRateLimiter::check('query_console', $rateIdentity, 10, 10)) {
                    ccJsonResponse(['success' => false, 'message' => 'Query console rate limited'], 429);
                }
                $query = trim((string) ($json['query'] ?? $_POST['query'] ?? ''));
                $tenantId = (int) ($json['tenant_id'] ?? $_POST['tenant_id'] ?? 0);
                $mode = strtoupper(trim((string) ($json['mode'] ?? $_POST['mode'] ?? 'SAFE')));
                $confirmWrite = strtolower(trim((string) ($json['confirm_write'] ?? $_POST['confirm_write'] ?? '0')));
                try {
                    $out = ccExecuteControlQuery($controlPdo, $query, $tenantId, $mode, $confirmWrite);
                } catch (Throwable $qe) {
                    $qm = $qe->getMessage();
                    if ($qm === 'Unsafe query blocked by validator') {
                        ccJsonResponse(['success' => false, 'message' => $qm], 403);
                    }
                    if (str_contains($qm, 'SAFE mode') || str_contains($qm, 'confirmation') || str_contains($qm, 'STRICT write') || str_contains($qm, 'suspended')) {
                        ccJsonResponse(['success' => false, 'message' => $qm], 422);
                    }
                    error_log('control-center run_query: ' . $qm);
                    ccJsonResponse(['success' => false, 'message' => 'Query execution failed'], 500);
                }
                ccJsonResponse([
                    'success' => true,
                    'result' => $out['result'],
                    'rows_affected' => $out['rows_affected'],
                    'execution_ms' => $out['execution_ms'],
                ]);
            }

            if ($action === 'test_connection') {
                ControlCenterAccess::requireRole([
                    ControlCenterAccess::SUPER_ADMIN,
                    ControlCenterAccess::ADMIN,
                    ControlCenterAccess::VIEWER,
                ]);
                $tenantId = (int) ($json['tenant_id'] ?? $_POST['tenant_id'] ?? 0);
                if ($tenantId <= 0) {
                    ccJsonResponse(['success' => false, 'message' => 'tenant_id is required'], 422);
                }
                $tenant = ccFindTenant($controlPdo, $tenantId);
                if (!$tenant) {
                    ccJsonResponse(['success' => false, 'message' => 'Tenant not found'], 404);
                }
                if ((string) ($tenant['status'] ?? '') === 'suspended') {
                    SystemAlerts::create($controlPdo, 'MEDIUM', 'tenant_inactive_access', 'Connection test on suspended tenant', ['tenant_id' => $tenantId], $tenantId);
                    ccJsonResponse(['success' => false, 'message' => 'Tenant is suspended'], 403);
                }
                $hasDbName = trim((string) ($tenant['database_name'] ?? '')) !== '';
                $hasDbUser = trim((string) ($tenant['db_user'] ?? '')) !== '';
                if (!$hasDbName || !$hasDbUser) {
                    ccJsonResponse([
                        'success' => false,
                        'message' => 'Tenant DB credentials are incomplete. Please configure database_name and db_user first.',
                        'code' => 'TENANT_DB_CONFIG_INCOMPLETE',
                    ], 422);
                }
                try {
                    $db = ccGetTenantDbById($tenantId);
                    if ($db instanceof PDO) {
                        $db->query('SELECT 1');
                    } elseif ($db instanceof mysqli) {
                        $db->query('SELECT 1');
                    } else {
                        throw new RuntimeException('Unsupported DB connection type.');
                    }
                } catch (Throwable $e) {
                    SystemAlerts::create($controlPdo, 'MEDIUM', 'db_connection_failed', 'Tenant DB connection failed', ['tenant_id' => $tenantId], $tenantId);
                    ccJsonResponse(['success' => false, 'message' => 'Connection failed'], 500);
                }
                ccAudit($controlPdo, 'test_connection', ['tenant_id' => $tenantId]);
                ccJsonResponse(['success' => true, 'status' => 'connected']);
            }

            if ($action === 'backup_tenant') {
                ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
                if (!ControlCenterRateLimiter::check('tenant_actions', $rateIdentity, 5, 60)) {
                    ccJsonResponse(['success' => false, 'message' => 'Too many tenant actions. Try again shortly.'], 429);
                }
                $tenantId = (int) ($json['tenant_id'] ?? $_POST['tenant_id'] ?? 0);
                if ($tenantId <= 0) {
                    ccJsonResponse(['success' => false, 'message' => 'tenant_id is required'], 422);
                }
                ccAssertTenantDataPlaneActive($controlPdo, $tenantId);
                try {
                    $dir = ccBackupStorageDir();
                    $file = BackupService::backup($tenantId, $controlPdo, $dir);
                } catch (Throwable $e) {
                    SystemAlerts::create($controlPdo, 'HIGH', 'backup_failed', $e->getMessage(), ['tenant_id' => $tenantId], $tenantId);
                    ccJsonResponse(['success' => false, 'message' => 'Backup failed'], 500);
                }
                $basename = basename($file);
                ccAudit($controlPdo, 'backup_tenant', ['tenant_id' => $tenantId, 'payload' => ['file' => $basename]]);
                logSystemEvent('ADMIN_ACTION', ['action' => 'backup_tenant', 'tenant_id' => $tenantId]);
                ccJsonResponse(['success' => true, 'file' => $basename]);
            }

            if ($action === 'restore_tenant') {
                ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
                if (!ControlCenterRateLimiter::check('tenant_actions', $rateIdentity, 5, 60)) {
                    ccJsonResponse(['success' => false, 'message' => 'Too many tenant actions. Try again shortly.'], 429);
                }
                $tenantId = (int) ($json['tenant_id'] ?? $_POST['tenant_id'] ?? 0);
                $backupFile = (string) ($json['backup_file'] ?? $_POST['backup_file'] ?? '');
                if ($tenantId <= 0 || $backupFile === '') {
                    ccJsonResponse(['success' => false, 'message' => 'tenant_id and backup_file are required'], 422);
                }
                ccAssertTenantDataPlaneActive($controlPdo, $tenantId);
                $resolved = ccResolveTenantBackupFile($backupFile);
                if ($resolved === null) {
                    ccJsonResponse(['success' => false, 'message' => 'Backup file not found'], 404);
                }
                try {
                    BackupService::restore($tenantId, $controlPdo, $resolved);
                } catch (Throwable $e) {
                    SystemAlerts::create($controlPdo, 'CRITICAL', 'restore_failed', $e->getMessage(), ['tenant_id' => $tenantId], $tenantId);
                    ccJsonResponse(['success' => false, 'message' => 'Restore failed'], 500);
                }
                ccAudit($controlPdo, 'restore_tenant', ['tenant_id' => $tenantId, 'payload' => ['backup_file' => basename($resolved)]]);
                logSystemEvent('ADMIN_ACTION', ['action' => 'restore_tenant', 'tenant_id' => $tenantId]);
                ccJsonResponse(['success' => true, 'message' => 'Restore completed']);
            }

            ccJsonResponse(['success' => false, 'message' => 'Unknown action'], 400);
        } catch (Throwable $e) {
            ccJsonSafeError($e);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $controlPdo instanceof PDO) {
    try {
        ccRequireCsrf();
        $action = (string) ($_POST['action'] ?? '');
        $rid = ccRateIdentity();
        $auditPost = ccRedactPayloadForAudit($_POST);

        if ($action === 'tenant_create') {
            ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
            if (!ControlCenterRateLimiter::check('tenant_actions', $rid, 5, 60)) {
                throw new RuntimeException('RATE_LIMIT');
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            $domain = ccNormalizeTenantDomain((string) ($_POST['domain'] ?? ''));
            if ($name === '' || $domain === '') {
                throw new RuntimeException('Tenant name and domain are required.');
            }
            $options = [
                'database_name' => trim((string) ($_POST['database_name'] ?? '')),
                'db_host' => trim((string) ($_POST['db_host'] ?? '')),
                'db_user' => trim((string) ($_POST['db_user'] ?? '')),
                'db_password' => (string) ($_POST['db_password'] ?? ''),
                'status' => strtolower(trim((string) ($_POST['status'] ?? 'active'))),
            ];
            try {
                $out = ProvisioningService::createTenant($controlPdo, $name, $domain, $options);
            } catch (Throwable $e) {
                SystemAlerts::create($controlPdo, 'HIGH', 'tenant_provision_failed', $e->getMessage(), ['domain' => $domain], null);
                error_log('tenant_create form: ' . $e->getMessage());
                throw new RuntimeException('Provisioning failed. Check logs and permissions.');
            }
            ccAudit($controlPdo, 'tenant_create', ['tenant_id' => $out['tenant_id'], 'payload' => $auditPost]);
            logSystemEvent('ADMIN_ACTION', ['action' => 'tenant_create', 'tenant_id' => $out['tenant_id']]);
            $alerts[] = ['type' => 'safe', 'text' => 'Tenant created and provisioned successfully.'];
        } elseif ($action === 'tenant_update') {
            ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN, ControlCenterAccess::ADMIN]);
            if (!ControlCenterRateLimiter::check('tenant_actions', $rid, 5, 60)) {
                throw new RuntimeException('RATE_LIMIT');
            }
            $tid = (int) ($_POST['tenant_id'] ?? 0);
            if ($tid <= 0) {
                throw new RuntimeException('tenant_id is required.');
            }
            $stmt = $controlPdo->prepare(
                'UPDATE tenants SET name=:n, domain=:d, database_name=:db, db_host=:h, db_user=:u, status=:s, updated_at=NOW() WHERE id=:id'
            );
            $stmt->execute([
                ':id' => $tid,
                ':n' => trim((string) ($_POST['name'] ?? '')),
                ':d' => strtolower(trim((string) ($_POST['domain'] ?? ''))),
                ':db' => trim((string) ($_POST['database_name'] ?? '')),
                ':h' => trim((string) ($_POST['db_host'] ?? '')) ?: null,
                ':u' => trim((string) ($_POST['db_user'] ?? '')),
                ':s' => trim((string) ($_POST['status'] ?? 'active')),
            ]);
            ccAudit($controlPdo, 'tenant_update', ['tenant_id' => $tid, 'payload' => $auditPost]);
            logSystemEvent('ADMIN_ACTION', ['action' => 'tenant_update', 'tenant_id' => $tid]);
            $alerts[] = ['type' => 'safe', 'text' => 'Tenant updated.'];
        } elseif ($action === 'tenant_toggle') {
            ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN, ControlCenterAccess::ADMIN]);
            if (!ControlCenterRateLimiter::check('tenant_actions', $rid, 5, 60)) {
                throw new RuntimeException('RATE_LIMIT');
            }
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? '');
            $next = $status === 'active' ? 'suspended' : 'active';
            if ($next === 'suspended') {
                ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
            }
            $confirmText = strtoupper(trim((string) ($_POST['confirm_text'] ?? '')));
            if ($next === 'suspended' && $confirmText !== 'SUSPEND') {
                throw new RuntimeException('Suspend requires confirm text: SUSPEND');
            }
            if ($next === 'active' && $confirmText !== 'ACTIVATE') {
                throw new RuntimeException('Activate requires confirm text: ACTIVATE');
            }
            $stmt = $controlPdo->prepare('UPDATE tenants SET status=:s, updated_at=NOW() WHERE id=:id');
            $stmt->execute([':s' => $next, ':id' => $tenantId]);
            // Keep control_agencies status in sync with tenant status for consistent UI/state.
            $hasSuspColSt = $controlPdo->query("SHOW COLUMNS FROM control_agencies LIKE 'is_suspended'");
            $hasSuspCol = (bool) ($hasSuspColSt && $hasSuspColSt->fetchColumn());
            if ($hasSuspCol) {
                $ast = $controlPdo->prepare('UPDATE control_agencies SET is_active=:a, is_suspended=:s WHERE tenant_id=:tid');
                $ast->execute([
                    ':a' => $next === 'active' ? 1 : 0,
                    ':s' => $next === 'suspended' ? 1 : 0,
                    ':tid' => $tenantId,
                ]);
            } else {
                $ast = $controlPdo->prepare('UPDATE control_agencies SET is_active=:a WHERE tenant_id=:tid');
                $ast->execute([
                    ':a' => $next === 'active' ? 1 : 0,
                    ':tid' => $tenantId,
                ]);
            }
            ccAudit($controlPdo, 'tenant_toggle', ['tenant_id' => $tenantId, 'payload' => array_merge($auditPost, ['next_status' => $next])]);
            logSystemEvent('ADMIN_ACTION', ['action' => 'tenant_toggle', 'tenant_id' => $tenantId, 'status' => $next]);
            $alerts[] = ['type' => 'warning', 'text' => 'Tenant status changed to ' . $next . '.'];
        } elseif ($action === 'tenant_delete') {
            ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
            if (!ControlCenterRateLimiter::check('tenant_actions', $rid, 5, 60)) {
                throw new RuntimeException('RATE_LIMIT');
            }
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            $confirmText = strtoupper(trim((string) ($_POST['confirm_text'] ?? '')));
            if ($confirmText !== 'DELETE') {
                throw new RuntimeException('Delete requires confirm text: DELETE');
            }
            $stmt = $controlPdo->prepare('DELETE FROM tenants WHERE id=:id');
            $stmt->execute([':id' => $tenantId]);
            ccAudit($controlPdo, 'tenant_delete', ['tenant_id' => $tenantId, 'payload' => $auditPost]);
            logSystemEvent('ADMIN_ACTION', ['action' => 'tenant_delete', 'tenant_id' => $tenantId]);
            $alerts[] = ['type' => 'danger', 'text' => 'Tenant deleted.'];
        } elseif ($action === 'tenant_link_agency') {
            ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
            if (!ControlCenterRateLimiter::check('tenant_actions', $rid, 5, 60)) {
                throw new RuntimeException('RATE_LIMIT');
            }
            $agencyId = (int) ($_POST['agency_id'] ?? 0);
            try {
                $out = ccLinkAgencyToTenant($controlPdo, $agencyId);
            } catch (Throwable $e) {
                SystemAlerts::create($controlPdo, 'HIGH', 'agency_tenant_link_failed', $e->getMessage(), ['agency_id' => $agencyId], null);
                error_log('tenant_link_agency form: ' . $e->getMessage());
                throw new RuntimeException('Agency link failed: ' . $e->getMessage());
            }
            ccAudit($controlPdo, 'tenant_link_agency', ['tenant_id' => $out['tenant_id'], 'payload' => ['agency_id' => $agencyId]]);
            logSystemEvent('ADMIN_ACTION', ['action' => 'tenant_link_agency', 'tenant_id' => $out['tenant_id'], 'agency_id' => $agencyId]);
            $alerts[] = ['type' => 'safe', 'text' => 'Agency #' . $agencyId . ' linked to tenant #' . (int) $out['tenant_id'] . '.'];
        } elseif ($action === 'db_test' || $action === 'run_migration' || $action === 'rebuild_schema') {
            ControlCenterAccess::requireRole([
                ControlCenterAccess::SUPER_ADMIN,
                ControlCenterAccess::ADMIN,
                ControlCenterAccess::VIEWER,
            ]);
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            $st = $controlPdo->prepare('SELECT * FROM tenants WHERE id=:id LIMIT 1');
            $st->execute([':id' => $tenantId]);
            $tenant = $st->fetch() ?: null;
            if (!$tenant) {
                throw new RuntimeException('Tenant not found.');
            }
            ccAssertTenantDataPlaneActive($controlPdo, $tenantId);
            $tenantDb = ccTenantPdo($tenant);
            $tenantDb->query('SELECT 1');
            if ($action === 'db_test') {
                ccAudit($controlPdo, 'db_test', ['tenant_id' => $tenantId, 'payload' => $auditPost]);
                $alerts[] = ['type' => 'safe', 'text' => 'Connection test succeeded.'];
            } elseif ($action === 'run_migration') {
                ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN, ControlCenterAccess::ADMIN]);
                if (!ControlCenterRateLimiter::check('tenant_actions', $rid, 5, 60)) {
                    throw new RuntimeException('RATE_LIMIT');
                }
                ccLogEvent($controlPdo, 'CONTROL_CENTER migration requested tenant_id=' . $tenantId, 'warn', $tenantId);
                ccAudit($controlPdo, 'run_migration', ['tenant_id' => $tenantId, 'payload' => $auditPost]);
                $alerts[] = ['type' => 'warning', 'text' => 'Migration request logged (manual runner required).'];
            } else {
                ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
                if (!ControlCenterRateLimiter::check('tenant_actions', $rid, 5, 60)) {
                    throw new RuntimeException('RATE_LIMIT');
                }
                if ((string) ($_POST['confirm_text'] ?? '') !== 'REBUILD') {
                    throw new RuntimeException('Rebuild requires confirm text: REBUILD');
                }
                ccLogEvent($controlPdo, 'CONTROL_CENTER schema rebuild requested tenant_id=' . $tenantId, 'error', $tenantId);
                ccAudit($controlPdo, 'rebuild_schema', ['tenant_id' => $tenantId, 'payload' => $auditPost]);
                SystemAlerts::create($controlPdo, 'HIGH', 'schema_rebuild_requested', 'Schema rebuild requested from control center', ['tenant_id' => $tenantId], $tenantId);
                $alerts[] = ['type' => 'danger', 'text' => 'Schema rebuild request logged (manual approval required).'];
            }
        } elseif ($action === 'test_connection') {
            ControlCenterAccess::requireRole([
                ControlCenterAccess::SUPER_ADMIN,
                ControlCenterAccess::ADMIN,
                ControlCenterAccess::VIEWER,
            ]);
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            if ($tenantId <= 0) {
                throw new RuntimeException('tenant_id is required');
            }
            $tenant = ccFindTenant($controlPdo, $tenantId);
            if (!$tenant) {
                throw new RuntimeException('Tenant not found.');
            }
            if ((string) ($tenant['status'] ?? '') === 'suspended') {
                SystemAlerts::create($controlPdo, 'MEDIUM', 'tenant_inactive_access', 'Connection test on suspended tenant', ['tenant_id' => $tenantId], $tenantId);
                throw new RuntimeException('Tenant is suspended.');
            }
            $hasDbName = trim((string) ($tenant['database_name'] ?? '')) !== '';
            $hasDbUser = trim((string) ($tenant['db_user'] ?? '')) !== '';
            if (!$hasDbName || !$hasDbUser) {
                throw new RuntimeException('Tenant DB credentials are incomplete. Please configure database_name and db_user first.');
            }
            try {
                $db = ccGetTenantDbById($tenantId);
                if ($db instanceof PDO) {
                    $db->query('SELECT 1');
                } elseif ($db instanceof mysqli) {
                    $db->query('SELECT 1');
                } else {
                    throw new RuntimeException('Unsupported DB connection type.');
                }
            } catch (Throwable $e) {
                SystemAlerts::create($controlPdo, 'MEDIUM', 'db_connection_failed', 'Tenant DB connection failed', ['tenant_id' => $tenantId], $tenantId);
                error_log('test_connection form: ' . $e->getMessage());
                throw new RuntimeException('Connection failed.');
            }
            ccAudit($controlPdo, 'test_connection', ['tenant_id' => $tenantId]);
            $alerts[] = ['type' => 'safe', 'text' => 'Tenant #' . $tenantId . ' connection successful.'];
        } elseif ($action === 'backup_tenant_sync') {
            ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
            if (!ControlCenterRateLimiter::check('tenant_actions', $rid, 5, 60)) {
                throw new RuntimeException('RATE_LIMIT');
            }
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            if ($tenantId <= 0) {
                throw new RuntimeException('tenant_id is required.');
            }
            ccAssertTenantDataPlaneActive($controlPdo, $tenantId);
            try {
                $file = BackupService::backup($tenantId, $controlPdo, ccBackupStorageDir());
            } catch (Throwable $e) {
                SystemAlerts::create($controlPdo, 'HIGH', 'backup_failed', $e->getMessage(), ['tenant_id' => $tenantId], $tenantId);
                error_log('backup_tenant_sync: ' . $e->getMessage());
                throw new RuntimeException('Backup failed.');
            }
            $basename = basename($file);
            ccAudit($controlPdo, 'backup_tenant', ['tenant_id' => $tenantId, 'payload' => ['file' => $basename]]);
            $alerts[] = ['type' => 'safe', 'text' => 'Backup saved: ' . $basename];
        } elseif ($action === 'restore_tenant_sync') {
            ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
            if (!ControlCenterRateLimiter::check('tenant_actions', $rid, 5, 60)) {
                throw new RuntimeException('RATE_LIMIT');
            }
            $tenantId = (int) ($_POST['tenant_id'] ?? 0);
            $backupFile = (string) ($_POST['backup_file'] ?? '');
            if ($tenantId <= 0 || $backupFile === '') {
                throw new RuntimeException('tenant_id and backup file name are required.');
            }
            if (strtoupper(trim((string) ($_POST['confirm_text'] ?? ''))) !== 'RESTORE') {
                throw new RuntimeException('Restore requires confirm text: RESTORE');
            }
            ccAssertTenantDataPlaneActive($controlPdo, $tenantId);
            $resolved = ccResolveTenantBackupFile($backupFile);
            if ($resolved === null) {
                throw new RuntimeException('Backup file not found.');
            }
            try {
                BackupService::restore($tenantId, $controlPdo, $resolved);
            } catch (Throwable $e) {
                SystemAlerts::create($controlPdo, 'CRITICAL', 'restore_failed', $e->getMessage(), ['tenant_id' => $tenantId], $tenantId);
                error_log('restore_tenant_sync: ' . $e->getMessage());
                throw new RuntimeException('Restore failed.');
            }
            ccAudit($controlPdo, 'restore_tenant', ['tenant_id' => $tenantId, 'payload' => ['backup_file' => basename($resolved)]]);
            $alerts[] = ['type' => 'danger', 'text' => 'Restore completed for tenant #' . $tenantId . '.'];
        } elseif ($action === 'query_execute' || $action === 'query_validate') {
            if (!ControlCenterRateLimiter::check('query_console', $rid, 10, 10)) {
                throw new RuntimeException('RATE_LIMIT');
            }
            $sql = trim((string) ($_POST['sql'] ?? ''));
            $mode = strtoupper(trim((string) ($_POST['execution_mode'] ?? 'SAFE')));
            $tenantId = (int) ($_POST['query_tenant_id'] ?? 0);
            $confirmWrite = (string) ($_POST['query_confirm_write'] ?? '0');
            if ($sql === '') {
                throw new RuntimeException('SQL cannot be empty.');
            }
            if ($action === 'query_validate') {
                ControlCenterAccess::requireRole([
                    ControlCenterAccess::SUPER_ADMIN,
                    ControlCenterAccess::ADMIN,
                    ControlCenterAccess::VIEWER,
                ]);
                if (!ControlCenterQueryValidator::isSafe($sql)) {
                    SystemAlerts::create($controlPdo, 'HIGH', 'unsafe_query', 'Blocked unsafe query at validation', ['tenant_id' => $tenantId], $tenantId > 0 ? $tenantId : null);
                    ControlCenterMetrics::bump($controlPdo, 'safety_warn', 1);
                    ccAudit($controlPdo, 'query_validate_blocked', ['tenant_id' => $tenantId > 0 ? $tenantId : null, 'payload' => ['mode' => $mode]]);
                    throw new RuntimeException('Query failed safety validation.');
                }
                ccAudit($controlPdo, 'query_validate', ['tenant_id' => $tenantId > 0 ? $tenantId : null, 'payload' => ['mode' => $mode, 'read_only' => ControlCenterQueryValidator::isReadOnlyStatement($sql)]]);
                $queryResult = [
                    'validated' => true,
                    'message' => 'Query passed safety checks (not executed).',
                    'read_only' => ControlCenterQueryValidator::isReadOnlyStatement($sql),
                ];
            } else {
                try {
                    $out = ccExecuteControlQuery($controlPdo, $sql, $tenantId, $mode, $confirmWrite);
                } catch (Throwable $qe) {
                    $qm = $qe->getMessage();
                    if ($qm === 'CONTROL_CENTER_FORBIDDEN') {
                        throw $qe;
                    }
                    if ($qm === 'Unsafe query blocked by validator') {
                        throw $qe;
                    }
                    error_log('query_execute: ' . $qm);
                    throw new RuntimeException('Query could not be executed.');
                }
                $queryResult = [
                    'validated' => true,
                    'message' => 'Query executed successfully.',
                    'elapsed_ms' => $out['execution_ms'],
                    'rows_affected' => $out['rows_affected'],
                    'rows' => $out['result'],
                ];
            }
        } elseif ($action === 'emergency_action') {
            ControlCenterAccess::requireRole([ControlCenterAccess::SUPER_ADMIN]);
            if (!ControlCenterRateLimiter::check('tenant_actions', $rid, 5, 60)) {
                throw new RuntimeException('RATE_LIMIT');
            }
            $code = (string) ($_POST['emergency_code'] ?? '');
            $confirm1 = (string) ($_POST['confirm_text'] ?? '');
            $confirm2 = (string) ($_POST['confirm_text_second'] ?? '');
            if ($confirm1 !== 'CONFIRM' || $confirm2 !== 'CONFIRM') {
                throw new RuntimeException('Double confirmation is required.');
            }
            ccLogEvent($controlPdo, 'CONTROL_CENTER emergency action=' . $code, 'error', null);
            ccAudit($controlPdo, 'emergency_action', ['tenant_id' => null, 'payload' => array_merge($auditPost, ['emergency_code' => $code])]);
            SystemAlerts::create($controlPdo, 'CRITICAL', 'emergency_action', 'Emergency control invoked: ' . $code, ['code' => $code], null);
            $_SESSION['cc_emergency'][$code] = true;
            $alerts[] = ['type' => 'danger', 'text' => 'Emergency action "' . $code . '" recorded.'];
        }
    } catch (Throwable $e) {
        $act = (string) ($_POST['action'] ?? '');
        $msg = $e->getMessage();
        error_log('control-center POST ' . $act . ': ' . $msg);
        if ($msg === 'CONTROL_CENTER_FORBIDDEN') {
            $alerts[] = ['type' => 'danger', 'text' => 'You do not have permission for this action.'];
        } elseif ($msg === 'RATE_LIMIT') {
            $alerts[] = ['type' => 'warning', 'text' => 'Too many requests. Please wait and try again.'];
        } elseif (strpos($act, 'query_') === 0) {
            $queryError = str_contains($msg, 'Unsafe query blocked') || str_contains($msg, 'safety validation')
                ? $msg
                : 'Query could not be completed. Check permissions, mode, and tenant status.';
        } else {
            $safeUser = str_contains($msg, 'CSRF')
                || str_contains($msg, 'required')
                || str_contains($msg, 'not found')
                || str_contains($msg, 'suspended')
                || str_contains($msg, 'confirm text')
                || str_contains($msg, 'confirmation')
                || str_contains($msg, 'incomplete')
                || str_contains($msg, 'Provisioning failed');
            $alerts[] = ['type' => 'danger', 'text' => $safeUser ? $msg : 'Request could not be completed.'];
        }
    }
}

$tenants = [];
$ccSourceDb = '';
$ccManagedCount = 0;
$ccAgencyShadowCount = 0;
$gatewayRows = [];
$safetyRows = [];
$eventsRows = [];
$totalEvents = 0;
$eventsPerPage = 20;
$eventsPage = max(1, (int) ($_GET['events_page'] ?? 1));
$eventsOffset = ($eventsPage - 1) * $eventsPerPage;
$eventKeyword = trim((string) ($_GET['event_keyword'] ?? ''));
$eventLevel = trim((string) ($_GET['event_level'] ?? ''));
$eventTenant = (int) ($_GET['event_tenant_id'] ?? 0);

if ($controlPdo instanceof PDO) {
    try {
        // Auto-heal legacy data: when super admin opens control center, attempt
        // to link currently unlinked agencies to tenants (best effort).
        if ($isSuperAdminCc && ccTableExists($controlPdo, 'control_agencies')) {
            $shouldRunAutoLink = true;
            if (!isset($_SESSION['cc_auto_link_last_run']) || !is_int($_SESSION['cc_auto_link_last_run'])) {
                $_SESSION['cc_auto_link_last_run'] = 0;
            }
            $elapsed = time() - (int) $_SESSION['cc_auto_link_last_run'];
            if ($elapsed < 5) {
                $shouldRunAutoLink = false;
            }
            if ($shouldRunAutoLink) {
                $_SESSION['cc_auto_link_last_run'] = time();
                $autoLink = ccAutoLinkUnlinkedAgencies($controlPdo, 200);
                if ($autoLink['attempted'] > 0) {
                    $alerts[] = [
                        'type' => $autoLink['failed'] > 0 ? 'warning' : 'safe',
                        'text' => 'Auto-link run: attempted ' . (int) $autoLink['attempted'] . ', linked ' . (int) $autoLink['linked'] . ', failed ' . (int) $autoLink['failed'] . '.',
                    ];
                }
            }
        }

        $ccSourceDb = (string) ($controlPdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
        $tenants = ccManagedResources($controlPdo);
        foreach ($tenants as $row) {
            $ccManagedCount++;
            if ((string) ($row['resource_type'] ?? '') === 'agency') {
                $ccAgencyShadowCount++;
            }
        }

        if (ccTableExists($controlPdo, 'system_events')) {
            $base = 'SELECT id, event_type, level, tenant_id, message, metadata, request_id, source, created_at FROM system_events';
            $where = [];
            $params = [];
            if ($eventKeyword !== '') {
                $where[] = '(message LIKE :kw OR metadata LIKE :kw OR event_type LIKE :kw)';
                $params[':kw'] = '%' . $eventKeyword . '%';
            }
            if ($eventLevel !== '') {
                $where[] = 'level = :lvl';
                $params[':lvl'] = $eventLevel;
            }
            if ($eventTenant > 0) {
                $where[] = 'tenant_id = :tid';
                $params[':tid'] = $eventTenant;
            }
            $whereSql = empty($where) ? '' : (' WHERE ' . implode(' AND ', $where));

            $countStmt = $controlPdo->prepare("SELECT COUNT(*) AS c FROM system_events{$whereSql}");
            $countStmt->execute($params);
            $totalEvents = (int) ($countStmt->fetch()['c'] ?? 0);

            $listSql = "{$base}{$whereSql} ORDER BY id DESC LIMIT {$eventsPerPage} OFFSET {$eventsOffset}";
            $listStmt = $controlPdo->prepare($listSql);
            $listStmt->execute($params);
            $eventsRows = $listStmt->fetchAll();

            $scan = $controlPdo->query('SELECT id, event_type, level, tenant_id, message, metadata, created_at FROM system_events ORDER BY id DESC LIMIT 200')->fetchAll();
            foreach ($scan as $row) {
                $combined = strtolower((string) (($row['event_type'] ?? '') . ' ' . ($row['message'] ?? '') . ' ' . ($row['metadata'] ?? '')));
                if ((string) ($row['event_type'] ?? '') === 'QUERY_GATEWAY_POLICY' && count($gatewayRows) < 100) {
                    $metaRaw = (string) ($row['metadata'] ?? '');
                    $meta = json_decode($metaRaw, true);
                    $decision = (strpos($combined, 'violation') !== false || strpos($combined, 'blocked') !== false) ? 'blocked'
                        : ((strpos($combined, 'warn') !== false) ? 'warned' : 'allowed');
                    $reason = (string) ($meta['policy_reason'] ?? ((strpos($combined, 'strict') !== false) ? 'STRICT_MODE' : 'GENERAL_POLICY'));
                    $gatewayRows[] = [
                        'tenant_id' => (int) ($row['tenant_id'] ?? 0),
                        'decision' => $decision,
                        'reason' => $reason,
                        'query' => substr((string) ($row['message'] ?? ''), 0, 240),
                        'created_at' => (string) ($row['created_at'] ?? ''),
                    ];
                }
                $hasSafety = strpos($combined, 'unsafe_query') !== false
                    || strpos($combined, 'security_block') !== false
                    || strpos($combined, 'tenant_query_safety_warning') !== false
                    || strpos($combined, 'strict_mode') !== false;
                if ($hasSafety && count($safetyRows) < 100) {
                    $metaRaw = (string) ($row['metadata'] ?? '');
                    $meta = json_decode($metaRaw, true);
                    $event = (string) ($row['event_type'] ?? 'SAFETY_EVENT');
                    $safetyRows[] = [
                        'tenant_id' => (int) ($row['tenant_id'] ?? 0),
                        'endpoint' => (string) ($meta['endpoint'] ?? ''),
                        'event' => $event,
                        'reason' => (string) ($row['message'] ?? ''),
                        'created_at' => (string) ($row['created_at'] ?? ''),
                    ];
                }
            }
        }
    } catch (Throwable $e) {
        $alerts[] = ['type' => 'danger', 'text' => 'Data loading failed: ' . $e->getMessage()];
    }
}

$ccMetrics = ['queries_ok' => 0, 'queries_fail' => 0, 'safety_warnings' => 0, 'queries_last_minute' => 0];
$activeTenantCount = 0;
$suspendedTenantCount = 0;
$inactiveTenantCount = 0;
$dashboardCriticalEvents = [];
$adminAuditRows = [];
if ($controlPdo instanceof PDO) {
    try {
        $ccMetrics = array_merge($ccMetrics, ControlCenterMetrics::getCounters($controlPdo));
        if (ccTableExists($controlPdo, 'admin_control_metrics')) {
            $st = $controlPdo->query(
                "SELECT COALESCE(SUM(metric_value),0) AS c FROM admin_control_metrics
                 WHERE created_at > (NOW() - INTERVAL 1 MINUTE)
                 AND metric_key IN ('query_ok','query_fail')"
            );
            if ($st) {
                $ccMetrics['queries_last_minute'] = (int) ($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);
            }
        }
        $dashboardCriticalEvents = SystemAlerts::recent($controlPdo, 15);
        if (ccTableExists($controlPdo, 'system_events')) {
            $adminAuditRows = $controlPdo->query(
                "SELECT id,
                        user_id,
                        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.role')) AS role,
                        JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.action')) AS action,
                        tenant_id,
                        LEFT(COALESCE(metadata, ''), 160) AS payload_preview,
                        created_at
                 FROM system_events
                 WHERE event_type = 'ADMIN_AUDIT'
                 ORDER BY id DESC LIMIT 50"
            )->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        /* ignore */
    }
}
$activeTenantCount = count(array_filter($tenants, static function ($t) {
    return !empty($t['has_tenant']) && ((string) ($t['status'] ?? '')) === 'active';
}));
$suspendedTenantCount = count(array_filter($tenants, static function ($t) {
    return ((string) ($t['status'] ?? '')) === 'suspended';
}));
$inactiveTenantCount = count(array_filter($tenants, static function ($t) {
    return ((string) ($t['status'] ?? '')) === 'inactive';
}));

$eventsTotalPages = max(1, (int) ceil($totalEvents / max(1, $eventsPerPage)));
$csrfToken = ccCsrfToken();
$scriptDirUrl = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/control-center.php'))), '/');
if ($scriptDirUrl === '') {
    $scriptDirUrl = '/admin';
}
$assetCssPath = __DIR__ . '/assets/css/control-center.css';
$assetJsPath = __DIR__ . '/assets/js/control-center.js';
$assetCssVersion = file_exists($assetCssPath) ? (string) filemtime($assetCssPath) : '1';
$assetJsVersion = file_exists($assetJsPath) ? (string) filemtime($assetJsPath) : '1';
$siteUrl = rtrim((defined('SITE_URL') ? (string) SITE_URL : ''), '/');
$assetBase = $siteUrl !== '' ? ($siteUrl . '/admin/control-center-assets.php') : ($scriptDirUrl . '/control-center-assets.php');
$assetCssUrl = $assetBase . '?file=css&v=' . rawurlencode($assetCssVersion);
$assetJsUrl = $assetBase . '?file=js&v=' . rawurlencode($assetJsVersion);
$helpCenterUrl = ($siteUrl !== '' ? $siteUrl : '') . '/admin/help-center.php?lang=ar';
$staticCssUrl = ($siteUrl !== '' ? $siteUrl : '') . '/admin/assets/css/control-center.css?v=' . rawurlencode($assetCssVersion);
$staticJsUrl = ($siteUrl !== '' ? $siteUrl : '') . '/admin/assets/js/control-center.js?v=' . rawurlencode($assetJsVersion);
$relativeCssUrl = 'assets/css/control-center.css?v=' . rawurlencode($assetCssVersion);
$relativeJsUrl = 'assets/js/control-center.js?v=' . rawurlencode($assetJsVersion);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Control Center</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assetCssUrl, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body data-cc-csrf="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>" data-cc-role="<?php echo htmlspecialchars($ccRole, ENT_QUOTES, 'UTF-8'); ?>">
<div class="cc-warning">⚠️ ADMIN CONTROL MODE — ACTIONS ARE LIVE AND EFFECT SYSTEM-WIDE</div>
<div class="cc-layout">
    <aside class="cc-sidebar">
        <h2>Control Center</h2>
        <a href="#overview">Overview</a>
        <a href="#tenant-control">Tenant Control</a>
        <a href="#db-control">Database Control</a>
        <a href="#query-console">Query Console</a>
        <a href="#gateway-policies">Gateway Policies</a>
        <a href="#safety-events">Safety Events</a>
        <a href="#system-alerts">Critical Events</a>
        <a href="#admin-audit">Event History</a>
        <a href="#logs-explorer">Events Explorer</a>
        <a href="<?php echo htmlspecialchars(($siteUrl !== '' ? $siteUrl : '') . '/admin/event-timeline.php', ENT_QUOTES, 'UTF-8'); ?>">Event Timeline</a>
        <a href="<?php echo htmlspecialchars(($siteUrl !== '' ? $siteUrl : '') . '/admin/event-flow.php', ENT_QUOTES, 'UTF-8'); ?>">Event Flow</a>
        <a href="#system-flags">System Flags</a>
        <a href="#emergency-controls">Emergency Controls</a>
        <a href="<?php echo htmlspecialchars($helpCenterUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Help Center</a>
    </aside>
    <main class="cc-main">
        <header class="cc-topbar">
            <div>
                <h1>Admin Full Control Center</h1>
                <p>Execution + observability + system control plane</p>
            </div>
            <div class="cc-live-wrap">
                <a class="cc-live" href="<?php echo htmlspecialchars($helpCenterUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Help Center</a>
                <label class="cc-live"><input type="checkbox" id="liveMode"> Live Auto Refresh (5s)</label>
                <span id="liveStatusBadge" class="live-status hidden">Live paused while typing</span>
            </div>
        </header>

        <?php foreach ($alerts as $alert): ?>
            <div class="cc-alert <?php echo htmlspecialchars((string) $alert['type'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars((string) $alert['text'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endforeach; ?>

        <section id="system-alerts" class="cc-card cc-alerts-panel">
            <h3>Critical Events</h3>
            <p class="cc-role-hint">Role: <strong><?php echo htmlspecialchars($ccRole, ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <?php if (empty($dashboardCriticalEvents)): ?>
                <p class="cc-muted">No recent critical events.</p>
            <?php else: ?>
                <div class="cc-alert-strip">
                    <?php foreach ($dashboardCriticalEvents as $da): ?>
                        <div class="cc-alert-item sev-<?php echo htmlspecialchars(strtolower((string) $da['severity']), ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="cc-alert-sev"><?php echo htmlspecialchars((string) $da['severity'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="cc-alert-msg"><?php echo htmlspecialchars((string) $da['message'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="cc-alert-meta"><?php echo htmlspecialchars((string) ($da['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($da['tenant_id']) ? ' · tenant #' . (int) $da['tenant_id'] : ''; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section id="overview" class="cc-card">
            <h3>Overview</h3>
            <div class="cc-grid stats cc-stats-6">
                <div class="stat safe"><span>Total Tenants</span><strong><?php echo count($tenants); ?></strong></div>
                <div class="stat safe"><span>Active Tenants</span><strong><?php echo (int) $activeTenantCount; ?></strong></div>
                <div class="stat warning"><span>Suspended</span><strong><?php echo (int) $suspendedTenantCount; ?></strong></div>
                <div class="stat danger"><span>Inactive</span><strong><?php echo (int) $inactiveTenantCount; ?></strong></div>
                <div class="stat system"><span>Queries (1h ok)</span><strong><?php echo (int) $ccMetrics['queries_ok']; ?></strong></div>
                <div class="stat danger"><span>Failed Queries (1h)</span><strong><?php echo (int) $ccMetrics['queries_fail']; ?></strong></div>
                <div class="stat warning"><span>Queries / min</span><strong><?php echo (int) $ccMetrics['queries_last_minute']; ?></strong></div>
                <div class="stat warning"><span>Safety warnings (1h)</span><strong><?php echo (int) $ccMetrics['safety_warnings']; ?></strong></div>
            </div>
            <div class="cc-grid stats cc-stats-secondary">
                <div class="stat system"><span>System Mode</span><strong><?php echo (defined('TENANT_STRICT_MODE') && TENANT_STRICT_MODE) ? 'STRICT' : 'SAFE'; ?></strong></div>
                <div class="stat warning"><span>Gateway Decisions</span><strong><?php echo count($gatewayRows); ?></strong></div>
                <div class="stat danger"><span>Safety Events</span><strong><?php echo count($safetyRows); ?></strong></div>
            </div>
        </section>

        <section id="tenant-control" class="cc-card">
            <h3>Tenant Control</h3>
            <?php if ($isSuperAdminCc): ?>
                <p class="cc-muted cc-tenant-source-meta">
                    Source DB: <strong><?php echo htmlspecialchars($ccSourceDb !== '' ? $ccSourceDb : '(unknown)', ENT_QUOTES, 'UTF-8'); ?></strong>
                    | Managed rows: <strong><?php echo (int) $ccManagedCount; ?></strong>
                    | Unlinked agency rows: <strong><?php echo (int) $ccAgencyShadowCount; ?></strong>
                </p>
            <?php endif; ?>
            <?php if (!$isSuperAdminCc): ?>
                <p class="cc-muted">Creating tenants requires <strong>SUPER_ADMIN</strong>. You can still view and test connections per your role.</p>
            <?php endif; ?>
            <form method="post" class="cc-form-grid">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="tenant_create">
                <fieldset class="cc-fieldset-plain" <?php echo $isSuperAdminCc ? '' : 'disabled'; ?>>
                <input type="text" name="name" placeholder="Tenant Name" required>
                <input type="text" name="domain" placeholder="Domain" required>
                <input type="text" name="database_name" placeholder="Database Name (optional — auto if empty)">
                <input type="text" name="db_host" placeholder="DB Host">
                <input type="text" name="db_user" placeholder="DB User">
                <input type="password" name="db_password" placeholder="DB Password">
                <select name="status"><option value="provisioning">provisioning</option><option value="active" selected>active</option><option value="suspended">suspended</option></select>
                <button type="submit">Create Tenant</button>
                </fieldset>
            </form>
            <div class="cc-table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Name</th><th>Domain</th><th>Status</th><th>DB Config</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($tenants)): ?>
                        <tr><td colspan="7">No tenants found.</td></tr>
                    <?php else: foreach ($tenants as $t): ?>
                        <?php
                        $hasDbConfig = trim((string) ($t['database_name'] ?? '')) !== '' && trim((string) ($t['db_user'] ?? '')) !== '';
                        $isLinkedTenant = !empty($t['has_tenant']) && (int) ($t['id'] ?? 0) > 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($t['display_id'] ?? (string) (int) ($t['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $t['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $t['domain'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="badge <?php echo htmlspecialchars((string) $t['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $t['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><span class="db-badge <?php echo $hasDbConfig ? 'ok' : 'missing'; ?>"><?php echo $hasDbConfig ? 'configured' : 'missing'; ?></span></td>
                            <td><?php echo htmlspecialchars((string) $t['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="row-actions">
                                <?php if (!$isLinkedTenant): ?>
                                <span class="cc-muted cc-tenant-link-pending">Tenant link pending</span>
                                <?php endif; ?>
                                <?php if ($isLinkedTenant && $isAdminOrAboveCc): ?>
                                <button type="button" class="edit-btn"
                                        data-id="<?php echo (int) $t['id']; ?>"
                                        data-name="<?php echo htmlspecialchars((string) $t['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-domain="<?php echo htmlspecialchars((string) $t['domain'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-db-name="<?php echo htmlspecialchars((string) $t['database_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-db-host="<?php echo htmlspecialchars((string) ($t['db_host'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-db-user="<?php echo htmlspecialchars((string) ($t['db_user'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-status="<?php echo htmlspecialchars((string) $t['status'], ENT_QUOTES, 'UTF-8'); ?>">Edit</button>
                                <?php endif; ?>
                                <?php if ($isLinkedTenant && (string) $t['status'] === 'active' && $isSuperAdminCc): ?>
                                <form method="post" class="inline danger-form" data-prompt="Type SUSPEND to continue">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="tenant_toggle">
                                    <input type="hidden" name="tenant_id" value="<?php echo (int) $t['id']; ?>">
                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars((string) $t['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="confirm_text" value="">
                                    <button type="submit">Suspend</button>
                                </form>
                                <?php elseif ($isLinkedTenant && (string) $t['status'] !== 'active' && $isAdminOrAboveCc): ?>
                                <form method="post" class="inline danger-form" data-prompt="Type ACTIVATE to continue">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="tenant_toggle">
                                    <input type="hidden" name="tenant_id" value="<?php echo (int) $t['id']; ?>">
                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars((string) $t['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="confirm_text" value="">
                                    <button type="submit">Activate</button>
                                </form>
                                <?php endif; ?>
                                <?php if ($isLinkedTenant && $isSuperAdminCc): ?>
                                <form method="post" class="inline danger-form" data-confirm="Delete tenant <?php echo (int) $t['id']; ?>?" data-prompt="Type DELETE to continue">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="tenant_delete">
                                    <input type="hidden" name="tenant_id" value="<?php echo (int) $t['id']; ?>">
                                    <input type="hidden" name="confirm_text" value="">
                                    <button type="submit">Delete</button>
                                </form>
                                <?php endif; ?>
                                <?php if (!$isLinkedTenant): ?>
                                <?php if ($isSuperAdminCc): ?>
                                <form method="post" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="action" value="tenant_link_agency">
                                    <input type="hidden" name="agency_id" value="<?php echo (int) ($t['agency_id'] ?? 0); ?>">
                                    <button type="submit">Link Now</button>
                                </form>
                                <?php endif; ?>
                                <a href="../control-panel/pages/control/agencies.php?control=1" class="btn-link" title="Open agencies and run Repair Missing Tenant Link">Open Agencies</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="db-control" class="cc-card">
            <h3>Database Control Panel</h3>
            <div class="cc-table-wrap">
                <table>
                    <thead><tr><th>Tenant</th><th>Connection</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($tenants)): ?>
                        <tr><td colspan="3">No tenants available.</td></tr>
                    <?php else: foreach ($tenants as $t): ?>
                        <tr>
                            <?php $isLinkedTenant = !empty($t['has_tenant']) && (int) ($t['id'] ?? 0) > 0; ?>
                            <td>#<?php echo htmlspecialchars((string) ($t['display_id'] ?? (string) (int) ($t['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) $t['domain'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php $dbReady = trim((string) ($t['database_name'] ?? '')) !== '' && trim((string) ($t['db_user'] ?? '')) !== ''; ?>
                            <td>
                                <?php echo htmlspecialchars((string) ($t['db_host'] ?: 'localhost'), ENT_QUOTES, 'UTF-8'); ?>
                                /
                                <?php echo htmlspecialchars((string) ($t['database_name'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?>
                                / ****
                                <span class="db-badge <?php echo $dbReady ? 'ok' : 'missing'; ?>"><?php echo $dbReady ? 'configured' : 'missing'; ?></span>
                            </td>
                            <td class="row-actions">
                                <?php if ($isLinkedTenant): ?>
                                <form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="db_test"><input type="hidden" name="tenant_id" value="<?php echo (int) $t['id']; ?>"><button type="submit">Test Connection</button></form>
                                <?php if ($isAdminOrAboveCc): ?>
                                <form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="run_migration"><input type="hidden" name="tenant_id" value="<?php echo (int) $t['id']; ?>"><button type="submit">Run Migration</button></form>
                                <?php endif; ?>
                                <?php if ($isSuperAdminCc): ?>
                                <form method="post" class="inline danger-form" data-prompt="Type REBUILD to continue"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="rebuild_schema"><input type="hidden" name="tenant_id" value="<?php echo (int) $t['id']; ?>"><input type="hidden" name="confirm_text" value=""><button type="submit">Rebuild Schema</button></form>
                                <form method="post" class="inline danger-form" data-prompt="Type BACKUP to continue"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="backup_tenant_sync"><input type="hidden" name="tenant_id" value="<?php echo (int) $t['id']; ?>"><input type="hidden" name="confirm_text" value=""><button type="submit" title="Requires CONTROL_CENTER_BACKUP_ENABLED and mysqldump">Backup DB</button></form>
                                <form method="post" class="inline danger-form" data-prompt="Type RESTORE to continue"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="action" value="restore_tenant_sync"><input type="hidden" name="tenant_id" value="<?php echo (int) $t['id']; ?>"><input type="text" name="backup_file" placeholder="backup filename.sql" required class="cc-restore-file-input"><input type="hidden" name="confirm_text" value=""><button type="submit" title="Irreversible — overwrites tenant DB">Restore</button></form>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="cc-muted">Tenant link required (run Repair Missing Tenant Link in Agencies).</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="query-console" class="cc-card">
            <h3>Query Console (Execution Engine)</h3>
            <form method="post" id="queryForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" id="queryAction" value="query_execute">
                <input type="hidden" name="query_confirm_write" id="queryConfirmWrite" value="0">
                <textarea name="sql" id="sqlEditor" rows="6" placeholder="SELECT * FROM tenants LIMIT 10;"></textarea>
                <div class="cc-form-row">
                    <select name="query_tenant_id" id="queryTenantSelect">
                        <option value="0">System Context</option>
                        <?php foreach ($tenants as $t): ?>
                            <?php if (empty($t['has_tenant']) || (int) ($t['id'] ?? 0) <= 0) { continue; } ?>
                            <option value="<?php echo (int) $t['id']; ?>">Tenant #<?php echo (int) $t['id']; ?> - <?php echo htmlspecialchars((string) $t['domain'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="execution_mode" id="executionModeSelect">
                        <option value="SAFE">SAFE (read-only)</option>
                        <option value="STRICT">STRICT (admin writes + tenant_id)</option>
                        <option value="SYSTEM" <?php echo $isSuperAdminCc ? '' : 'disabled'; ?>>SYSTEM (super-admin)</option>
                    </select>
                    <button type="submit" data-action="query_execute">Run Query</button>
                    <button type="submit" data-action="query_validate">Validate Query</button>
                    <button type="button" id="clearSql">Clear</button>
                </div>
            </form>
            <?php if ($queryError !== null): ?>
                <div class="cc-alert danger"><?php echo htmlspecialchars($queryError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if (is_array($queryResult)): ?>
                <div class="cc-result-meta">
                    <span><?php echo htmlspecialchars((string) ($queryResult['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if (isset($queryResult['elapsed_ms'])): ?><span>Execution: <?php echo (int) $queryResult['elapsed_ms']; ?> ms</span><?php endif; ?>
                    <?php if (isset($queryResult['rows_affected'])): ?><span>Rows affected: <?php echo (int) $queryResult['rows_affected']; ?></span><?php endif; ?>
                </div>
                <?php if (!empty($queryResult['rows']) && is_array($queryResult['rows'])): ?>
                    <div class="cc-table-wrap">
                        <table>
                            <thead><tr><?php foreach (array_keys($queryResult['rows'][0]) as $col): ?><th><?php echo htmlspecialchars((string) $col, ENT_QUOTES, 'UTF-8'); ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                            <?php foreach ($queryResult['rows'] as $row): ?>
                                <tr><?php foreach ($row as $cell): ?><td><?php echo htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8'); ?></td><?php endforeach; ?></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <section id="gateway-policies" class="cc-card">
            <h3>Gateway Policies</h3>
            <div class="cc-form-row">
                <input type="text" id="gatewaySearch" placeholder="Search query preview...">
                <select id="gatewayStatusFilter"><option value="all">All</option><option value="allowed">Allowed</option><option value="blocked">Blocked</option><option value="warned">Warned</option></select>
                <input type="number" id="gatewayTenantFilter" placeholder="Tenant ID">
            </div>
            <div class="cc-table-wrap">
                <table id="gatewayTable">
                    <thead><tr><th>Time</th><th>Tenant</th><th>Status</th><th>Reason</th><th>Query</th></tr></thead>
                    <tbody>
                    <?php if (empty($gatewayRows)): ?>
                        <tr><td colspan="5">No gateway rows.</td></tr>
                    <?php else: foreach ($gatewayRows as $g): ?>
                        <tr data-status="<?php echo htmlspecialchars((string) $g['decision'], ENT_QUOTES, 'UTF-8'); ?>" data-tenant="<?php echo (int) $g['tenant_id']; ?>">
                            <td><?php echo htmlspecialchars((string) $g['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $g['tenant_id']; ?></td>
                            <td><span class="badge <?php echo htmlspecialchars((string) $g['decision'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $g['decision'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo htmlspecialchars((string) $g['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $g['query'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="safety-events" class="cc-card">
            <h3>Safety Events</h3>
            <div class="cc-table-wrap">
                <table>
                    <thead><tr><th>Tenant</th><th>Endpoint</th><th>Event</th><th>Reason</th><th>Time</th></tr></thead>
                    <tbody>
                    <?php if (empty($safetyRows)): ?>
                        <tr><td colspan="5">No safety events.</td></tr>
                    <?php else: foreach ($safetyRows as $s): ?>
                        <tr>
                            <td><?php echo (int) $s['tenant_id']; ?></td>
                            <td><?php echo htmlspecialchars((string) $s['endpoint'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $s['event'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $s['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $s['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="admin-audit" class="cc-card">
            <h3>Event History</h3>
            <p class="cc-muted">Recent actions on the control plane (sourced from <code>system_events</code>).</p>
            <div class="cc-table-wrap">
                <table>
                    <thead><tr><th>Time</th><th>Role</th><th>Action</th><th>User</th><th>Tenant</th><th>Payload (preview)</th></tr></thead>
                    <tbody>
                    <?php if (empty($adminAuditRows)): ?>
                        <tr><td colspan="6">No audit rows yet or table not installed.</td></tr>
                    <?php else: foreach ($adminAuditRows as $ar): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($ar['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($ar['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($ar['action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) ($ar['user_id'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string) ($ar['tenant_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><code><?php echo htmlspecialchars((string) ($ar['payload_preview'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="logs-explorer" class="cc-card">
            <h3>Events Explorer</h3>
            <form method="get" class="cc-form-row">
                <input type="text" name="event_keyword" value="<?php echo htmlspecialchars($eventKeyword, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Keyword">
                <select name="event_level">
                    <option value="">All Levels</option>
                    <option value="info" <?php echo $eventLevel === 'info' ? 'selected' : ''; ?>>info</option>
                    <option value="warn" <?php echo $eventLevel === 'warn' ? 'selected' : ''; ?>>warn</option>
                    <option value="error" <?php echo $eventLevel === 'error' ? 'selected' : ''; ?>>error</option>
                </select>
                <input type="number" name="event_tenant_id" value="<?php echo $eventTenant > 0 ? (int) $eventTenant : ''; ?>" placeholder="Tenant ID">
                <button type="submit">Filter</button>
            </form>
            <div class="cc-table-wrap">
                <table>
                    <thead><tr><th>Time</th><th>Level</th><th>Type</th><th>Tenant</th><th>Request</th><th>Message</th></tr></thead>
                    <tbody>
                    <?php if (empty($eventsRows)): ?>
                        <tr><td colspan="6">No events available.</td></tr>
                    <?php else: foreach ($eventsRows as $l): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($l['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($l['level'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($l['event_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($l['tenant_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><code><?php echo htmlspecialchars((string) ($l['request_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                            <td><?php echo htmlspecialchars(substr((string) ($l['message'] ?? ''), 0, 260), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="cc-pagination">
                <a href="?events_page=<?php echo max(1, $eventsPage - 1); ?>&event_keyword=<?php echo urlencode($eventKeyword); ?>&event_level=<?php echo urlencode($eventLevel); ?>&event_tenant_id=<?php echo (int) $eventTenant; ?>#logs-explorer">Prev</a>
                <span>Page <?php echo $eventsPage; ?> / <?php echo $eventsTotalPages; ?></span>
                <a href="?events_page=<?php echo min($eventsTotalPages, $eventsPage + 1); ?>&event_keyword=<?php echo urlencode($eventKeyword); ?>&event_level=<?php echo urlencode($eventLevel); ?>&event_tenant_id=<?php echo (int) $eventTenant; ?>#logs-explorer">Next</a>
            </div>
        </section>

        <section id="system-flags" class="cc-card">
            <h3>System Flags</h3>
            <div class="cc-flags">
                <?php
                $flags = [
                    'TENANT_STRICT_MODE' => defined('TENANT_STRICT_MODE') ? (bool) TENANT_STRICT_MODE : false,
                    'TENANT_ENFORCE_CONTEXT_ON_API' => defined('TENANT_ENFORCE_CONTEXT_ON_API') ? (bool) TENANT_ENFORCE_CONTEXT_ON_API : false,
                    'QUERY_GATEWAY_ENABLED' => true,
                    'DEBUG_MODE' => defined('DEBUG_MODE') ? (bool) DEBUG_MODE : false,
                ];
                foreach ($flags as $name => $enabled):
                ?>
                    <div class="flag-item"><span><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></span><label class="switch"><input type="checkbox" <?php echo $enabled ? 'checked' : ''; ?> disabled><span class="slider"></span></label></div>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="emergency-controls" class="cc-card danger-zone">
            <h3>Emergency Controls</h3>
            <?php if (!$isSuperAdminCc): ?>
                <p class="cc-muted">Emergency controls require <strong>SUPER_ADMIN</strong>.</p>
            <?php else: ?>
            <p class="cc-irreversible">Irreversible or high-impact effects may apply. Double confirmation required. Every action is audited and raises a <strong>CRITICAL</strong> alert.</p>
            <div class="cc-form-row wrap">
                <?php
                $emergencyActions = [
                    'maintenance_mode' => 'Enable Maintenance Mode',
                    'disable_all_tenants' => 'Disable All Tenants',
                    'kill_query_execution' => 'Kill Query Execution',
                    'lock_gateway' => 'Lock Gateway (Read-Only)',
                ];
                foreach ($emergencyActions as $code => $label):
                ?>
                <form method="post" class="inline emergency-form" data-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="emergency_action">
                    <input type="hidden" name="emergency_code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="confirm_text" value="">
                    <input type="hidden" name="confirm_text_second" value="">
                    <button type="submit" class="danger-btn">🔴 <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<div id="editTenantModal" class="modal hidden">
    <div class="modal-content">
        <h3>Edit Tenant</h3>
        <form method="post" id="editTenantForm" class="cc-form-grid">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="tenant_update">
            <input type="hidden" name="tenant_id" id="editTenantId">
            <fieldset class="cc-fieldset-plain" <?php echo $isAdminOrAboveCc ? '' : 'disabled'; ?>>
            <input type="text" name="name" id="editTenantName" required>
            <input type="text" name="domain" id="editTenantDomain" required>
            <input type="text" name="database_name" id="editTenantDbName">
            <input type="text" name="db_host" id="editTenantDbHost">
            <input type="text" name="db_user" id="editTenantDbUser">
            <select name="status" id="editTenantStatus">
                <option value="provisioning">provisioning</option>
                <option value="active">active</option>
                <option value="suspended">suspended</option>
            </select>
            <div class="modal-actions">
                <button type="submit">Save</button>
                <button type="button" id="closeEditModal">Cancel</button>
            </div>
            </fieldset>
        </form>
    </div>
</div>

<div id="configDbModal" class="modal hidden">
    <div class="modal-content">
        <h3>Configure Tenant DB</h3>
        <form id="configDbForm" class="cc-form-grid">
            <input type="hidden" id="cfgTenantId">
            <fieldset class="cc-fieldset-plain" <?php echo $isAdminOrAboveCc ? '' : 'disabled'; ?>>
            <input type="text" id="cfgDbName" placeholder="Database Name" required>
            <input type="text" id="cfgDbHost" placeholder="DB Host (optional)">
            <input type="text" id="cfgDbUser" placeholder="DB User" required>
            <input type="password" id="cfgDbPassword" placeholder="DB Password">
            <div class="modal-actions">
                <button type="submit">Save DB Config</button>
                <button type="button" id="closeConfigDbModal">Cancel</button>
            </div>
            </fieldset>
        </form>
    </div>
</div>

<div id="ccToastHost" class="cc-toast-host" aria-live="polite" aria-atomic="true"></div>

<script src="<?php echo htmlspecialchars($assetJsUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>

