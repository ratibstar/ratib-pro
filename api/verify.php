<?php
/**
 * EN: Handles API endpoint/business logic in `api/verify.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/verify.php`.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(0);

require_once __DIR__ . '/../includes/payment_api_guards.php';
require_once __DIR__ . '/../includes/payment_api_throwable_polyfill.php';
require_once __DIR__ . '/../includes/payment_control_registration_sync.php';
payment_api_register_fatal_json_handler();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    payment_api_mark_completed();
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonOut(int $status, array $payload): void
{
    payment_api_mark_completed();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function paymentLog(string $message, array $context = []): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message;
    if ($context !== []) {
        $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES);
    }
    @file_put_contents($logDir . '/payment.log', $line . PHP_EOL, FILE_APPEND);
}

try {
    require_once __DIR__ . '/../includes/payment_api_bootstrap.php';
    require_once __DIR__ . '/../includes/payment_orders_schema.php';
    require_once __DIR__ . '/../includes/ngenius.php';
} catch (Throwable $e) {
    $root = payment_api_root_throwable($e);
    paymentLog('verify bootstrap failed', [
        'error' => $e->getMessage(),
        'file' => $root->getFile(),
        'line' => $root->getLine(),
    ]);
    jsonOut(500, [
        'message' => 'Payment bootstrap failed: ' . $e->getMessage(),
        'error' => $e->getMessage(),
        'origin' => basename($root->getFile()) . ':' . $root->getLine(),
    ]);
}

/** @return PDO */
function paymentPdo()
{
    if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
        throw new RuntimeException('PHP extensions pdo and pdo_mysql are required.');
    }

    if (!defined('DB_HOST') || !defined('DB_USER')) {
        throw new RuntimeException('Database not configured.');
    }

    /* Same DB as create-order.php — main app database. */
    $dbName = defined('DB_NAME') ? DB_NAME : '';

    if ($dbName === '') {
        throw new RuntimeException('Database name not configured.');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        DB_HOST,
        defined('DB_PORT') ? (int) DB_PORT : 3306,
        $dbName
    );

    return new PDO($dsn, DB_USER, defined('DB_PASS') ? DB_PASS : '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
}

function fetchOrder($pdo, int $orderId): ?array
{
    $t = RATIB_NGENIUS_ORDERS_TABLE;
    $stmt = $pdo->prepare(
        "SELECT id, email, plan_key, years, total_amount, ngenius_order_id, control_request_id,
                reg_agency_name, reg_agency_id, reg_country_id, reg_country_name,
                reg_contact_phone, reg_desired_site_url, reg_notes
         FROM `{$t}` WHERE id = :id LIMIT 1"
    );
    $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function updateOrderControlRequestId($pdo, int $orderId, int $controlRequestId): void
{
    if ($controlRequestId <= 0) {
        return;
    }
    $t = RATIB_NGENIUS_ORDERS_TABLE;
    $stmt = $pdo->prepare("UPDATE `{$t}` SET control_request_id = :cid WHERE id = :id");
    $stmt->bindValue(':cid', $controlRequestId, PDO::PARAM_INT);
    $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
}

/** @return mysqli|null */
function controlDbConn()
{
    return payment_control_panel_mysqli();
}

function syncControlPaymentStatus(?int $controlRequestId, string $status): void
{
    if ($controlRequestId === null || $controlRequestId <= 0) {
        return;
    }
    $conn = controlDbConn();
    if (!$conn) {
        paymentLog('verify control sync skipped: no DB connection', ['order_status' => $status]);
        return;
    }
    $chk = $conn->query("SHOW TABLES LIKE 'control_registration_requests'");
    if (!$chk || $chk->num_rows === 0) {
        paymentLog('verify control sync skipped: table not found', ['order_status' => $status]);
        return;
    }
    $map = [
        'paid' => 'paid',
        'failed' => 'failed',
        'pending' => 'pending',
    ];
    $paymentStatus = $map[$status] ?? 'pending';
    $hasPaymentStatus = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'")->num_rows ?? 0) > 0;
    if (!$hasPaymentStatus) {
        paymentLog('verify control sync skipped: payment_status column missing');
        return;
    }
    $safeStatus = $conn->real_escape_string($paymentStatus);
    $conn->query("UPDATE control_registration_requests SET payment_status = '{$safeStatus}', updated_at = NOW() WHERE id = " . (int) $controlRequestId);
    if ((int) ($conn->affected_rows ?? 0) > 0) {
        paymentLog('verify control sync updated', ['control_request_id' => $controlRequestId, 'payment_status' => $paymentStatus]);
    } else {
        paymentLog('verify control sync no-op (id not found or unchanged)', ['control_request_id' => $controlRequestId, 'payment_status' => $paymentStatus]);
    }
}

function controlRequestExists($conn, int $controlRequestId): bool
{
    if ($controlRequestId <= 0) {
        return false;
    }
    $res = $conn->query("SELECT id FROM control_registration_requests WHERE id = " . (int) $controlRequestId . " LIMIT 1");
    return (bool) ($res && $res->num_rows > 0);
}

/**
 * Sync control panel row from ngenius_reg_orders (plan, amount, years, reg snapshot, email).
 * Previously skipped when reg_* were all empty, which left Plan / Amount blank.
 */
function enrichControlRegistrationFromOrder(?mysqli $conn, ?int $controlRequestId, array $order): void
{
    if ($controlRequestId === null || $controlRequestId <= 0) {
        return;
    }
    if ($conn instanceof mysqli && !$conn->connect_error) {
        $order = payment_enrich_order_row_country_id($conn, $order);
    } else {
        $pd = payment_control_pdo();
        if ($pd instanceof PDO) {
            $order = payment_enrich_snapshot_with_pdo_country_id($pd, $order);
        }
    }
    payment_sync_control_row_from_ngenius_order($conn, $controlRequestId, $order, null);
}

function ensureControlRequestLinked($pdo, array $order, string $status): ?int
{
    $existing = (int) ($order['control_request_id'] ?? 0);
    $conn = controlDbConn();
    if (!$conn) {
        paymentLog('verify link skipped: no control DB connection', ['order_id' => (int) ($order['id'] ?? 0)]);
        return null;
    }
    $chk = $conn->query("SHOW TABLES LIKE 'control_registration_requests'");
    if (!$chk || $chk->num_rows === 0) {
        paymentLog('verify link skipped: control table missing', ['order_id' => (int) ($order['id'] ?? 0)]);
        return null;
    }
    if (!$conn->connect_error) {
        $order = payment_enrich_order_row_country_id($conn, $order);
    }
    if ($existing > 0 && controlRequestExists($conn, $existing)) {
        return $existing;
    }
    if ($existing > 0 && !controlRequestExists($conn, $existing)) {
        paymentLog('verify link stale control_request_id detected', ['order_id' => (int) ($order['id'] ?? 0), 'control_request_id' => $existing]);
    }

    $orderId = (int) ($order['id'] ?? 0);
    if ($orderId <= 0) {
        return null;
    }
    $marker = 'Auto link from verify order_id=' . $orderId;
    $safeMarker = $conn->real_escape_string($marker);
    $exists = $conn->query("SELECT id FROM control_registration_requests WHERE notes = '{$safeMarker}' LIMIT 1");
    $controlId = ($exists && $exists->num_rows > 0) ? (int) (($exists->fetch_assoc()['id'] ?? 0)) : 0;
    if ($controlId > 0) {
        updateOrderControlRequestId($pdo, $orderId, $controlId);
        return $controlId;
    }

    $hasPlanAmount = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'plan_amount'")->num_rows ?? 0) > 0;
    $hasYears = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'years'")->num_rows ?? 0) > 0;
    $hasPaymentStatus = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'")->num_rows ?? 0) > 0;
    $hasPaymentMethod = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_method'")->num_rows ?? 0) > 0;
    $colAgencyIdUser = $conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'agency_id'");
    $hasAgencyIdUserCol = ($colAgencyIdUser && $colAgencyIdUser->num_rows > 0);

    $plan = strtolower(trim((string) ($order['plan_key'] ?? '')));
    $years = max(1, (int) ($order['years'] ?? 1));
    $total = (float) ($order['total_amount'] ?? 0.0);
    $email = trim((string) ($order['email'] ?? ''));
    $payStatus = $status === 'paid' ? 'paid' : ($status === 'failed' ? 'failed' : 'pending');

    $snapAgency = trim((string) ($order['reg_agency_name'] ?? ''));
    $agencyDisplay = $snapAgency !== '' ? $snapAgency : ('N-Genius ' . strtoupper($plan !== '' ? $plan : 'PLAN'));
    $snapAgencyId = trim((string) ($order['reg_agency_id'] ?? ''));
    $snapCountryId = (int) ($order['reg_country_id'] ?? 0);
    $snapCountry = trim((string) ($order['reg_country_name'] ?? ''));
    $snapPhone = trim((string) ($order['reg_contact_phone'] ?? ''));
    $snapSite = trim((string) ($order['reg_desired_site_url'] ?? ''));
    $snapNotes = trim((string) ($order['reg_notes'] ?? ''));
    $notesCell = $snapNotes !== '' ? ($marker . ' | ' . $snapNotes) : $marker;

    $fields = ['agency_name'];
    $values = ['?'];
    $types = 's';
    $bind = [$agencyDisplay];
    if ($hasAgencyIdUserCol) {
        $fields[] = 'agency_id';
        $values[] = '?';
        $types .= 's';
        $bind[] = $snapAgencyId;
    }
    $fields = array_merge($fields, ['country_id', 'country_name', 'contact_email', 'contact_phone', 'desired_site_url', 'notes', 'plan', 'ip_address', 'user_agent']);
    $values = array_merge($values, ['?', '?', '?', '?', '?', '?', '?', '?', '?']);
    $types .= 'sisssssss';
    $bind = array_merge($bind, [$snapCountryId, $snapCountry, $email, $snapPhone, $snapSite, $notesCell, $plan, '', 'N-Genius-verify-link']);

    if ($hasPlanAmount) {
        $fields[] = 'plan_amount';
        $values[] = '?';
        $types .= 'd';
        $bind[] = $total;
    }
    if ($hasYears) {
        $fields[] = 'years';
        $values[] = '?';
        $types .= 'i';
        $bind[] = $years;
    }
    if ($hasPaymentStatus) {
        $fields[] = 'payment_status';
        $values[] = '?';
        $types .= 's';
        $bind[] = $payStatus;
    }
    if ($hasPaymentMethod) {
        $fields[] = 'payment_method';
        $values[] = '?';
        $types .= 's';
        $bind[] = 'register';
    }

    $sql = "INSERT INTO control_registration_requests (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        paymentLog('verify link prepare failed', ['order_id' => $orderId, 'error' => (string) $conn->error]);
        return null;
    }
    if (!payment_mysqli_stmt_bind_param_safe($stmt, $types, $bind)) {
        paymentLog('verify link bind_param failed', ['order_id' => $orderId, 'types' => $types]);
        return null;
    }
    if (!$stmt->execute()) {
        paymentLog('verify link execute failed', ['order_id' => $orderId, 'error' => (string) $stmt->error]);
        return null;
    }
    $controlId = (int) ($conn->insert_id ?? 0);
    if ($controlId > 0) {
        updateOrderControlRequestId($pdo, $orderId, $controlId);
        paymentLog('verify link created', ['order_id' => $orderId, 'control_request_id' => $controlId]);
        return $controlId;
    }
    return null;
}

function verifyRespond(bool $success, string $message, int $orderId, array $meta = []): void
{
    $wantsJson = (isset($_GET['json']) && (string) $_GET['json'] === '1');
    if ($wantsJson) {
        jsonOut(200, array_merge(['success' => $success, 'message' => $message, 'order_id' => $orderId], $meta));
    }
    $baseUrl = defined('SITE_URL') && SITE_URL ? rtrim((string) SITE_URL, '/') : '';
    if ($baseUrl === '') {
        $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $baseUrl = $scheme . '://' . $host;
    }
    $nextUrl = $baseUrl . '/pages/home.php?open=register&payment=' . ($success ? 'success' : 'failed') . '&order_id=' . $orderId;
    $safeMsg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safeNext = htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8');
    payment_api_mark_completed();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Payment status</title></head><body>';
    echo '<script>alert("' . addslashes($safeMsg) . '");window.location.href="' . $safeNext . '";</script>';
    echo '</body></html>';
    exit;
}

function updateOrderStatus($pdo, int $orderId, string $status): void
{
    $t = RATIB_NGENIUS_ORDERS_TABLE;
    $stmt = $pdo->prepare("UPDATE `{$t}` SET status = :status WHERE id = :id");
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
}

function activateUser($pdo, string $email): void
{
    $stmt = $pdo->prepare('UPDATE users SET status = :active WHERE email = :email AND status = :pending');
    $stmt->bindValue(':active', 'active', PDO::PARAM_STR);
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->bindValue(':pending', 'pending', PDO::PARAM_STR);
    $stmt->execute();
}

function storePaymentRecord($pdo, int $orderId, string $reference, string $status, string $rawResponse): void
{
    $t = RATIB_NGENIUS_PAYMENTS_TABLE;
    $stmt = $pdo->prepare(
        "INSERT INTO `{$t}` (order_id, reference, status, raw_response, created_at) VALUES (:order_id, :reference, :status, :raw_response, NOW())"
    );
    $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->bindValue(':reference', substr($reference, 0, 128), PDO::PARAM_STR);
    $stmt->bindValue(':status', substr($status, 0, 32), PDO::PARAM_STR);
    $stmt->bindValue(':raw_response', $rawResponse, PDO::PARAM_STR);
    $stmt->execute();
}

$orderIdRaw = trim((string) ($_GET['order_id'] ?? ''));
if ($orderIdRaw === '' || !ctype_digit($orderIdRaw)) {
    jsonOut(400, ['message' => 'Invalid order_id']);
}
$orderId = (int) $orderIdRaw;

$apiKey = (string) ratib_ngenius_env('NGENIUS_API_KEY', '');
$apiSecret = (string) ratib_ngenius_env('NGENIUS_API_SECRET', '');
$outletId = (string) ratib_ngenius_env('NGENIUS_OUTLET_ID', '');
$fallbackBase = NGENIUS_DEFAULT_API_BASE_KSA;
$identityBase = rtrim((string) ratib_ngenius_env('NGENIUS_IDENTITY_BASE', (string) ratib_ngenius_env('NGENIUS_API_BASE', $fallbackBase)), '/');
$orderBase = rtrim((string) ratib_ngenius_env('NGENIUS_ORDER_BASE', (string) ratib_ngenius_env('NGENIUS_API_BASE', $fallbackBase)), '/');
$tokenUrl = trim((string) ratib_ngenius_env('NGENIUS_TOKEN_URL', ''));

if ($apiKey === '' || $outletId === '') {
    $ratibRoot = dirname(__DIR__);
    $ratibDoc = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string) $_SERVER['DOCUMENT_ROOT'], "/\\") : '';
    paymentLog('Missing N-Genius credentials in verify', [
        'has_key' => $apiKey !== '',
        'has_secret' => $apiSecret !== '',
        'has_outlet' => $outletId !== '',
        'dotenv_project_root' => is_readable($ratibRoot . '/.env'),
        'dotenv_document_root' => $ratibDoc !== '' && is_readable($ratibDoc . '/.env'),
        'secrets_config_readable' => is_readable(__DIR__ . '/../config/ngenius.secrets.php'),
        'secrets_env_readable' => is_readable(__DIR__ . '/../config/env/ngenius.secrets.php'),
        'defined_outlet' => defined('NGENIUS_OUTLET_ID'),
        'defined_key' => defined('NGENIUS_API_KEY'),
        'defined_secret' => defined('NGENIUS_API_SECRET'),
    ]);
    jsonOut(503, ['message' => 'Payment is not configured. Missing N-Genius credentials (same .env / secrets as create-order).']);
}

if (!function_exists('curl_init')) {
    jsonOut(500, ['message' => 'Payment unavailable: PHP curl extension is not enabled on this server.']);
}

$ngeniusRealm = trim((string) ratib_ngenius_env('NGENIUS_REALM', 'networkinternational'));
if ($ngeniusRealm === '') {
    $ngeniusRealm = 'networkinternational';
}

paymentLog('verify config', [
    'identity_base' => $identityBase,
    'order_base' => $orderBase,
    'realm' => $ngeniusRealm,
    'token_url' => $tokenUrl !== '' ? $tokenUrl : ($identityBase . '/identity/auth/access-token'),
    'outlet_id_set' => $outletId !== '',
    'order_id' => $orderId,
]);

try {
    $pdo = paymentPdo();
    payment_ensure_ngenius_tables($pdo);
    $order = fetchOrder($pdo, $orderId);
} catch (Throwable $e) {
    paymentLog('Verify DB read error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
    $dbErr = $e->getMessage();
    if (preg_match('/pdo_mysql|could not find driver|class [\'"]?pdo[\'"]? not found|pdo and pdo_mysql are required/i', $dbErr)) {
        jsonOut(500, ['message' => 'Database driver unavailable: enable PHP extensions pdo and pdo_mysql on this server.']);
    }
    jsonOut(500, ['message' => 'Database error']);
}

if (!$order) {
    jsonOut(404, ['message' => 'Order not found']);
}

$ngeniusRef = trim((string) ($order['ngenius_order_id'] ?? ''));
if ($ngeniusRef === '') {
    try {
        updateOrderStatus($pdo, $orderId, 'failed');
    } catch (Throwable $e) {
        paymentLog('Verify update failed (missing reference)', ['order_id' => $orderId, 'error' => $e->getMessage()]);
    }
    jsonOut(200, ['success' => false, 'message' => 'Payment failed']);
}

$tokenRes = ngenius_fetch_access_token($identityBase, $apiKey, $apiSecret, $tokenUrl === '' ? null : $tokenUrl, $ngeniusRealm);
paymentLog('Verify token response', [
    'status' => $tokenRes['http_status'],
    'curl_error' => $tokenRes['curl_error'],
    'body' => $tokenRes['body'],
    'order_id' => $orderId,
]);

if ($tokenRes['curl_error'] !== '' || $tokenRes['http_status'] < 200 || $tokenRes['http_status'] >= 300 || !$tokenRes['ok']) {
    $resolvedTokenUrl = $tokenUrl !== '' ? $tokenUrl : ($identityBase . '/identity/auth/access-token');
    jsonOut(502, array_merge(
        ngenius_token_failure_client_payload($tokenRes),
        [
            'payment_config' => [
                'token_url' => $resolvedTokenUrl,
                'identity_base' => $identityBase,
                'order_base' => $orderBase,
                'realm' => $ngeniusRealm,
                'api_key_length' => strlen($apiKey),
            ],
        ]
    ));
}

$accessToken = $tokenRes['access_token'];

$verifyHeaders = [
    'Accept: application/vnd.ni-payment.v2+json',
    'Authorization: Bearer ' . $accessToken,
];
$verifyUrl = $orderBase . '/transactions/outlets/' . rawurlencode($outletId) . '/orders/' . rawurlencode($ngeniusRef);
$verifyRes = ngenius_http_request('GET', $verifyUrl, $verifyHeaders, null);
paymentLog('Verify order response', ['status' => $verifyRes['status'], 'body' => $verifyRes['body'], 'order_id' => $orderId]);

$verifyData = json_decode($verifyRes['body'], true);
$paymentState = '';
$paymentReason = '';
if (is_array($verifyData)) {
    $embedded = $verifyData['_embedded'] ?? null;
    $payments = is_array($embedded) && isset($embedded['payment']) && is_array($embedded['payment'])
        ? $embedded['payment']
        : [];
    $first = $payments[0] ?? null;
    if (is_array($first)) {
        $paymentState = (string) ($first['state'] ?? '');
        $stateMessage = trim((string) ($first['stateDescription'] ?? ''));
        $resultCode = trim((string) ($first['resultCode'] ?? ''));
        $resultMessage = trim((string) ($first['resultMessage'] ?? ''));
        $declineCode = trim((string) ($first['declineCode'] ?? ''));
        $declineReason = trim((string) ($first['declineReason'] ?? ''));
        $reasonBits = [];
        if ($stateMessage !== '') {
            $reasonBits[] = $stateMessage;
        }
        if ($resultCode !== '') {
            $reasonBits[] = 'resultCode=' . $resultCode;
        }
        if ($resultMessage !== '') {
            $reasonBits[] = $resultMessage;
        }
        if ($declineCode !== '') {
            $reasonBits[] = 'declineCode=' . $declineCode;
        }
        if ($declineReason !== '') {
            $reasonBits[] = $declineReason;
        }
        if ($reasonBits !== []) {
            $paymentReason = implode(' | ', $reasonBits);
        }
    }
}
$isPurchased = strtoupper($paymentState) === 'PURCHASED';
$status = $isPurchased ? 'paid' : 'failed';

if ($verifyRes['error'] !== '' || $verifyRes['status'] < 200 || $verifyRes['status'] >= 300) {
    $status = 'failed';
}

$rawResponse = $verifyRes['body'] !== '' ? $verifyRes['body'] : json_encode(['error' => 'empty response'], JSON_UNESCAPED_SLASHES);

try {
    $pdo->beginTransaction();

    updateOrderStatus($pdo, $orderId, $status);
    storePaymentRecord($pdo, $orderId, $ngeniusRef, $status, $rawResponse);

    if ($status === 'paid') {
        $orderEmail = trim((string) ($order['email'] ?? ''));
        if ($orderEmail !== '') {
            activateUser($pdo, $orderEmail);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    paymentLog('Verify persistence error', ['order_id' => $orderId, 'error' => $e->getMessage()]);
    jsonOut(500, ['message' => 'Could not persist payment verification']);
}

$linkedControlRequestId = null;
$finalControlRequestId = isset($order['control_request_id']) ? (int) $order['control_request_id'] : null;
if ($status === 'paid') {
    $linkedControlRequestId = ensureControlRequestLinked($pdo, $order, $status);
    $finalControlRequestId = $linkedControlRequestId ?? $finalControlRequestId;
    syncControlPaymentStatus($finalControlRequestId, $status);

    $controlConn = controlDbConn();
    enrichControlRegistrationFromOrder($controlConn instanceof mysqli ? $controlConn : null, is_int($finalControlRequestId) ? $finalControlRequestId : null, $order);
} else {
    paymentLog('verify skipped control queue link (payment not paid)', [
        'order_id' => $orderId,
        'status' => $status,
    ]);
}

$controlRowExists = false;
if (!isset($controlConn)) {
    $controlConn = controlDbConn();
}
if ($controlConn instanceof mysqli && is_int($finalControlRequestId) && $finalControlRequestId > 0) {
    $controlRowExists = controlRequestExists($controlConn, $finalControlRequestId);
}
$verifyMeta = [
    'verify_debug' => [
        'db_name' => defined('DB_NAME') ? (string) DB_NAME : '',
        'control_db_name' => defined('CONTROL_PANEL_DB_NAME') ? (string) CONTROL_PANEL_DB_NAME : '',
        'order_control_request_id_before' => isset($order['control_request_id']) ? (int) $order['control_request_id'] : 0,
        'order_control_request_id_after' => is_int($finalControlRequestId) ? $finalControlRequestId : 0,
        'control_row_exists' => $controlRowExists,
        'payment_status' => $status,
        'gateway_state' => $paymentState,
        'gateway_reason' => $paymentReason,
    ],
];

if ($status === 'paid') {
    verifyRespond(true, 'Payment verified successfully', $orderId, $verifyMeta);
}
$failMsg = 'Payment failed';
if ($paymentState !== '') {
    $failMsg .= ' (' . $paymentState . ')';
}
if ($paymentReason !== '') {
    $failMsg .= ' - ' . $paymentReason;
}
verifyRespond(false, $failMsg, $orderId, $verifyMeta);
