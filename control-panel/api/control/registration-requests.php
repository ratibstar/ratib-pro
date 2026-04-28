<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/registration-requests.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/registration-requests.php`.
 */
/**
 * Control Panel API: Registration requests (control_registration_requests)
 * Requires control panel session.
 */
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../includes/control-api-same-origin-cors.php';
applyControlApiSameOriginCors();
header('Access-Control-Allow-Methods: GET, PATCH, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once __DIR__ . '/../../includes/config.php';

function jsonOut($data) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

$isControl = defined('IS_CONTROL_PANEL') && IS_CONTROL_PANEL;
if (!$isControl || empty($_SESSION['control_logged_in'])) {
    jsonOut(['success' => false, 'message' => 'Unauthorized']);
}
require_once __DIR__ . '/../../includes/control-permissions.php';
if (!hasControlPermission(CONTROL_PERM_REGISTRATION) && !hasControlPermission('view_control_registration') && !hasControlPermission('view_all_control_registration')) {
    jsonOut(['success' => false, 'message' => 'Access denied']);
}

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl) jsonOut(['success' => false, 'message' => 'Database unavailable']);

$ngMergePath = __DIR__ . '/../../includes/registration_requests_ngenius_display_merge.php';
$ngMergeLoaded = is_readable($ngMergePath);
if ($ngMergeLoaded) {
    require_once $ngMergePath;
}

$chk = $ctrl->query("SHOW TABLES LIKE 'control_registration_requests'");
if (!$chk || $chk->num_rows === 0) jsonOut(['success' => false, 'message' => 'Table not found']);

$scopeCountryIds = getRegistrationRequestScopeCountryIds($ctrl);
$canViewAllRegistration = ($scopeCountryIds === null);
$hasCountryId = false;
$colChk = $ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'country_id'");
if ($colChk && $colChk->num_rows > 0) {
    $hasCountryId = true;
}

$method = $_SERVER['REQUEST_METHOD'];

// GET - list
if ($method === 'GET') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
    $status = trim($_GET['status'] ?? '');
    $paymentStatusFilter = strtolower(trim((string)($_GET['payment_status'] ?? '')));
    if (!in_array($paymentStatusFilter, ['paid', 'unpaid', 'pending', 'failed'], true)) {
        $paymentStatusFilter = '';
    }

    $where = [];
    if ($scopeCountryIds === []) {
        if (!$canViewAllRegistration) {
            $where[] = '1=0';
        }
    } elseif (!$canViewAllRegistration && $scopeCountryIds !== null && !empty($scopeCountryIds) && $hasCountryId) {
        $idsStr = implode(',', array_map('intval', $scopeCountryIds));
        // Match by country_id OR legacy rows with no country_id but correct country_name (scoped only — no broad N-Genius ORs).
        $namesRes = $ctrl->query("SELECT name FROM control_countries WHERE id IN ($idsStr) AND is_active = 1");
        $countryNames = [];
        if ($namesRes) {
            while ($r = $namesRes->fetch_assoc()) {
                $countryNames[] = "'" . $ctrl->real_escape_string($r['name']) . "'";
            }
        }
        $nameMatch = !empty($countryNames) ? " OR (COALESCE(country_id, 0) = 0 AND country_name IN (" . implode(',', $countryNames) . "))" : '';
        $where[] = "(country_id IN ($idsStr) $nameMatch)";
    }
    if ($status !== '' && in_array($status, ['pending','approved','rejected'])) {
        $esc = $ctrl->real_escape_string($status);
        $where[] = "status = '{$esc}'";
    }
    $colPayStatus = $ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'");
    $hasPaymentStatus = ($colPayStatus && $colPayStatus->num_rows > 0);
    if ($hasPaymentStatus && $paymentStatusFilter !== '') {
        if ($paymentStatusFilter === 'unpaid') {
            $where[] = "(LOWER(TRIM(COALESCE(payment_status,''))) <> 'paid')";
        } else {
            $escPay = $ctrl->real_escape_string($paymentStatusFilter);
            $where[] = "LOWER(TRIM(COALESCE(payment_status,''))) = '{$escPay}'";
        }
    }
    $whereForApi = $where;
    if ($status === '') {
        $whereForApi[] = "LOWER(TRIM(COALESCE(status,''))) <> 'approved'";
    }
    // Default safety: when no explicit payment filter is set, list only paid registrations
    // (plus optional non-paid Pro inquiries) to avoid treating unpaid paid-plans as valid.
    if ($hasPaymentStatus && $paymentStatusFilter === '') {
        $whereForApi[] = "(LOWER(TRIM(COALESCE(payment_status,''))) = 'paid' OR LOWER(TRIM(COALESCE(plan,''))) = 'pro')";
    }
    $whereClause = $whereForApi ? ' WHERE ' . implode(' AND ', $whereForApi) : '';

    $total = (int)($ctrl->query("SELECT COUNT(*) as c FROM control_registration_requests" . $whereClause)->fetch_assoc()['c'] ?? 0);
    $totalPages = max(1, (int)ceil($total / max(1, $limit)));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $limit;
    $res = $ctrl->query("SELECT * FROM control_registration_requests" . $whereClause . " ORDER BY created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset);
    $list = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    if ($ngMergeLoaded && function_exists('registration_requests_merge_ngenius_orders_for_display')) {
        registration_requests_merge_ngenius_orders_for_display($ctrl, $list);
    }

    jsonOut([
        'success' => true,
        'list' => $list,
        'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => $totalPages]
    ]);
}

// PATCH - approve (with agency_id) or reject
if ($method === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = (int)($input['id'] ?? 0);
    $action = trim((string)($input['action'] ?? ''));

    if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid ID']);
    if (!in_array($action, ['approve','reject'])) jsonOut(['success' => false, 'message' => 'Invalid action']);
    if ($action === 'approve' && !hasControlPermission(CONTROL_PERM_REGISTRATION) && !hasControlPermission('approve_control_registration')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    if ($action === 'reject' && !hasControlPermission(CONTROL_PERM_REGISTRATION) && !hasControlPermission('reject_control_registration')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }

    $row = $ctrl->query("SELECT * FROM control_registration_requests WHERE id = " . (int)$id . " LIMIT 1")->fetch_assoc();
    if (!$row) jsonOut(['success' => false, 'message' => 'Request not found']);
    if ($ngMergeLoaded && function_exists('registration_requests_merge_ngenius_orders_for_display')) {
        $rowWrap = [$row];
        registration_requests_merge_ngenius_orders_for_display($ctrl, $rowWrap);
        $row = $rowWrap[0];
    }
    if ($row['status'] !== 'pending') jsonOut(['success' => false, 'message' => 'Request already processed']);
    if ($scopeCountryIds !== null && $hasCountryId && isset($row['country_id']) && $row['country_id'] !== null) {
        if (!in_array((int) $row['country_id'], $scopeCountryIds, true)) {
            jsonOut(['success' => false, 'message' => 'You do not have permission to process this request']);
        }
    }

    $adminId = (int)($_SESSION['control_user_id'] ?? $_SESSION['control_admin_id'] ?? 0);
    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $agencyId = $action === 'approve' ? (int)($input['agency_id'] ?? 0) : null;

    if ($action === 'approve' && $agencyId <= 0) jsonOut(['success' => false, 'message' => 'agency_id required for approve']);

    $agencyIdSql = $agencyId > 0 ? ", created_agency_id = " . (int)$agencyId : "";
    $q = "UPDATE control_registration_requests SET status = '" . $ctrl->real_escape_string($newStatus) . "', reviewed_at = NOW(), reviewed_by = " . (int)$adminId . $agencyIdSql . ", updated_at = NOW() WHERE id = " . (int)$id;
    if ($ctrl->query($q)) {
        jsonOut(['success' => true, 'message' => $action === 'approve' ? 'Approved' : 'Rejected']);
    }
    jsonOut(['success' => false, 'message' => 'Update failed']);
}

// PUT - update request (requires edit)
if ($method === 'PUT') {
    if (!hasControlPermission(CONTROL_PERM_REGISTRATION) && !hasControlPermission('edit_control_registration') && !hasControlPermission('approve_control_registration')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) jsonOut(['success' => false, 'message' => 'Invalid ID']);
    $existingRow = $ctrl->query("SELECT * FROM control_registration_requests WHERE id = $id LIMIT 1")->fetch_assoc();
    if (!$existingRow) {
        jsonOut(['success' => false, 'message' => 'Request not found']);
    }
    if ($ngMergeLoaded && function_exists('registration_requests_merge_ngenius_orders_for_display')) {
        $existingWrap = [$existingRow];
        registration_requests_merge_ngenius_orders_for_display($ctrl, $existingWrap);
        $existingRow = $existingWrap[0];
    }
    if ($scopeCountryIds !== null && $hasCountryId) {
        if ($existingRow['country_id'] !== null && !in_array((int) $existingRow['country_id'], $scopeCountryIds, true)) {
            jsonOut(['success' => false, 'message' => 'You do not have permission to edit this request']);
        }
    }

    // Allow partial updates (e.g. Mark Paid) by falling back to current DB values.
    $agencyName = trim((string)($input['agency_name'] ?? ($existingRow['agency_name'] ?? '')));
    $agencyIdUser = trim((string)($input['agency_id'] ?? ($existingRow['agency_id'] ?? '')));
    $countryName = trim((string)($input['country_name'] ?? ($existingRow['country_name'] ?? '')));
    $contactEmail = trim((string)($input['contact_email'] ?? ($existingRow['contact_email'] ?? '')));
    $contactPhone = trim((string)($input['contact_phone'] ?? ($existingRow['contact_phone'] ?? '')));
    $desiredSiteUrl = trim((string)($input['desired_site_url'] ?? ($existingRow['desired_site_url'] ?? '')));
    $notes = trim((string)($input['notes'] ?? ($existingRow['notes'] ?? '')));
    if ($agencyName === '') jsonOut(['success' => false, 'message' => 'Agency name is required']);
    if ($contactEmail === '') jsonOut(['success' => false, 'message' => 'Contact email is required']);
    if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) jsonOut(['success' => false, 'message' => 'Invalid email']);
    if ($desiredSiteUrl !== '' && !preg_match('/^https?:\/\/.+/', $desiredSiteUrl)) jsonOut(['success' => false, 'message' => 'Site URL must start with http:// or https://']);

    $plan = trim((string)($input['plan'] ?? ($existingRow['plan'] ?? '')));
    $planAmount = array_key_exists('plan_amount', $input) ? (float)$input['plan_amount'] : null;
    $years = array_key_exists('years', $input) ? (int)$input['years'] : null;
    $paymentStatus = isset($input['payment_status']) ? trim((string)$input['payment_status']) : null;
    $paymentMethod = isset($input['payment_method']) ? trim((string)$input['payment_method']) : null;

    if ($paymentStatus !== null && $paymentStatus !== '' && !in_array($paymentStatus, ['unpaid','paid','pending','failed'], true)) {
        jsonOut(['success' => false, 'message' => 'Invalid payment status']);
    }
    if ($paymentMethod !== null && $paymentMethod !== '' && !in_array($paymentMethod, ['paypal','tap','register'], true)) {
        jsonOut(['success' => false, 'message' => 'Invalid payment method']);
    }

    $e = function($v) use ($ctrl) { return "'" . $ctrl->real_escape_string($v) . "'"; };
    $cols = $ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'agency_id'");
    $hasAgencyId = ($cols && $cols->num_rows > 0);

    $set = [];
    $set[] = "agency_name = " . $e($agencyName);
    if ($hasAgencyId) {
        $set[] = "agency_id = " . $e($agencyIdUser);
    }
    $set[] = "country_name = " . $e($countryName);
    $set[] = "contact_email = " . $e($contactEmail);
    $set[] = "contact_phone = " . $e($contactPhone);
    $set[] = "desired_site_url = " . $e($desiredSiteUrl);
    $set[] = "notes = " . $e($notes);

    if ($plan !== '') {
        $set[] = "plan = " . $e($plan);
    }

    $colPlanAmount = $ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'plan_amount'");
    $hasPlanAmount = ($colPlanAmount && $colPlanAmount->num_rows > 0);
    if ($hasPlanAmount && $planAmount !== null) {
        $set[] = "plan_amount = " . ((float)$planAmount);
    }

    $colYears = $ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'years'");
    $hasYearsCol = ($colYears && $colYears->num_rows > 0);
    if ($hasYearsCol && $years !== null) {
        $set[] = "years = " . (int)$years;
    }

    $colPayStatus = $ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'");
    $hasPayStatus = ($colPayStatus && $colPayStatus->num_rows > 0);
    if ($hasPayStatus && $paymentStatus !== null && $paymentStatus !== '') {
        $set[] = "payment_status = " . $e($paymentStatus);
    }

    $colPayMethod = $ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_method'");
    $hasPayMethod = ($colPayMethod && $colPayMethod->num_rows > 0);
    if ($hasPayMethod && $paymentMethod !== null && $paymentMethod !== '') {
        $set[] = "payment_method = " . $e($paymentMethod);
    }

    $set[] = "updated_at = NOW()";

    $oldRow = $ctrl->query("SELECT payment_status, plan_amount FROM control_registration_requests WHERE id = " . (int)$id . " LIMIT 1")->fetch_assoc();
    $q = "UPDATE control_registration_requests SET " . implode(', ', $set) . " WHERE id = " . (int)$id;
    if (!$ctrl->query($q)) jsonOut(['success' => false, 'message' => 'Update failed']);

    $paymentChanged = ($hasPayStatus && $paymentStatus !== null && $paymentStatus !== '') && ($oldRow['payment_status'] ?? '') !== $paymentStatus;
    $amountChanged = $hasPlanAmount && $planAmount !== null && (float)($oldRow['plan_amount'] ?? 0) != (float)$planAmount;
    if (($paymentChanged || $amountChanged)) {
        $chkAudit = @$ctrl->query("SHOW TABLES LIKE 'control_registration_audit'");
        if ($chkAudit && $chkAudit->num_rows > 0) {
            $adminId = (int)($_SESSION['control_user_id'] ?? $_SESSION['control_admin_id'] ?? 0);
            $oldPay = "'" . $ctrl->real_escape_string((string)($oldRow['payment_status'] ?? '')) . "'";
            $newPay = $paymentStatus !== null && $paymentStatus !== '' ? "'" . $ctrl->real_escape_string($paymentStatus) . "'" : 'NULL';
            $oldAmt = ($oldRow['plan_amount'] !== null && $oldRow['plan_amount'] !== '') ? (float)$oldRow['plan_amount'] : 'NULL';
            $newAmt = $planAmount !== null ? (float)$planAmount : 'NULL';
            @$ctrl->query("INSERT INTO control_registration_audit (registration_request_id, admin_id, action, old_payment_status, new_payment_status, old_plan_amount, new_plan_amount) VALUES (" . (int)$id . ", " . $adminId . ", 'update', " . $oldPay . ", " . $newPay . ", " . ($oldAmt === 'NULL' ? 'NULL' : $oldAmt) . ", " . ($newAmt === 'NULL' ? 'NULL' : $newAmt) . ")");
        }
    }
    if ($paymentChanged && strtolower((string) ($paymentStatus ?? '')) === 'paid') {
        $rowAfter = $ctrl->query('SELECT * FROM control_registration_requests WHERE id = ' . (int) $id . ' LIMIT 1')->fetch_assoc();
        if ($rowAfter) {
            require_once __DIR__ . '/../../includes/registration-accounting-sync.php';
            syncPaidRegistrationToAccounting($ctrl, $rowAfter);
        }
    }
    jsonOut(['success' => true, 'message' => 'Updated']);
}

// DELETE - remove request (requires delete)
if ($method === 'DELETE') {
    if (!hasControlPermission(CONTROL_PERM_REGISTRATION) && !hasControlPermission('delete_control_registration')) {
        jsonOut(['success' => false, 'message' => 'Access denied']);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $ids = isset($input['ids']) && is_array($input['ids']) ? array_map('intval', $input['ids']) : [];
    if (empty($ids)) jsonOut(['success' => false, 'message' => 'No IDs provided']);
    $ids = array_filter($ids, function($x) { return $x > 0; });
    if (empty($ids)) jsonOut(['success' => false, 'message' => 'Invalid IDs']);
    if ($scopeCountryIds !== null && $hasCountryId) {
        $idList = implode(',', $ids);
        $res = $ctrl->query("SELECT id, country_id FROM control_registration_requests WHERE id IN ($idList)");
        while ($res && $row = $res->fetch_assoc()) {
            if ($row['country_id'] !== null && !in_array((int) $row['country_id'], $scopeCountryIds, true)) {
                jsonOut(['success' => false, 'message' => 'You do not have permission to delete one or more of these requests']);
            }
        }
        if ($res) {
            $res->close();
        }
    }
    $idList = implode(',', $ids);
    if ($ctrl->query("DELETE FROM control_registration_requests WHERE id IN ($idList)")) jsonOut(['success' => true, 'message' => 'Deleted']);
    jsonOut(['success' => false, 'message' => 'Delete failed']);
}

jsonOut(['success' => false, 'message' => 'Method not allowed']);
