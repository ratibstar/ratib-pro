<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/get-control-countries-for-users.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/get-control-countries-for-users.php`.
 */
/**
 * Control Panel API: Get countries from control_countries for Add New Users form.
 * Syncs control_countries to agency's recruitment_countries and returns list with recruitment_countries.id
 * so the users form can save country_id correctly.
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

if (empty($_SESSION['control_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
if (!hasControlPermission(CONTROL_PERM_SYSTEM_SETTINGS) && !hasControlPermission('manage_control_users')) {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$ctrl = $GLOBALS['control_conn'] ?? null;
$conn = $GLOBALS['conn'] ?? null;

if (!$ctrl) {
    echo json_encode(['success' => false, 'message' => 'Control database unavailable']);
    exit;
}

// Get control_countries - scoped by country permissions unless user has full select-country/global access.
$chk = $ctrl->query("SHOW TABLES LIKE 'control_countries'");
if (!$chk || $chk->num_rows === 0) {
    echo json_encode(['success' => true, 'countries' => []]);
    exit;
}

$hasActive = $ctrl->query("SHOW COLUMNS FROM control_countries LIKE 'is_active'");
$countryWhere = ($hasActive && $hasActive->num_rows > 0) ? "is_active = 1" : "1=1";
$hasSort = $ctrl->query("SHOW COLUMNS FROM control_countries LIKE 'sort_order'");
$orderBy = ($hasSort && $hasSort->num_rows > 0) ? "ORDER BY sort_order ASC, name ASC" : "ORDER BY name ASC";

$allowedCountryIds = getAllowedCountryIds($ctrl);
$countryScopeSql = '';
if (is_array($allowedCountryIds)) {
    if (count($allowedCountryIds) === 0) {
        echo json_encode(['success' => true, 'countries' => []]);
        exit;
    }
    $countryScopeSql = ' AND id IN (' . implode(',', array_map('intval', $allowedCountryIds)) . ')';
}
$stmt = $ctrl->query("SELECT id, name FROM control_countries WHERE $countryWhere $countryScopeSql $orderBy");
$controlCountries = [];
if ($stmt) {
    while ($row = $stmt->fetch_assoc()) {
        $controlCountries[] = ['id' => (int)$row['id'], 'name' => trim($row['name'])];
    }
    $stmt->close();
}

// If we have agency DB (conn is agency), sync to recruitment_countries and get ids
$result = [];
if ($conn && $conn instanceof mysqli && !empty($controlCountries)) {
    $chkRec = $conn->query("SHOW TABLES LIKE 'recruitment_countries'");
    if ($chkRec && $chkRec->num_rows > 0) {
        foreach ($controlCountries as $cc) {
            $name = $conn->real_escape_string($cc['name']);
            $existing = $conn->query("SELECT id FROM recruitment_countries WHERE TRIM(country_name) = '$name' LIMIT 1");
            if ($existing && $existing->num_rows > 0) {
                $row = $existing->fetch_assoc();
                $result[] = ['id' => (int)$row['id'], 'name' => $cc['name']];
            } else {
                $stmt = $conn->prepare("INSERT INTO recruitment_countries (country_name, status) VALUES (?, 'active')");
                if ($stmt) {
                    $stmt->bind_param('s', $cc['name']);
                    if ($stmt->execute()) {
                        $result[] = ['id' => (int)$conn->insert_id, 'name' => $cc['name']];
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// If no agency DB or sync failed, return control countries with control id (fallback - may not save correctly)
if (empty($result) && !empty($controlCountries)) {
    foreach ($controlCountries as $cc) {
        $result[] = ['id' => $cc['id'], 'name' => $cc['name']];
    }
}

echo json_encode(['success' => true, 'countries' => $result]);
