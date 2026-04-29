<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

function cp_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cp_allowed_labels_keys(): array
{
    return ['government', 'workPermit', 'contract', 'travel'];
}

function cp_allowed_requirement_fields(): array
{
    return [
        'full_name', 'gender', 'agent_id',
        'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number',
        'training_certificate_number', 'contract_signed_number', 'insurance_number', 'exit_permit_number', 'approval_reference_id',
        'government_registration_number', 'work_permit_number', 'insurance_policy_number',
        'salary', 'contract_duration', 'flight_ticket_number', 'predeparture_training_completed', 'contract_verified'
    ];
}

function cp_default_profiles(): array
{
    return [
        'indonesia' => [
            'labels' => ['government' => 'Government Approval', 'workPermit' => 'Exit Permit', 'contract' => 'Signed Contract', 'travel' => 'Travel Readiness'],
            'requirements' => ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'training_certificate_number', 'contract_signed_number', 'insurance_number', 'exit_permit_number', 'approval_reference_id']
        ],
        'bangladesh' => [
            'labels' => ['government' => 'BMET Registration', 'workPermit' => 'Work Permit', 'contract' => 'Overseas Contract', 'travel' => 'Travel Clearance'],
            'requirements' => ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'government_registration_number', 'work_permit_number', 'insurance_policy_number', 'salary', 'contract_duration', 'flight_ticket_number', 'predeparture_training_completed', 'contract_verified']
        ],
        'sri_lanka' => [
            'labels' => ['government' => 'SLBFE Registration', 'workPermit' => 'Work Permit', 'contract' => 'Employment Contract', 'travel' => 'Departure Clearance'],
            'requirements' => ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'government_registration_number', 'work_permit_number', 'insurance_policy_number', 'salary', 'contract_duration', 'flight_ticket_number', 'predeparture_training_completed', 'contract_verified']
        ],
        'kenya' => [
            'labels' => ['government' => 'NITA Registration', 'workPermit' => 'Work Permit', 'contract' => 'Employment Contract', 'travel' => 'Travel Clearance'],
            'requirements' => ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'government_registration_number', 'work_permit_number', 'insurance_policy_number', 'salary', 'contract_duration', 'flight_ticket_number', 'predeparture_training_completed', 'contract_verified']
        ],
        'default' => [
            'labels' => ['government' => 'Government Registration', 'workPermit' => 'Work Permit', 'contract' => 'Contract', 'travel' => 'Travel & Departure'],
            'requirements' => ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'government_registration_number', 'work_permit_number']
        ],
    ];
}

function cp_allowed_country_slugs(): array
{
    return array_keys(cp_default_profiles());
}

function cp_validate_payload(array $raw): array
{
    $countrySlug = strtolower(trim((string) ($raw['country_slug'] ?? '')));
    $labels = $raw['labels'] ?? [];
    $requirements = $raw['requirements'] ?? [];
    if ($countrySlug === '' || !preg_match('/^[a-z0-9_\\-]+$/', $countrySlug)) {
        throw new RuntimeException('Invalid country_slug');
    }
    if (!in_array($countrySlug, cp_allowed_country_slugs(), true)) {
        throw new RuntimeException('Unsupported country_slug: ' . $countrySlug);
    }
    if (!is_array($labels) || !is_array($requirements)) {
        throw new RuntimeException('labels/requirements must be arrays');
    }
    $allowedLabelKeys = cp_allowed_labels_keys();
    foreach ($labels as $k => $v) {
        if (!in_array((string) $k, $allowedLabelKeys, true)) {
            throw new RuntimeException('Unknown label key: ' . (string) $k);
        }
        $labels[$k] = trim((string) $v);
    }
    $allowedReq = cp_allowed_requirement_fields();
    $cleanReq = [];
    foreach ($requirements as $r) {
        $f = trim((string) $r);
        if ($f === '') continue;
        if (!in_array($f, $allowedReq, true)) {
            throw new RuntimeException('Unknown requirement field: ' . $f);
        }
        $cleanReq[] = $f;
    }
    $cleanReq = array_values(array_unique($cleanReq));
    return [$countrySlug, $labels, $cleanReq];
}

function ensure_country_profiles_table(mysqli $ctrl): void
{
    $ctrl->query(
        "CREATE TABLE IF NOT EXISTS control_country_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            country_slug VARCHAR(100) NOT NULL UNIQUE,
            labels_json LONGTEXT NULL,
            requirements_json LONGTEXT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $ctrl->query(
        "CREATE TABLE IF NOT EXISTS control_country_profiles_audit (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            country_slug VARCHAR(100) NOT NULL,
            changed_by_id INT NULL,
            changed_by_username VARCHAR(191) NULL,
            action_type VARCHAR(40) NOT NULL,
            before_json LONGTEXT NULL,
            after_json LONGTEXT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_country_profiles_audit_slug (country_slug),
            KEY idx_country_profiles_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function cp_fetch_profile(mysqli $ctrl, string $slug): ?array
{
    $st = $ctrl->prepare("SELECT country_slug, labels_json, requirements_json, updated_at FROM control_country_profiles WHERE country_slug = ? LIMIT 1");
    if (!$st) return null;
    $st->bind_param('s', $slug);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) return null;
    return [
        'country_slug' => (string) ($row['country_slug'] ?? ''),
        'labels' => json_decode((string) ($row['labels_json'] ?? '{}'), true) ?: new stdClass(),
        'requirements' => json_decode((string) ($row['requirements_json'] ?? '[]'), true) ?: [],
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function cp_effective_profile(string $slug, ?array $stored): array
{
    $defaults = cp_default_profiles();
    $base = $defaults[$slug] ?? $defaults['default'];
    if ($stored && is_array($stored['labels'] ?? null) && count((array) $stored['labels']) > 0) {
        $base['labels'] = $stored['labels'];
    }
    if ($stored && is_array($stored['requirements'] ?? null) && count((array) $stored['requirements']) > 0) {
        $base['requirements'] = array_values(array_unique(array_map('strval', (array) $stored['requirements'])));
    }
    return $base;
}

function cp_log_audit(mysqli $ctrl, string $slug, string $actionType, mixed $before, mixed $after): void
{
    $userId = isset($_SESSION['control_user_id']) ? (int) $_SESSION['control_user_id'] : null;
    $username = isset($_SESSION['control_username']) ? (string) $_SESSION['control_username'] : null;
    $beforeJson = $before !== null ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $afterJson = $after !== null ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    $st = $ctrl->prepare(
        "INSERT INTO control_country_profiles_audit
         (country_slug, changed_by_id, changed_by_username, action_type, before_json, after_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$st) return;
    $st->bind_param('sissss', $slug, $userId, $username, $actionType, $beforeJson, $afterJson);
    $st->execute();
    $st->close();
}

if (empty($_SESSION['control_logged_in'])) {
    cp_json(['success' => false, 'message' => 'Unauthorized'], 401);
}
requireControlPermission(CONTROL_PERM_SYSTEM_SETTINGS, 'view_control_system_settings', 'edit_control_system_settings', 'manage_control_roles');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!($ctrl instanceof mysqli)) {
    cp_json(['success' => false, 'message' => 'Control DB unavailable'], 500);
}

ensure_country_profiles_table($ctrl);
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = strtolower(trim((string) ($_GET['action'] ?? '')));

if ($method === 'GET') {
    $rows = [];
    $res = $ctrl->query("SELECT country_slug, labels_json, requirements_json, updated_at FROM control_country_profiles ORDER BY country_slug ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = [
                'country_slug' => (string) ($row['country_slug'] ?? ''),
                'labels' => json_decode((string) ($row['labels_json'] ?? '{}'), true) ?: new stdClass(),
                'requirements' => json_decode((string) ($row['requirements_json'] ?? '[]'), true) ?: [],
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }
        $res->close();
    }

    if ($action === 'export') {
        cp_json([
            'success' => true,
            'exported_at' => date('c'),
            'version' => 1,
            'profiles' => $rows,
        ]);
    }

    if ($action === 'preview') {
        $storedMap = [];
        foreach ($rows as $r) $storedMap[$r['country_slug']] = $r;
        $preview = [];
        foreach (cp_default_profiles() as $slug => $cfg) {
            $preview[$slug] = cp_effective_profile($slug, $storedMap[$slug] ?? null);
        }
        cp_json(['success' => true, 'data' => $preview]);
    }

    cp_json(['success' => true, 'data' => $rows]);
}

if ($method === 'POST') {
    $raw = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($raw)) cp_json(['success' => false, 'message' => 'Invalid JSON'], 422);

    $postAction = strtolower(trim((string) ($raw['action'] ?? 'save')));
    if ($postAction === 'import') {
        $profiles = $raw['profiles'] ?? null;
        if (!is_array($profiles)) cp_json(['success' => false, 'message' => 'profiles[] required for import'], 422);
        $ctrl->begin_transaction();
        try {
            foreach ($profiles as $p) {
                if (!is_array($p)) continue;
                [$countrySlug, $labels, $requirements] = cp_validate_payload($p);
                $before = cp_fetch_profile($ctrl, $countrySlug);
                $labelsJson = json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $reqJson = json_encode($requirements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $st = $ctrl->prepare(
                    "INSERT INTO control_country_profiles (country_slug, labels_json, requirements_json, updated_at)
                     VALUES (?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE
                        labels_json = VALUES(labels_json),
                        requirements_json = VALUES(requirements_json),
                        updated_at = NOW()"
                );
                if (!$st) throw new RuntimeException('Prepare failed');
                $st->bind_param('sss', $countrySlug, $labelsJson, $reqJson);
                $ok = $st->execute();
                $st->close();
                if (!$ok) throw new RuntimeException('Import save failed for ' . $countrySlug);
                $after = cp_fetch_profile($ctrl, $countrySlug);
                cp_log_audit($ctrl, $countrySlug, 'import', $before, $after);
            }
            $ctrl->commit();
        } catch (Throwable $e) {
            $ctrl->rollback();
            cp_json(['success' => false, 'message' => $e->getMessage()], 500);
        }
        cp_json(['success' => true, 'message' => 'Import completed']);
    }

    try {
        [$countrySlug, $labels, $requirements] = cp_validate_payload($raw);
    } catch (Throwable $e) {
        cp_json(['success' => false, 'message' => $e->getMessage()], 422);
    }
    $before = cp_fetch_profile($ctrl, $countrySlug);
    $labelsJson = json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $reqJson = json_encode($requirements, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $st = $ctrl->prepare(
        "INSERT INTO control_country_profiles (country_slug, labels_json, requirements_json, updated_at)
         VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            labels_json = VALUES(labels_json),
            requirements_json = VALUES(requirements_json),
            updated_at = NOW()"
    );
    if (!$st) cp_json(['success' => false, 'message' => 'Prepare failed'], 500);
    $st->bind_param('sss', $countrySlug, $labelsJson, $reqJson);
    $ok = $st->execute();
    $st->close();
    if (!$ok) cp_json(['success' => false, 'message' => 'Save failed'], 500);
    $after = cp_fetch_profile($ctrl, $countrySlug);
    cp_log_audit($ctrl, $countrySlug, 'save', $before, $after);
    cp_json(['success' => true, 'message' => 'Profile saved']);
}

cp_json(['success' => false, 'message' => 'Method not allowed'], 405);

