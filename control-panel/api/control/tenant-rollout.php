<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}
ob_start();

set_exception_handler(static function (Throwable $e): void {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Unhandled exception: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!$err) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR];
    if (!in_array((int) $err['type'], $fatalTypes, true)) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Fatal error: ' . (string) ($err['message'] ?? 'unknown'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

function tr_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tr_stmt_error(mysqli_stmt $st): string
{
    $err = trim((string) $st->error);
    return $err !== '' ? $err : 'Database operation failed';
}

function tr_user_id(): ?int
{
    if (!isset($_SESSION['control_user_id'])) {
        return null;
    }
    return (int) $_SESSION['control_user_id'];
}

function tr_username(): string
{
    return trim((string) ($_SESSION['control_username'] ?? ''));
}

function tr_ensure_tables(mysqli $ctrl): void
{
    $ctrl->query(
        "CREATE TABLE IF NOT EXISTS control_rollout_tenants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_code VARCHAR(64) NOT NULL,
            tenant_name VARCHAR(191) NOT NULL,
            primary_domain VARCHAR(191) NOT NULL,
            country_id INT NULL,
            db_key_ref VARCHAR(191) NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'active',
            release_channel VARCHAR(32) NOT NULL DEFAULT 'stable',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_rollout_tenant_code (tenant_code),
            UNIQUE KEY uq_rollout_tenant_domain (primary_domain),
            KEY idx_rollout_tenant_country (country_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ctrl->query(
        "CREATE TABLE IF NOT EXISTS control_rollout_feature_flags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            flag_key VARCHAR(120) NOT NULL,
            flag_description VARCHAR(255) NULL,
            default_value TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_rollout_flag_key (flag_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ctrl->query(
        "CREATE TABLE IF NOT EXISTS control_rollout_flag_overrides (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            flag_id INT NOT NULL,
            scope_type VARCHAR(16) NOT NULL,
            country_id INT NULL,
            tenant_id INT NULL,
            override_value TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            changed_by_id INT NULL,
            changed_by_username VARCHAR(191) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_rollout_override_flag (flag_id),
            KEY idx_rollout_override_country (country_id),
            KEY idx_rollout_override_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ctrl->query(
        "CREATE TABLE IF NOT EXISTS control_rollout_audit (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(40) NOT NULL,
            entity_id BIGINT NULL,
            action_type VARCHAR(40) NOT NULL,
            before_json LONGTEXT NULL,
            after_json LONGTEXT NULL,
            changed_by_id INT NULL,
            changed_by_username VARCHAR(191) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_rollout_audit_entity (entity_type, entity_id),
            KEY idx_rollout_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Lightweight schema healing in case table existed from earlier partial versions.
    $hasTenantRelease = @$ctrl->query("SHOW COLUMNS FROM control_rollout_tenants LIKE 'release_channel'");
    if (!$hasTenantRelease || $hasTenantRelease->num_rows === 0) {
        @$ctrl->query("ALTER TABLE control_rollout_tenants ADD COLUMN release_channel VARCHAR(32) NOT NULL DEFAULT 'stable' AFTER status");
    }
    $hasTenantStatus = @$ctrl->query("SHOW COLUMNS FROM control_rollout_tenants LIKE 'status'");
    if (!$hasTenantStatus || $hasTenantStatus->num_rows === 0) {
        @$ctrl->query("ALTER TABLE control_rollout_tenants ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'active'");
    }
}

function tr_audit(mysqli $ctrl, string $entityType, ?int $entityId, string $actionType, mixed $before, mixed $after): void
{
    $beforeJson = $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $afterJson = $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $uid = tr_user_id();
    $uname = tr_username();
    $st = $ctrl->prepare(
        "INSERT INTO control_rollout_audit
         (entity_type, entity_id, action_type, before_json, after_json, changed_by_id, changed_by_username, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$st) {
        return;
    }
    $st->bind_param('sisssis', $entityType, $entityId, $actionType, $beforeJson, $afterJson, $uid, $uname);
    $st->execute();
    $st->close();
}

function tr_countries(mysqli $ctrl): array
{
    $rows = [];
    $hasIsActive = @$ctrl->query("SHOW COLUMNS FROM control_countries LIKE 'is_active'");
    $where = ($hasIsActive && $hasIsActive->num_rows > 0) ? " WHERE is_active = 1" : "";
    $res = @$ctrl->query("SELECT id, name, slug FROM control_countries" . $where . " ORDER BY name ASC");
    if (!$res) {
        return $rows;
    }
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) ($r['id'] ?? 0),
            'name' => (string) ($r['name'] ?? ''),
            'slug' => (string) ($r['slug'] ?? ''),
        ];
    }
    $res->close();
    return $rows;
}

function tr_tenants(mysqli $ctrl): array
{
    $rows = [];
    $sql = "SELECT t.id, t.tenant_code, t.tenant_name, t.primary_domain, t.country_id, t.db_key_ref, t.status, t.release_channel,
                   t.created_at, t.updated_at, COALESCE(c.name, '') AS country_name
            FROM control_rollout_tenants t
            LEFT JOIN control_countries c ON c.id = t.country_id
            ORDER BY t.tenant_name ASC";
    $res = $ctrl->query($sql);
    if (!$res) {
        return $rows;
    }
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) ($r['id'] ?? 0),
            'tenant_code' => (string) ($r['tenant_code'] ?? ''),
            'tenant_name' => (string) ($r['tenant_name'] ?? ''),
            'primary_domain' => (string) ($r['primary_domain'] ?? ''),
            'country_id' => (int) ($r['country_id'] ?? 0),
            'country_name' => (string) ($r['country_name'] ?? ''),
            'db_key_ref' => (string) ($r['db_key_ref'] ?? ''),
            'status' => (string) ($r['status'] ?? 'active'),
            'release_channel' => (string) ($r['release_channel'] ?? 'stable'),
            'created_at' => (string) ($r['created_at'] ?? ''),
            'updated_at' => (string) ($r['updated_at'] ?? ''),
        ];
    }
    $res->close();
    return $rows;
}

function tr_flags(mysqli $ctrl): array
{
    $rows = [];
    $res = $ctrl->query("SELECT id, flag_key, flag_description, default_value, is_active, created_at, updated_at FROM control_rollout_feature_flags ORDER BY flag_key ASC");
    if (!$res) {
        return $rows;
    }
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) ($r['id'] ?? 0),
            'flag_key' => (string) ($r['flag_key'] ?? ''),
            'flag_description' => (string) ($r['flag_description'] ?? ''),
            'default_value' => (int) ($r['default_value'] ?? 0),
            'is_active' => (int) ($r['is_active'] ?? 1),
            'created_at' => (string) ($r['created_at'] ?? ''),
            'updated_at' => (string) ($r['updated_at'] ?? ''),
        ];
    }
    $res->close();
    return $rows;
}

function tr_overrides(mysqli $ctrl): array
{
    $rows = [];
    $sql = "SELECT o.id, o.flag_id, o.scope_type, o.country_id, o.tenant_id, o.override_value, o.is_active, o.updated_at,
                   f.flag_key, COALESCE(c.name, '') AS country_name, COALESCE(t.tenant_name, '') AS tenant_name
            FROM control_rollout_flag_overrides o
            LEFT JOIN control_rollout_feature_flags f ON f.id = o.flag_id
            LEFT JOIN control_countries c ON c.id = o.country_id
            LEFT JOIN control_rollout_tenants t ON t.id = o.tenant_id
            ORDER BY o.updated_at DESC, o.id DESC";
    $res = $ctrl->query($sql);
    if (!$res) {
        return $rows;
    }
    while ($r = $res->fetch_assoc()) {
        $rows[] = [
            'id' => (int) ($r['id'] ?? 0),
            'flag_id' => (int) ($r['flag_id'] ?? 0),
            'flag_key' => (string) ($r['flag_key'] ?? ''),
            'scope_type' => (string) ($r['scope_type'] ?? ''),
            'country_id' => (int) ($r['country_id'] ?? 0),
            'country_name' => (string) ($r['country_name'] ?? ''),
            'tenant_id' => (int) ($r['tenant_id'] ?? 0),
            'tenant_name' => (string) ($r['tenant_name'] ?? ''),
            'override_value' => (int) ($r['override_value'] ?? 0),
            'is_active' => (int) ($r['is_active'] ?? 1),
            'updated_at' => (string) ($r['updated_at'] ?? ''),
        ];
    }
    $res->close();
    return $rows;
}

function tr_require_permissions(): void
{
    if (empty($_SESSION['control_logged_in'])) {
        tr_json(['success' => false, 'message' => 'Unauthorized'], 401);
    }
    if (
        !hasControlPermission(CONTROL_PERM_SYSTEM_SETTINGS)
        && !hasControlPermission('view_control_system_settings')
        && !hasControlPermission(CONTROL_PERM_DASHBOARD)
    ) {
        tr_json(['success' => false, 'message' => 'Access denied'], 403);
    }
}

function tr_read_json_body(): array
{
    $raw = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($raw)) {
        tr_json(['success' => false, 'message' => 'Invalid JSON payload'], 422);
    }
    return $raw;
}

tr_require_permissions();
$ctrl = $GLOBALS['control_conn'] ?? null;
if (!($ctrl instanceof mysqli)) {
    tr_json(['success' => false, 'message' => 'Control DB unavailable'], 500);
}

tr_ensure_tables($ctrl);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = strtolower(trim((string) ($_GET['action'] ?? 'bootstrap')));

if ($method === 'GET') {
    tr_json([
        'success' => true,
        'action' => $action,
        'countries' => tr_countries($ctrl),
        'tenants' => tr_tenants($ctrl),
        'flags' => tr_flags($ctrl),
        'overrides' => tr_overrides($ctrl),
    ]);
}

if ($method === 'POST') {
    $body = tr_read_json_body();
    $postAction = strtolower(trim((string) ($body['action'] ?? '')));

    if ($postAction === 'save_tenant') {
        $id = (int) ($body['id'] ?? 0);
        $tenantCode = strtolower(trim((string) ($body['tenant_code'] ?? '')));
        $tenantName = trim((string) ($body['tenant_name'] ?? ''));
        $primaryDomain = strtolower(trim((string) ($body['primary_domain'] ?? '')));
        $countryId = (int) ($body['country_id'] ?? 0);
        $dbKeyRef = trim((string) ($body['db_key_ref'] ?? ''));
        $status = strtolower(trim((string) ($body['status'] ?? 'active')));
        $releaseChannel = strtolower(trim((string) ($body['release_channel'] ?? 'stable')));
        if ($tenantCode === '' || !preg_match('/^[a-z0-9_\\-]+$/', $tenantCode)) {
            tr_json(['success' => false, 'message' => 'Invalid tenant_code'], 422);
        }
        if ($tenantName === '' || $primaryDomain === '' || $dbKeyRef === '') {
            tr_json(['success' => false, 'message' => 'tenant_name, primary_domain and db_key_ref are required'], 422);
        }
        if (!in_array($status, ['active', 'maintenance', 'suspended'], true)) {
            tr_json(['success' => false, 'message' => 'Invalid status'], 422);
        }
        if (!in_array($releaseChannel, ['stable', 'canary', 'beta'], true)) {
            $releaseChannel = 'stable';
        }

        $before = null;
        if ($id > 0) {
            $stBefore = $ctrl->prepare("SELECT * FROM control_rollout_tenants WHERE id = ? LIMIT 1");
            if ($stBefore) {
                $stBefore->bind_param('i', $id);
                $stBefore->execute();
                $before = $stBefore->get_result()->fetch_assoc() ?: null;
                $stBefore->close();
            }
        }

        if ($id > 0) {
            $st = $ctrl->prepare(
                "UPDATE control_rollout_tenants
                 SET tenant_code = ?, tenant_name = ?, primary_domain = ?, country_id = ?, db_key_ref = ?, status = ?, release_channel = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            if (!$st) {
                tr_json(['success' => false, 'message' => 'Failed to prepare tenant update'], 500);
            }
            $countryNullable = $countryId > 0 ? $countryId : null;
            $st->bind_param('sssisssi', $tenantCode, $tenantName, $primaryDomain, $countryNullable, $dbKeyRef, $status, $releaseChannel, $id);
            $ok = $st->execute();
            $execErr = tr_stmt_error($st);
            $st->close();
            if (!$ok) {
                tr_json(['success' => false, 'message' => 'Tenant update failed: ' . $execErr], 500);
            }
            tr_audit($ctrl, 'tenant', $id, 'update', $before, ['tenant_code' => $tenantCode, 'tenant_name' => $tenantName, 'primary_domain' => $primaryDomain, 'country_id' => $countryId, 'status' => $status]);
            tr_json(['success' => true, 'message' => 'Tenant updated']);
        }

        $st = $ctrl->prepare(
            "INSERT INTO control_rollout_tenants
            (tenant_code, tenant_name, primary_domain, country_id, db_key_ref, status, release_channel, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        if (!$st) {
            tr_json(['success' => false, 'message' => 'Failed to prepare tenant insert'], 500);
        }
        $countryNullable = $countryId > 0 ? $countryId : null;
        $st->bind_param('sssisss', $tenantCode, $tenantName, $primaryDomain, $countryNullable, $dbKeyRef, $status, $releaseChannel);
        $ok = $st->execute();
        $execErr = tr_stmt_error($st);
        $newId = (int) $st->insert_id;
        $st->close();
        if (!$ok) {
            tr_json(['success' => false, 'message' => 'Tenant insert failed: ' . $execErr], 500);
        }
        tr_audit($ctrl, 'tenant', $newId, 'create', null, ['tenant_code' => $tenantCode, 'tenant_name' => $tenantName, 'primary_domain' => $primaryDomain, 'country_id' => $countryId, 'status' => $status]);
        tr_json(['success' => true, 'message' => 'Tenant created']);
    }

    if ($postAction === 'save_flag') {
        $id = (int) ($body['id'] ?? 0);
        $flagKey = strtolower(trim((string) ($body['flag_key'] ?? '')));
        $flagDescription = trim((string) ($body['flag_description'] ?? ''));
        $defaultValue = (int) ($body['default_value'] ?? 0) > 0 ? 1 : 0;
        if ($flagKey === '' || !preg_match('/^[a-z0-9_.\\-]+$/', $flagKey)) {
            tr_json(['success' => false, 'message' => 'Invalid flag_key'], 422);
        }

        if ($id > 0) {
            $st = $ctrl->prepare(
                "UPDATE control_rollout_feature_flags
                 SET flag_key = ?, flag_description = ?, default_value = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            if (!$st) {
                tr_json(['success' => false, 'message' => 'Failed to prepare flag update'], 500);
            }
            $st->bind_param('ssii', $flagKey, $flagDescription, $defaultValue, $id);
            $ok = $st->execute();
            $execErr = tr_stmt_error($st);
            $st->close();
            if (!$ok) {
                tr_json(['success' => false, 'message' => 'Flag update failed: ' . $execErr], 500);
            }
            tr_audit($ctrl, 'flag', $id, 'update', null, ['flag_key' => $flagKey, 'default_value' => $defaultValue]);
            tr_json(['success' => true, 'message' => 'Flag updated']);
        }

        $st = $ctrl->prepare(
            "INSERT INTO control_rollout_feature_flags (flag_key, flag_description, default_value, is_active, created_at, updated_at)
             VALUES (?, ?, ?, 1, NOW(), NOW())"
        );
        if (!$st) {
            tr_json(['success' => false, 'message' => 'Failed to prepare flag insert'], 500);
        }
        $st->bind_param('ssi', $flagKey, $flagDescription, $defaultValue);
        $ok = $st->execute();
        $execErr = tr_stmt_error($st);
        $newId = (int) $st->insert_id;
        $st->close();
        if (!$ok) {
            tr_json(['success' => false, 'message' => 'Flag insert failed: ' . $execErr], 500);
        }
        tr_audit($ctrl, 'flag', $newId, 'create', null, ['flag_key' => $flagKey, 'default_value' => $defaultValue]);
        tr_json(['success' => true, 'message' => 'Flag created']);
    }

    if ($postAction === 'save_override') {
        $flagId = (int) ($body['flag_id'] ?? 0);
        $scopeType = strtolower(trim((string) ($body['scope_type'] ?? '')));
        $countryId = (int) ($body['country_id'] ?? 0);
        $tenantId = (int) ($body['tenant_id'] ?? 0);
        $overrideValue = (int) ($body['override_value'] ?? 0) > 0 ? 1 : 0;
        if ($flagId <= 0 || !in_array($scopeType, ['country', 'tenant'], true)) {
            tr_json(['success' => false, 'message' => 'Invalid override payload'], 422);
        }
        if ($scopeType === 'country' && $countryId <= 0) {
            tr_json(['success' => false, 'message' => 'country_id required for country override'], 422);
        }
        if ($scopeType === 'tenant' && $tenantId <= 0) {
            tr_json(['success' => false, 'message' => 'tenant_id required for tenant override'], 422);
        }

        $existingId = 0;
        if ($scopeType === 'country') {
            $stFind = $ctrl->prepare("SELECT id FROM control_rollout_flag_overrides WHERE flag_id = ? AND scope_type = 'country' AND country_id = ? LIMIT 1");
            if ($stFind) {
                $stFind->bind_param('ii', $flagId, $countryId);
                $stFind->execute();
                $existingId = (int) (($stFind->get_result()->fetch_assoc()['id'] ?? 0));
                $stFind->close();
            }
        } else {
            $stFind = $ctrl->prepare("SELECT id FROM control_rollout_flag_overrides WHERE flag_id = ? AND scope_type = 'tenant' AND tenant_id = ? LIMIT 1");
            if ($stFind) {
                $stFind->bind_param('ii', $flagId, $tenantId);
                $stFind->execute();
                $existingId = (int) (($stFind->get_result()->fetch_assoc()['id'] ?? 0));
                $stFind->close();
            }
        }

        $uid = tr_user_id();
        $uname = tr_username();
        if ($existingId > 0) {
            $st = $ctrl->prepare(
                "UPDATE control_rollout_flag_overrides
                 SET override_value = ?, is_active = 1, changed_by_id = ?, changed_by_username = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            if (!$st) {
                tr_json(['success' => false, 'message' => 'Failed to prepare override update'], 500);
            }
            $st->bind_param('iisi', $overrideValue, $uid, $uname, $existingId);
            $ok = $st->execute();
            $execErr = tr_stmt_error($st);
            $st->close();
            if (!$ok) {
                tr_json(['success' => false, 'message' => 'Override update failed: ' . $execErr], 500);
            }
            tr_audit($ctrl, 'override', $existingId, 'update', null, ['flag_id' => $flagId, 'scope_type' => $scopeType, 'override_value' => $overrideValue]);
            tr_json(['success' => true, 'message' => 'Override updated']);
        }

        $countryNullable = $scopeType === 'country' ? $countryId : null;
        $tenantNullable = $scopeType === 'tenant' ? $tenantId : null;
        $st = $ctrl->prepare(
            "INSERT INTO control_rollout_flag_overrides
            (flag_id, scope_type, country_id, tenant_id, override_value, is_active, changed_by_id, changed_by_username, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?, NOW(), NOW())"
        );
        if (!$st) {
            tr_json(['success' => false, 'message' => 'Failed to prepare override insert'], 500);
        }
        $st->bind_param('isiiiis', $flagId, $scopeType, $countryNullable, $tenantNullable, $overrideValue, $uid, $uname);
        $ok = $st->execute();
        $execErr = tr_stmt_error($st);
        $newId = (int) $st->insert_id;
        $st->close();
        if (!$ok) {
            tr_json(['success' => false, 'message' => 'Override insert failed: ' . $execErr], 500);
        }
        tr_audit($ctrl, 'override', $newId, 'create', null, ['flag_id' => $flagId, 'scope_type' => $scopeType, 'override_value' => $overrideValue]);
        tr_json(['success' => true, 'message' => 'Override created']);
    }

    if ($postAction === 'delete_override') {
        $id = (int) ($body['id'] ?? 0);
        if ($id <= 0) {
            tr_json(['success' => false, 'message' => 'Override id is required'], 422);
        }
        $st = $ctrl->prepare("DELETE FROM control_rollout_flag_overrides WHERE id = ? LIMIT 1");
        if (!$st) {
            tr_json(['success' => false, 'message' => 'Failed to prepare override delete'], 500);
        }
        $st->bind_param('i', $id);
        $ok = $st->execute();
        $execErr = tr_stmt_error($st);
        $st->close();
        if (!$ok) {
            tr_json(['success' => false, 'message' => 'Override delete failed: ' . $execErr], 500);
        }
        tr_audit($ctrl, 'override', $id, 'delete', null, null);
        tr_json(['success' => true, 'message' => 'Override removed']);
    }

    tr_json(['success' => false, 'message' => 'Unknown action'], 422);
}

tr_json(['success' => false, 'message' => 'Method not allowed'], 405);
