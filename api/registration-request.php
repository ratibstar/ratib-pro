<?php
/**
 * EN: Handles API endpoint/business logic in `api/registration-request.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/registration-request.php`.
 */
/**
 * Public API: Submit agency registration request (Pro plan).
 * No authentication required. Rate limited by IP.
 */
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// EN: Registration is write-only endpoint; reject non-POST requests early.
// AR: التسجيل نقطة نهاية للكتابة فقط؛ رفض أي طلب ليس POST مبكراً.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

function jsonOut($data) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

// EN: Prefer control DB so requests are visible in control-center workflows.
// AR: تفضيل قاعدة التحكم لضمان ظهور الطلبات داخل تدفقات مركز التحكم.
// Use control panel DB so registration requests appear in control panel; fallback to main conn
$conn = null;
if (defined('CONTROL_PANEL_DB_NAME') && CONTROL_PANEL_DB_NAME !== '' && defined('DB_HOST') && defined('DB_USER')) {
    $ctrlDb = CONTROL_PANEL_DB_NAME;
    $mainDb = defined('DB_NAME') ? DB_NAME : '';
    if ($ctrlDb !== $mainDb) {
        try {
            $conn = @new mysqli(DB_HOST, DB_USER, defined('DB_PASS') ? DB_PASS : '', $ctrlDb, defined('DB_PORT') ? (int)DB_PORT : 3306);
            if ($conn && !$conn->connect_error) {
                $conn->set_charset('utf8mb4');
            } else {
                if ($conn) { $conn->close(); }
                $conn = $GLOBALS['conn'] ?? null;
            }
        } catch (Throwable $e) {
            $conn = $GLOBALS['conn'] ?? null;
        }
    }
}
if (!$conn) {
    $conn = $GLOBALS['conn'] ?? null;
}
if (!$conn) {
    jsonOut(['success' => false, 'message' => 'Service temporarily unavailable']);
}

// Ensure table exists
$chk = $conn->query("SHOW TABLES LIKE 'control_registration_requests'");
if (!$chk || $chk->num_rows === 0) {
    jsonOut(['success' => false, 'message' => 'Registration is not configured yet']);
}

$ip = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''));
if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);
$userAgent = substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 512);

// EN: IP-based throttling to reduce spam/abuse on public submission endpoint.
// AR: تحديد معدل حسب عنوان IP لتقليل الإزعاج وإساءة الاستخدام في واجهة الإرسال العامة.
// Rate limit: max 10 requests per IP per hour (increased from 5 for better testing)
// For testing: You can temporarily increase this number or disable rate limiting
$escIp = $conn->real_escape_string($ip);
$rateLimitCount = 10; // Max requests per hour per IP
$rateLimitWindow = 1; // Hours

$limitCheck = $conn->query("SELECT COUNT(*) as c FROM control_registration_requests WHERE ip_address = '{$escIp}' AND created_at > DATE_SUB(NOW(), INTERVAL {$rateLimitWindow} HOUR)");
if ($limitCheck && ($row = $limitCheck->fetch_assoc()) && (int)($row['c'] ?? 0) >= $rateLimitCount) {
    $waitTime = $rateLimitWindow * 60; // Convert to minutes
    jsonOut(['success' => false, 'message' => "Too many requests. Please try again in {$waitTime} minutes."]);
}

// EN: Accept both JSON payload and traditional form payload for compatibility.
// AR: قبول كل من حمولة JSON وبيانات النماذج التقليدية للتوافق.
$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$agencyName = trim((string)($input['agency_name'] ?? ''));
$agencyIdUser = trim((string)($input['agency_id'] ?? ''));
$countryId = isset($input['country_id']) && ctype_digit((string)$input['country_id']) ? (int)$input['country_id'] : null;
$countryName = trim((string)($input['country_name'] ?? $input['country'] ?? ''));
$contactEmail = trim((string)($input['contact_email'] ?? ''));
$contactPhone = trim((string)($input['contact_phone'] ?? ''));
$desiredSiteUrl = trim((string)($input['desired_site_url'] ?? ''));
$notes = trim((string)($input['notes'] ?? ''));
$plan = trim((string)($input['plan'] ?? 'pro')) ?: 'pro';
$planAmount = isset($input['plan_amount']) ? (float)$input['plan_amount'] : null;
$years = isset($input['years']) ? (int)$input['years'] : null;
$paymentStatus = isset($input['payment_status']) ? trim((string)$input['payment_status']) : null;
$paymentMethod = isset($input['payment_method']) ? trim((string)$input['payment_method']) : null;

if ($agencyName === '') jsonOut(['success' => false, 'message' => 'Agency name is required']);
if ($contactEmail === '') jsonOut(['success' => false, 'message' => 'Contact email is required']);
if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) jsonOut(['success' => false, 'message' => 'Invalid email address']);
if ($desiredSiteUrl !== '' && !preg_match('/^https?:\/\/.+/', $desiredSiteUrl)) {
    jsonOut(['success' => false, 'message' => 'Site URL must start with http:// or https://']);
}

// Honeypot (bots often fill hidden fields)
$honeypot = trim((string)($input['website_url'] ?? ''));
if ($honeypot !== '') jsonOut(['success' => true, 'message' => 'Thank you. We will contact you soon.']);

// Resolve country_id from country_name when country_id not provided (for country-filtered display in control panel)
if (($countryId === null || $countryId <= 0) && $countryName !== '') {
    $chkCc = $conn->query("SHOW TABLES LIKE 'control_countries'");
    if ($chkCc && $chkCc->num_rows > 0) {
        $escName = $conn->real_escape_string($countryName);
        $r = $conn->query("SELECT id FROM control_countries WHERE name = '{$escName}' AND is_active = 1 LIMIT 1");
        if ($r && $row = $r->fetch_assoc()) {
            $countryId = (int)$row['id'];
        }
    }
}

$cols = $conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'agency_id'");
$hasAgencyId = ($cols && $cols->num_rows > 0);
$colsAmt = $conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'plan_amount'");
$hasPlanAmount = ($colsAmt && $colsAmt->num_rows > 0);
$colsYears = $conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'years'");
$hasYears = ($colsYears && $colsYears->num_rows > 0);
$colsPaymentStatus = $conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'");
$hasPaymentStatus = ($colsPaymentStatus && $colsPaymentStatus->num_rows > 0);
$colsPaymentMethod = $conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_method'");
$hasPaymentMethod = ($colsPaymentMethod && $colsPaymentMethod->num_rows > 0);

// Build INSERT query dynamically based on available columns
$cid = ($countryId !== null && $countryId > 0) ? (int)$countryId : 0;
if ($cid === 0) $countryName = $countryName ?: '';

// Validate payment_status if provided
if ($paymentStatus !== null && !in_array($paymentStatus, ['unpaid', 'paid', 'pending', 'failed'])) {
    $paymentStatus = null;
}
// Validate payment_method if provided
if ($paymentMethod !== null && !in_array($paymentMethod, ['paypal', 'tap', 'register'])) {
    $paymentMethod = null;
}

// EN: Build INSERT dynamically to support mixed database schema versions safely.
// AR: بناء جملة الإدخال بشكل ديناميكي لدعم إصدارات مخطط قاعدة البيانات المختلفة بأمان.
// Build dynamic INSERT query
$fields = ['agency_name', 'country_id', 'country_name', 'contact_email', 'contact_phone', 'desired_site_url', 'notes', 'plan', 'ip_address', 'user_agent'];
$placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?'];
$bindTypes = 'sissssssss';
$bindValues = [&$agencyName, &$cid, &$countryName, &$contactEmail, &$contactPhone, &$desiredSiteUrl, &$notes, &$plan, &$ip, &$userAgent];

if ($hasAgencyId) {
    $fields[] = 'agency_id';
    $placeholders[] = '?';
    $bindTypes .= 's';
    $bindValues[] = &$agencyIdUser;
}

if ($hasPlanAmount && $planAmount !== null && $planAmount > 0) {
    $fields[] = 'plan_amount';
    $placeholders[] = '?';
    $bindTypes .= 'd';
    $bindValues[] = &$planAmount;
}

if ($hasYears && $years !== null) {
    $fields[] = 'years';
    $placeholders[] = '?';
    $bindTypes .= 'i';
    $bindValues[] = &$years;
}

if ($hasPaymentStatus && $paymentStatus !== null) {
    $fields[] = 'payment_status';
    $placeholders[] = '?';
    $bindTypes .= 's';
    $bindValues[] = &$paymentStatus;
}

if ($hasPaymentMethod && $paymentMethod !== null) {
    $fields[] = 'payment_method';
    $placeholders[] = '?';
    $bindTypes .= 's';
    $bindValues[] = &$paymentMethod;
}

$sql = "INSERT INTO control_registration_requests (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    jsonOut(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

// Use call_user_func_array for dynamic binding (preserves references)
$bindArgs = array_merge([$bindTypes], $bindValues);
call_user_func_array([$stmt, 'bind_param'], $bindArgs);

if ($stmt->execute()) {
    $insertId = (int)($conn->insert_id ?? 0);
    $out = ['success' => true, 'message' => 'Thank you. Your request has been submitted. We will contact you soon.'];
    // Always include registration_id when Tap flow requested (so frontend can redirect to payment)
    if ($insertId > 0 && $paymentMethod === 'tap') {
        $out['registration_id'] = (string)$insertId;
    }
    jsonOut($out);
}
jsonOut(['success' => false, 'message' => 'Could not submit. Please try again.']);
