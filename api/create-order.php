<?php
/**
 * EN: Handles API endpoint/business logic in `api/create-order.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/create-order.php`.
 */
declare(strict_types=1);

/** Bumps when create-order.php changes — check Network response headers or GET ?ping=1 */
const RATIB_CREATE_ORDER_RELEASE = '2026-04-16a';
const RATIB_CREATE_ORDER_DEDUPE_WINDOW_SECONDS = 86400;

ini_set('display_errors', '0');
error_reporting(0);

require_once __DIR__ . '/../includes/payment_api_guards.php';
require_once __DIR__ . '/../includes/payment_api_throwable_polyfill.php';
payment_api_register_fatal_json_handler();

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    payment_api_mark_completed();
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ping'])) {
    payment_api_mark_completed();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Ratib-Create-Order-Release: ' . RATIB_CREATE_ORDER_RELEASE);
    }
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'backend_release' => RATIB_CREATE_ORDER_RELEASE,
        'script' => basename(__FILE__),
        'mtime' => @filemtime(__FILE__) ?: 0,
        'hint' => 'If POST still returns 500, open /api/ratib-payment-trace.php for DB/schema checks; then Network → create-order.php Response or logs/payment.log.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    payment_api_mark_completed();
    http_response_code(405);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('X-Ratib-Create-Order-Release: ' . RATIB_CREATE_ORDER_RELEASE);
    }
    echo json_encode([
        'message' => 'Method not allowed. Use POST for checkout, or GET ?ping=1 to verify deployment.',
        'backend_release' => RATIB_CREATE_ORDER_RELEASE,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function jsonOut(int $status, array $payload): void
{
    payment_api_mark_completed();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('X-Ratib-Create-Order-Release: ' . RATIB_CREATE_ORDER_RELEASE);
    }
    http_response_code($status);
    if (!array_key_exists('backend_release', $payload)) {
        $payload['backend_release'] = RATIB_CREATE_ORDER_RELEASE;
    }
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
} catch (Throwable $e) {
    $root = payment_api_root_throwable($e);
    paymentLog('create-order bootstrap failed (payment_api_bootstrap)', [
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

try {
    require_once __DIR__ . '/../includes/payment_orders_schema.php';
} catch (Throwable $e) {
    $root = payment_api_root_throwable($e);
    paymentLog('create-order bootstrap failed (payment_orders_schema)', [
        'error' => $e->getMessage(),
        'file' => $root->getFile(),
        'line' => $root->getLine(),
    ]);
    jsonOut(500, [
        'message' => 'Payment schema load failed: ' . $e->getMessage(),
        'error' => $e->getMessage(),
        'origin' => basename($root->getFile()) . ':' . $root->getLine(),
    ]);
}

try {
    require_once __DIR__ . '/../includes/ngenius.php';
} catch (Throwable $e) {
    $root = payment_api_root_throwable($e);
    paymentLog('create-order bootstrap failed (ngenius)', [
        'error' => $e->getMessage(),
        'file' => $root->getFile(),
        'line' => $root->getLine(),
    ]);
    jsonOut(500, [
        'message' => 'Payment gateway load failed: ' . $e->getMessage(),
        'error' => $e->getMessage(),
        'origin' => basename($root->getFile()) . ':' . $root->getLine(),
    ]);
}

try {
    require_once __DIR__ . '/../includes/payment_control_registration_sync.php';
} catch (Throwable $e) {
    $root = payment_api_root_throwable($e);
    paymentLog('create-order bootstrap failed (payment_control_registration_sync)', [
        'error' => $e->getMessage(),
        'file' => $root->getFile(),
        'line' => $root->getLine(),
    ]);
    jsonOut(500, [
        'message' => 'Payment control sync load failed: ' . $e->getMessage(),
        'error' => $e->getMessage(),
        'origin' => basename($root->getFile()) . ':' . $root->getLine(),
        'hint' => 'Fix PHP in includes/payment_control_registration_sync.php or PHP version (7.4+ required).',
    ]);
}

const TAX_RATE = 0.15;

/**
 * Map USD list prices (site UI) to N-Genius `amount.value` minor units for the configured checkout currency.
 * KSA outlets typically use SAR: value is halalas (2 decimal places), so multiply USD by peg then ×100.
 *
 * @return array{minor:int,currency:string,usd_to_sar?:float}
 */
function ratib_ngenius_minor_units_from_usd_total(float $totalUsd): array
{
    $currency = strtoupper(trim((string) ratib_ngenius_env('NGENIUS_CHECKOUT_CURRENCY', 'SAR')));
    if ($currency === '') {
        $currency = 'SAR';
    }

    if ($currency === 'USD') {
        return [
            'minor' => (int) round($totalUsd * 100),
            'currency' => 'USD',
        ];
    }

    $rate = (float) ratib_ngenius_env('NGENIUS_USD_TO_SAR', '3.75');
    if (!is_finite($rate) || $rate <= 0) {
        $rate = 3.75;
    }

    $inCheckout = $totalUsd * $rate;

    return [
        'minor' => (int) round($inCheckout * 100),
        'currency' => $currency,
        'usd_to_sar' => $rate,
    ];
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

    /* N-Genius registration orders live in the main site DB (DB_NAME), not CONTROL_PANEL_DB_NAME. */
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

    /* true: native MySQL prepares (false) often break LIMIT/decimal binds on shared hosts; emulated is fine for bound params. */
    return new PDO($dsn, DB_USER, defined('DB_PASS') ? DB_PASS : '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);
}

function inferBaseUrl(): string
{
    if (defined('SITE_URL') && is_string(SITE_URL) && SITE_URL !== '') {
        return rtrim(SITE_URL, '/');
    }
    $https = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return $scheme . '://' . $host;
}

function appendOrderId(string $url, int $orderId): string
{
    $separator = strpos($url, '?') === false ? '?' : '&';
    return $url . $separator . 'order_id=' . rawurlencode((string) $orderId);
}

function readInput(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return array_merge($_POST, $json);
    }
    return $_POST;
}

/**
 * Guard against repeated clicks/retries that create many pending gateway orders.
 * Returns latest pending local order id for same key fields in the recent window.
 */
function findRecentDuplicatePendingOrderId(
    PDO $pdo,
    string $email
): int {
    $t = RATIB_NGENIUS_ORDERS_TABLE;
    $windowMinutes = max(1, (int) ceil(RATIB_CREATE_ORDER_DEDUPE_WINDOW_SECONDS / 60));
    $sql = "SELECT id
            FROM `{$t}`
            WHERE email = :email
              AND LOWER(TRIM(COALESCE(status, ''))) = 'pending'
              AND created_at >= (NOW() - INTERVAL {$windowMinutes} MINUTE)
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':email', substr($email, 0, 255), PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int) ($row['id'] ?? 0) : 0;
}

/**
 * @param array<string,mixed> $input
 */
function insertPendingOrder(
    $pdo,
    string $email,
    int $amountMinor,
    string $planKey,
    int $years,
    float $subtotal,
    float $taxAmount,
    float $totalAmount,
    ?int $controlRequestId,
    array $input
): int
{
    $t = RATIB_NGENIUS_ORDERS_TABLE;
    $regAgency = substr(trim((string) ($input['agency_name'] ?? '')), 0, 255);
    $regAgencyId = substr(trim((string) ($input['agency_id'] ?? '')), 0, 64);
    $regCountryId = isset($input['country_id']) && ctype_digit((string) $input['country_id'])
        ? (int) $input['country_id'] : 0;
    $regCountry = substr(trim((string) ($input['country_name'] ?? $input['country'] ?? '')), 0, 255);
    $regPhone = substr(trim((string) ($input['contact_phone'] ?? $input['phone'] ?? '')), 0, 64);
    $regSite = substr(trim((string) ($input['desired_site_url'] ?? '')), 0, 512);
    $regNotesRaw = trim((string) ($input['notes'] ?? ''));
    $regNotes = $regNotesRaw !== '' ? substr($regNotesRaw, 0, 2000) : null;

    $stmt = $pdo->prepare(
        "INSERT INTO `{$t}` (
            email, amount, plan_key, years, subtotal, tax_amount, total_amount, control_request_id, status,
            reg_agency_name, reg_agency_id, reg_country_id, reg_country_name, reg_contact_phone, reg_desired_site_url, reg_notes
         ) VALUES (
            :email, :amount, :plan_key, :years, :subtotal, :tax_amount, :total_amount, :control_request_id, :status,
            :reg_agency_name, :reg_agency_id, :reg_country_id, :reg_country_name, :reg_contact_phone, :reg_desired_site_url, :reg_notes
         )"
    );
    $stmt->bindValue(':email', substr($email, 0, 255), PDO::PARAM_STR);
    $stmt->bindValue(':amount', $amountMinor, PDO::PARAM_INT);
    $stmt->bindValue(':plan_key', substr($planKey, 0, 32), PDO::PARAM_STR);
    $stmt->bindValue(':years', $years, PDO::PARAM_INT);
    $stmt->bindValue(':subtotal', $subtotal);
    $stmt->bindValue(':tax_amount', $taxAmount);
    $stmt->bindValue(':total_amount', $totalAmount);
    if ($controlRequestId !== null && $controlRequestId > 0) {
        $stmt->bindValue(':control_request_id', $controlRequestId, PDO::PARAM_INT);
    } else {
        $stmt->bindValue(':control_request_id', null, PDO::PARAM_NULL);
    }
    $stmt->bindValue(':status', 'pending', PDO::PARAM_STR);
    $stmt->bindValue(':reg_agency_name', $regAgency, PDO::PARAM_STR);
    $stmt->bindValue(':reg_agency_id', $regAgencyId, PDO::PARAM_STR);
    $stmt->bindValue(':reg_country_id', $regCountryId, PDO::PARAM_INT);
    $stmt->bindValue(':reg_country_name', $regCountry, PDO::PARAM_STR);
    $stmt->bindValue(':reg_contact_phone', $regPhone, PDO::PARAM_STR);
    $stmt->bindValue(':reg_desired_site_url', $regSite, PDO::PARAM_STR);
    if ($regNotes === null) {
        $stmt->bindValue(':reg_notes', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':reg_notes', $regNotes, PDO::PARAM_STR);
    }
    $stmt->execute();
    $id = (int) $pdo->lastInsertId();
    if ($id < 1) {
        $sel = $pdo->prepare(
            "SELECT id FROM `{$t}` WHERE email = :e AND plan_key = :pk AND years = :y
             AND ABS(total_amount - :tot) < 0.0001
             ORDER BY id DESC LIMIT 1"
        );
        $sel->bindValue(':e', substr($email, 0, 255), PDO::PARAM_STR);
        $sel->bindValue(':pk', substr($planKey, 0, 32), PDO::PARAM_STR);
        $sel->bindValue(':y', $years, PDO::PARAM_INT);
        $sel->bindValue(':tot', $totalAmount);
        $sel->execute();
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        $id = $row ? (int) ($row['id'] ?? 0) : 0;
        if ($id > 0) {
            paymentLog('insertPendingOrder id fallback (lastInsertId was 0)', ['id' => $id]);
        }
    }
    if ($id < 1) {
        throw new RuntimeException('Could not create local order.');
    }
    return $id;
}

/** @return mysqli|null */
function controlDbConn()
{
    return payment_control_panel_mysqli();
}

/**
 * Create pending request in control panel table so admins can review/mark paid.
 *
 * @param array<string,mixed> $input
 */
function createControlRegistrationRequest(array $input, array $amountResolved): int
{
    $pdoTry = payment_control_pdo();
    if ($pdoTry instanceof PDO) {
        try {
            $newId = payment_insert_control_registration_via_pdo($pdoTry, $input, $amountResolved);
            if ($newId > 0) {
                paymentLog('control request created (PDO)', ['control_request_id' => $newId]);
                return $newId;
            }
        } catch (Throwable $e) {
            paymentLog('control request PDO insert failed', ['error' => $e->getMessage()]);
        }
    }

    try {
        $conn = controlDbConn();
        if (!$conn) {
            paymentLog('control request skipped: no DB connection');
            return 0;
        }
        $chk = $conn->query("SHOW TABLES LIKE 'control_registration_requests'");
        if (!$chk || $chk->num_rows === 0) {
            paymentLog('control request skipped: table not found');
            return 0;
        }

        $colAgencyIdUser = $conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'agency_id'");
    $hasAgencyIdUserCol = ($colAgencyIdUser && $colAgencyIdUser->num_rows > 0);

    $planNorm = strtolower(trim((string) ($amountResolved['plan'] ?? '')));
    $plan = substr($planNorm !== '' ? $planNorm : 'pro', 0, 32);
    $agencyName = trim((string) ($input['agency_name'] ?? ''));
    $agencyForRow = $agencyName !== '' ? $agencyName : ('N-Genius ' . strtoupper($planNorm !== '' ? $planNorm : 'PLAN'));
    $countryName = trim((string) ($input['country_name'] ?? $input['country'] ?? ''));
    $countryId = isset($input['country_id']) && ctype_digit((string) $input['country_id']) ? (int) $input['country_id'] : 0;
    $contactEmail = trim((string) ($input['contact_email'] ?? $input['email'] ?? ''));
    $contactPhone = trim((string) ($input['contact_phone'] ?? $input['phone'] ?? ''));
    $desiredSiteUrl = trim((string) ($input['desired_site_url'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));
    $planAmount = (float) ($amountResolved['total'] ?? 0.0);
    $years = (int) ($amountResolved['years'] ?? 1);
    $ip = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''));
    if (strpos($ip, ',') !== false) {
        $ip = trim((string) explode(',', $ip)[0]);
    }
    $userAgent = substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 512);

    $fields = ['agency_name'];
    $values = ['?'];
    $types = 's';
    $bind = [substr($agencyForRow, 0, 255)];
    if ($hasAgencyIdUserCol) {
        $fields[] = 'agency_id';
        $values[] = '?';
        $types .= 's';
        $bind[] = trim((string) ($input['agency_id'] ?? ''));
    }
    $fields = array_merge($fields, ['country_id', 'country_name', 'contact_email', 'contact_phone', 'desired_site_url', 'notes', 'plan', 'ip_address', 'user_agent']);
    $values = array_merge($values, ['?', '?', '?', '?', '?', '?', '?', '?', '?']);
    $types .= 'sisssssss';
    $bind = array_merge($bind, [$countryId, $countryName, $contactEmail, $contactPhone, $desiredSiteUrl, $notes, $plan, $ip, $userAgent]);

    $hasPlanAmount = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'plan_amount'")->num_rows ?? 0) > 0;
    $hasYears = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'years'")->num_rows ?? 0) > 0;
    $hasPaymentStatus = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'")->num_rows ?? 0) > 0;
    $hasPaymentMethod = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_method'")->num_rows ?? 0) > 0;

    if ($hasPlanAmount) {
        $fields[] = 'plan_amount';
        $values[] = '?';
        $types .= 'd';
        $bind[] = $planAmount;
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
        $bind[] = 'pending';
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
        paymentLog('control request prepare failed', ['error' => (string) $conn->error]);
        return 0;
    }
    if (!payment_mysqli_stmt_bind_param_safe($stmt, $types, $bind)) {
        paymentLog('control request bind_param failed', ['types' => $types]);
        return 0;
    }
    if (!$stmt->execute()) {
        paymentLog('control request execute failed', ['error' => (string) $stmt->error]);
        return 0;
    }
        $insertId = (int) ($conn->insert_id ?? 0);
        paymentLog('control request created', ['control_request_id' => $insertId, 'email' => $contactEmail, 'plan' => $plan]);
        return $insertId;
    } catch (Throwable $e) {
        paymentLog('control request mysqli path failed (checkout continues without control row)', [
            'error' => $e->getMessage(),
        ]);
        return 0;
    }
}

/**
 * After local ngenius_reg_orders row exists, sync control panel row (plan, amount, reg fields, notes marker).
 */
function updateControlRegistrationAfterOrderCreate(PDO $mainPdo, int $controlRequestId, int $localOrderId, array $input, array $amountResolved): void
{
    if ($controlRequestId < 1 || $localOrderId < 1) {
        return;
    }
    $marker = 'Auto universal link order_id=' . $localOrderId;
    $userNotes = trim((string) ($input['notes'] ?? ''));
    $notesCell = $userNotes !== '' ? ($marker . ' | ' . substr($userNotes, 0, 800)) : $marker;
    $snap = payment_build_ngenius_order_snapshot_for_control_sync($input, $amountResolved);
    $dbRow = payment_fetch_ngenius_order_row_for_control_sync($mainPdo, $localOrderId);
    if ($dbRow !== null) {
        $dbSnap = payment_order_row_to_control_sync_snapshot($dbRow);
        // Keep form-entered values if DB snapshot has empty/zero values.
        $pick = static function ($primary, $fallback) {
            if ($primary === null) {
                return $fallback;
            }
            if (is_string($primary) && trim($primary) === '') {
                return $fallback;
            }
            if ((is_int($primary) || is_float($primary)) && (float)$primary <= 0.0) {
                return $fallback;
            }
            return $primary;
        };
        $snap = [
            'email' => $pick($dbSnap['email'] ?? null, $snap['email'] ?? ''),
            'plan_key' => $pick($dbSnap['plan_key'] ?? null, $snap['plan_key'] ?? ''),
            'years' => $pick($dbSnap['years'] ?? null, $snap['years'] ?? 1),
            'total_amount' => $pick($dbSnap['total_amount'] ?? null, $snap['total_amount'] ?? 0.0),
            'reg_agency_name' => $pick($dbSnap['reg_agency_name'] ?? null, $snap['reg_agency_name'] ?? ''),
            'reg_agency_id' => $pick($dbSnap['reg_agency_id'] ?? null, $snap['reg_agency_id'] ?? ''),
            'reg_country_id' => $pick($dbSnap['reg_country_id'] ?? null, $snap['reg_country_id'] ?? 0),
            'reg_country_name' => $pick($dbSnap['reg_country_name'] ?? null, $snap['reg_country_name'] ?? ''),
            'reg_contact_phone' => $pick($dbSnap['reg_contact_phone'] ?? null, $snap['reg_contact_phone'] ?? ''),
            'reg_desired_site_url' => $pick($dbSnap['reg_desired_site_url'] ?? null, $snap['reg_desired_site_url'] ?? ''),
        ];
    }
    $mconn = controlDbConn();
    if ($mconn instanceof mysqli && !$mconn->connect_error) {
        $snap = payment_enrich_snapshot_with_country_id($mconn, $snap);
    } else {
        $pd = payment_control_pdo();
        if ($pd instanceof PDO) {
            $snap = payment_enrich_snapshot_with_pdo_country_id($pd, $snap);
        }
    }
    payment_sync_control_row_from_ngenius_order(
        ($mconn instanceof mysqli && !$mconn->connect_error) ? $mconn : null,
        $controlRequestId,
        $snap,
        $notesCell
    );
    paymentLog('control post-order sync ok', ['control_request_id' => $controlRequestId, 'order_id' => $localOrderId]);
}

/**
 * Backfill old ngenius_reg_orders rows that were created before control linkage existed.
 * Safe to run repeatedly; rows are deduplicated by notes marker and linked back by control_request_id.
 */
function backfillMissingControlRequests($pdo, int $limit = 50): int
{
    $conn = controlDbConn();
    if (!$conn) {
        return 0;
    }
    $chk = $conn->query("SHOW TABLES LIKE 'control_registration_requests'");
    if (!$chk || $chk->num_rows === 0) {
        return 0;
    }

    $hasPlanAmount = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'plan_amount'")->num_rows ?? 0) > 0;
    $hasYears = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'years'")->num_rows ?? 0) > 0;
    $hasPaymentStatus = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'")->num_rows ?? 0) > 0;
    $hasPaymentMethod = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_method'")->num_rows ?? 0) > 0;

    $ordersTable = RATIB_NGENIUS_ORDERS_TABLE;
    // Integer LIMIT only: native PDO MySQL prepares (ATTR_EMULATE_PREPARES false) often reject LIMIT :placeholder.
    $lim = max(1, min(500, $limit));
    $stmt = $pdo->prepare(
        "SELECT id, email, plan_key, years, total_amount, status,
                reg_agency_name, reg_agency_id, reg_country_id, reg_country_name,
                reg_contact_phone, reg_desired_site_url, reg_notes
         FROM `{$ordersTable}`
         WHERE (control_request_id IS NULL OR control_request_id = 0)
           AND email <> ''
         ORDER BY id ASC
         LIMIT " . $lim
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if (!is_array($rows) || $rows === []) {
        return 0;
    }

    $inserted = 0;
    foreach ($rows as $row) {
        $orderId = (int) ($row['id'] ?? 0);
        if ($orderId < 1) {
            continue;
        }
        $marker = 'Backfill from ngenius_reg_orders id=' . $orderId;
        $safeMarker = $conn->real_escape_string($marker);
        $dup = $conn->query("SELECT id FROM control_registration_requests WHERE notes = '{$safeMarker}' LIMIT 1");
        $existingControlId = ($dup && $dup->num_rows > 0) ? (int) (($dup->fetch_assoc()['id'] ?? 0)) : 0;

        $controlId = $existingControlId;
        if ($controlId < 1) {
            $plan = strtolower(trim((string) ($row['plan_key'] ?? '')));
            $years = (int) ($row['years'] ?? 1);
            $amount = (float) ($row['total_amount'] ?? 0.0);
            $status = strtolower(trim((string) ($row['status'] ?? 'pending')));
            $paymentStatus = $status === 'paid' ? 'paid' : ($status === 'failed' ? 'failed' : 'pending');

            $colAgencyIdUser = $conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'agency_id'");
            $hasAgencyIdUserCol = ($colAgencyIdUser && $colAgencyIdUser->num_rows > 0);

            $snapAgency = trim((string) ($row['reg_agency_name'] ?? ''));
            $agencyDisplay = $snapAgency !== '' ? $snapAgency : ('N-Genius ' . strtoupper($plan !== '' ? $plan : 'PLAN'));
            $snapAgencyId = trim((string) ($row['reg_agency_id'] ?? ''));
            $snapCountryId = (int) ($row['reg_country_id'] ?? 0);
            $snapCountry = trim((string) ($row['reg_country_name'] ?? ''));
            $snapPhone = trim((string) ($row['reg_contact_phone'] ?? ''));
            $snapSite = trim((string) ($row['reg_desired_site_url'] ?? ''));
            $snapNotes = trim((string) ($row['reg_notes'] ?? ''));
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
            $bind = array_merge($bind, [$snapCountryId, $snapCountry, (string) ($row['email'] ?? ''), $snapPhone, $snapSite, $notesCell, $plan, '', 'N-Genius-backfill']);

            if ($hasPlanAmount) {
                $fields[] = 'plan_amount';
                $values[] = '?';
                $types .= 'd';
                $bind[] = $amount;
            }
            if ($hasYears) {
                $fields[] = 'years';
                $values[] = '?';
                $types .= 'i';
                $bind[] = $years > 0 ? $years : 1;
            }
            if ($hasPaymentStatus) {
                $fields[] = 'payment_status';
                $values[] = '?';
                $types .= 's';
                $bind[] = $paymentStatus;
            }
            if ($hasPaymentMethod) {
                $fields[] = 'payment_method';
                $values[] = '?';
                $types .= 's';
                $bind[] = 'register';
            }

            $sql = "INSERT INTO control_registration_requests (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            $ins = $conn->prepare($sql);
            if (!$ins) {
                paymentLog('backfill prepare failed', ['error' => (string) $conn->error, 'order_id' => $orderId]);
                continue;
            }
            if (!payment_mysqli_stmt_bind_param_safe($ins, $types, $bind)) {
                paymentLog('backfill bind_param failed', ['order_id' => $orderId, 'types' => $types]);
                continue;
            }
            if (!$ins->execute()) {
                paymentLog('backfill insert failed', ['error' => (string) $ins->error, 'order_id' => $orderId]);
                continue;
            }
            $controlId = (int) ($conn->insert_id ?? 0);
            if ($controlId > 0) {
                $inserted++;
            }
        }

        if ($controlId > 0) {
            $upd = $pdo->prepare("UPDATE `{$ordersTable}` SET control_request_id = :cid WHERE id = :id");
            $upd->bindValue(':cid', $controlId, PDO::PARAM_INT);
            $upd->bindValue(':id', $orderId, PDO::PARAM_INT);
            $upd->execute();
        }
    }

    if ($inserted > 0) {
        paymentLog('backfill completed', ['inserted' => $inserted]);
    }
    return $inserted;
}

/**
 * Resolve paid plan amount from trusted server-side price table.
 *
 * @param array<string,mixed> $input
 * @return array{ok:bool,minor:int,subtotal:float,total:float,plan:string,years:int,message?:string,checkout_currency?:string,usd_to_sar_rate?:float|null}
 */
function resolvePlanAmount(array $input): array
{
    $plan = strtolower(trim((string) ($input['plan'] ?? '')));
    // Legacy clients / old home default sent "pro"; N-Genius checkout only prices gold & platinum.
    if ($plan === 'pro') {
        $plan = 'gold';
    }
    $years = (int) ($input['years'] ?? 1);
    if ($years < 1) {
        $years = 1;
    }

    $priceTable = [
        'gold' => [1 => 550.0, 2 => 1000.0],
        'platinum' => [1 => 600.0, 2 => 1100.0],
    ];

    if (!isset($priceTable[$plan])) {
        return [
            'ok' => false,
            'minor' => 0,
            'subtotal' => 0.0,
            'total' => 0.0,
            'plan' => $plan,
            'years' => $years,
            'checkout_currency' => strtoupper(trim((string) ratib_ngenius_env('NGENIUS_CHECKOUT_CURRENCY', 'SAR'))) ?: 'SAR',
            'usd_to_sar_rate' => null,
            'message' => 'Please choose Gold or Platinum before payment.',
        ];
    }

    if (!isset($priceTable[$plan][$years])) {
        return [
            'ok' => false,
            'minor' => 0,
            'subtotal' => 0.0,
            'total' => 0.0,
            'plan' => $plan,
            'years' => $years,
            'checkout_currency' => strtoupper(trim((string) ratib_ngenius_env('NGENIUS_CHECKOUT_CURRENCY', 'SAR'))) ?: 'SAR',
            'usd_to_sar_rate' => null,
            'message' => 'Unsupported plan duration selected.',
        ];
    }

    $subtotal = (float) $priceTable[$plan][$years];
    $taxAmount = round($subtotal * TAX_RATE, 2);
    $total = round($subtotal + $taxAmount, 2);
    $gw = ratib_ngenius_minor_units_from_usd_total($total);

    return [
        'ok' => true,
        'minor' => (int) $gw['minor'],
        'subtotal' => $subtotal,
        'tax_amount' => $taxAmount,
        'total' => $total,
        'plan' => $plan,
        'years' => $years,
        'checkout_currency' => (string) $gw['currency'],
        'usd_to_sar_rate' => isset($gw['usd_to_sar']) ? (float) $gw['usd_to_sar'] : null,
    ];
}

function saveNgeniusReference($pdo, int $orderId, string $reference): void
{
    $t = RATIB_NGENIUS_ORDERS_TABLE;
    $stmt = $pdo->prepare("UPDATE `{$t}` SET ngenius_order_id = :ref WHERE id = :id");
    $stmt->bindValue(':ref', substr($reference, 0, 128), PDO::PARAM_STR);
    $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
}

if (!function_exists('curl_init')) {
    jsonOut(500, ['message' => 'Payment unavailable: PHP curl extension is not enabled on this server.']);
}

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
    $diag = [
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
        'backend_release' => 'create-order-missing-config-diag-v1',
    ];
    paymentLog('Missing N-Genius credentials', [
        'has_key' => $diag['has_key'],
        'has_secret' => $diag['has_secret'],
        'has_outlet' => $diag['has_outlet'],
        'dotenv_project_root' => $diag['dotenv_project_root'],
        'dotenv_document_root' => $diag['dotenv_document_root'],
        'secrets_config_readable' => $diag['secrets_config_readable'],
        'secrets_env_readable' => $diag['secrets_env_readable'],
        'defined_outlet' => $diag['defined_outlet'],
        'defined_key' => $diag['defined_key'],
        'defined_secret' => $diag['defined_secret'],
    ]);
    jsonOut(503, [
        'message' => 'Payment is not configured. Add NGENIUS_OUTLET_ID and NGENIUS_API_KEY to .env (same folder as api/), or config/ngenius.secrets.php, or define() in config/env/out_ratib_sa.php. NGENIUS_API_SECRET is optional if the portal gives a single key. See logs/payment.log.',
        'config_diagnostics' => $diag,
    ]);
}

$ngeniusRealm = trim((string) ratib_ngenius_env('NGENIUS_REALM', 'networkinternational'));
if ($ngeniusRealm === '') {
    $ngeniusRealm = 'networkinternational';
}

$input = readInput();
$amountResolved = resolvePlanAmount($input);
if (!$amountResolved['ok']) {
    jsonOut(400, ['message' => (string) ($amountResolved['message'] ?? 'Invalid plan amount.')]);
}

paymentLog('create-order config', [
    'identity_base' => $identityBase,
    'order_base' => $orderBase,
    'realm' => $ngeniusRealm,
    'token_url' => $tokenUrl !== '' ? $tokenUrl : ($identityBase . '/identity/auth/access-token'),
    'outlet_id_set' => $outletId !== '',
]);
paymentLog('create-order amount', [
    'plan' => (string) $amountResolved['plan'],
    'years' => (int) $amountResolved['years'],
    'subtotal' => (float) $amountResolved['subtotal'],
    'total' => (float) $amountResolved['total'],
    'tax_amount' => (float) ($amountResolved['tax_amount'] ?? 0.0),
    'checkout_currency' => (string) ($amountResolved['checkout_currency'] ?? ''),
    'usd_to_sar_rate' => $amountResolved['usd_to_sar_rate'] ?? null,
    'minor' => (int) $amountResolved['minor'],
]);

$email = trim((string) ($input['email'] ?? $input['contact_email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonOut(400, ['message' => 'Valid email is required.']);
}

$company = trim((string) ($input['company'] ?? $input['agency_name'] ?? ''));
$phone = trim((string) ($input['phone'] ?? $input['contact_phone'] ?? ''));
$countryName = trim((string) ($input['country_name'] ?? $input['country'] ?? ''));
$siteUrl = trim((string) ($input['desired_site_url'] ?? ''));

if ($countryName === '') {
    jsonOut(400, ['message' => 'Country is required.']);
}
if ($phone === '') {
    jsonOut(400, ['message' => 'Contact phone is required.']);
}
if ($siteUrl === '') {
    jsonOut(400, ['message' => 'Desired site URL is required.']);
}
if (!preg_match('/^https?:\/\/.+/i', $siteUrl)) {
    jsonOut(400, ['message' => 'Desired site URL must start with http:// or https://']);
}

try {
    $pdo = paymentPdo();
    payment_ensure_ngenius_tables($pdo);
    $duplicatePendingId = findRecentDuplicatePendingOrderId(
        $pdo,
        $email
    );
    if ($duplicatePendingId > 0) {
        paymentLog('create-order duplicate pending blocked', [
            'duplicate_order_id' => $duplicatePendingId,
            'email' => $email,
            'plan' => (string) $amountResolved['plan'],
            'years' => (int) $amountResolved['years'],
            'total' => (float) $amountResolved['total'],
            'window_seconds' => RATIB_CREATE_ORDER_DEDUPE_WINDOW_SECONDS,
        ]);
        jsonOut(429, [
            'message' => 'A pending checkout already exists for this registration. Please complete it or wait before trying again.',
            'phase' => 'dedupe',
            'duplicate_order_id' => $duplicatePendingId,
            'retry_after_seconds' => RATIB_CREATE_ORDER_DEDUPE_WINDOW_SECONDS,
        ]);
    }
    // Do not create control registration rows before payment confirmation.
    // Queue insertion is handled by verify.php only after status is "paid".
    $controlRequestId = null;
    $localOrderId = insertPendingOrder(
        $pdo,
        $email,
        (int) $amountResolved['minor'],
        (string) $amountResolved['plan'],
        (int) $amountResolved['years'],
        (float) $amountResolved['subtotal'],
        (float) ($amountResolved['tax_amount'] ?? 0.0),
        (float) $amountResolved['total'],
        null,
        $input
    );
    paymentLog('create-order queued local order only (awaiting paid verify)', ['order_id' => $localOrderId]);
} catch (Throwable $e) {
    paymentLog('DB insert error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    $dbErr = $e->getMessage();
    if (preg_match('/pdo_mysql|could not find driver|class [\'"]?pdo[\'"]? not found|pdo and pdo_mysql are required/i', $dbErr)) {
        jsonOut(500, ['message' => 'Database driver unavailable: enable PHP extensions pdo and pdo_mysql on this server.']);
    }
    $hint = 'Open DevTools → Network → create-order.php → Response, and check logs/payment.log on the server.';
    if (stripos($dbErr, 'Unknown column') !== false) {
        $hint = 'Database table ngenius_reg_orders is missing columns. Deploy includes/payment_orders_schema.php or run the ngenius migration SQL.';
    } elseif (stripos($dbErr, 'LIMIT') !== false || stripos($dbErr, 'HY093') !== false || stripos($dbErr, 'Invalid parameter') !== false) {
        $hint = 'If this mentions LIMIT or parameters, ensure api/create-order.php includes the native-PDO LIMIT fix (deploy latest create-order.php).';
    }
    $detail = substr(preg_replace('/\s+/', ' ', $dbErr), 0, 220);
    $payload = [
        'message' => 'Could not record order. Check DB credentials and logs/payment.log.',
        'backend_release' => RATIB_CREATE_ORDER_RELEASE,
        'hint' => $hint,
        'detail' => $detail,
    ];
    if (getenv('RATIB_PAYMENT_DEBUG') === '1'
        || (isset($_SERVER['RATIB_PAYMENT_DEBUG']) && (string) $_SERVER['RATIB_PAYMENT_DEBUG'] === '1')) {
        $payload['error'] = $dbErr;
        $payload['origin'] = basename($e->getFile()) . ':' . (string) $e->getLine();
    }
    jsonOut(500, $payload);
}

try {
    $baseUrl = inferBaseUrl();
    $redirectBase = (string) ratib_ngenius_env('NGENIUS_REDIRECT_URL', $baseUrl . '/api/verify.php');
    $cancelBase = (string) ratib_ngenius_env('NGENIUS_CANCEL_URL', $baseUrl . '/api/verify.php');
    $redirectUrl = appendOrderId($redirectBase, $localOrderId);
    $cancelUrl = appendOrderId($cancelBase, $localOrderId);

    $merchantRef = 'RATIB-' . $localOrderId;
    if ($company !== '') {
        $merchantRef .= '-' . substr((string) preg_replace('/[^A-Za-z0-9]/', '', $company), 0, 20);
    }
    if ($phone !== '') {
        $merchantRef .= '-P' . substr((string) preg_replace('/[^0-9+]/', '', $phone), 0, 12);
    }
    $merchantRef = substr($merchantRef, 0, 200);

    $tokenRes = ngenius_fetch_access_token($identityBase, $apiKey, $apiSecret, $tokenUrl === '' ? null : $tokenUrl, $ngeniusRealm);
    paymentLog('Token response', [
        'status' => $tokenRes['http_status'],
        'curl_error' => $tokenRes['curl_error'],
        'body' => $tokenRes['body'],
    ]);

    if ($tokenRes['curl_error'] !== '' || $tokenRes['http_status'] < 200 || $tokenRes['http_status'] >= 300 || !$tokenRes['ok']) {
        $resolvedTokenUrl = $tokenUrl !== '' ? $tokenUrl : ($identityBase . '/identity/auth/access-token');
        $credentialHint = function_exists('ngenius_api_key_shape')
            ? ngenius_api_key_shape($apiKey)
            : ['length' => strlen($apiKey)];
        jsonOut(502, array_merge(
            ngenius_token_failure_client_payload($tokenRes),
            [
                'payment_config' => [
                    'token_url' => $resolvedTokenUrl,
                    'identity_base' => $identityBase,
                    'order_base' => $orderBase,
                    'realm' => $ngeniusRealm,
                    'api_key_length' => strlen($apiKey),
                    'credential_hint' => $credentialHint,
                    'backend_release' => 'create-order-credential-hint-v1',
                ],
            ]
        ));
    }

    $accessToken = $tokenRes['access_token'];

    $orderHeaders = [
        'Accept: application/vnd.ni-payment.v2+json',
        'Content-Type: application/vnd.ni-payment.v2+json',
        'Authorization: Bearer ' . $accessToken,
    ];
    $orderPayload = [
        // Live outlet currently does not allow SALE (saleNotAllowed); use PURCHASE for hosted checkout.
        'action' => 'PURCHASE',
        'amount' => [
            'currencyCode' => (string) ($amountResolved['checkout_currency'] ?? 'SAR'),
            'value' => (int) $amountResolved['minor'],
        ],
        'emailAddress' => $email,
        'merchantOrderReference' => $merchantRef,
        'merchantAttributes' => [
            'redirectUrl' => $redirectUrl,
            'cancelUrl' => $cancelUrl,
            'skipConfirmationPage' => true,
        ],
    ];
    $orderJson = json_encode($orderPayload, JSON_UNESCAPED_SLASHES);
    if ($orderJson === false) {
        paymentLog('Order payload json_encode failed', ['order_id' => $localOrderId]);
        jsonOut(500, ['message' => 'Could not build payment request.']);
    }

    $orderRes = ngenius_http_request(
        'POST',
        $orderBase . '/transactions/outlets/' . rawurlencode($outletId) . '/orders',
        $orderHeaders,
        $orderJson
    );
    paymentLog('Order response', ['status' => $orderRes['status'], 'body' => $orderRes['body'], 'order_id' => $localOrderId]);

    if ($orderRes['error'] !== '' || $orderRes['status'] < 200 || $orderRes['status'] >= 300) {
        $orderHint = '';
        if (function_exists('ngenius_identity_response_hint')) {
            $orderHint = ngenius_identity_response_hint((string) ($orderRes['body'] ?? ''));
        }
        jsonOut(502, [
            'message' => 'Failed to create payment order.',
            'order_http_status' => (int) ($orderRes['status'] ?? 0),
            'order_curl_error' => (string) ($orderRes['error'] ?? ''),
            'order_error_hint' => $orderHint,
            'order_error_body' => substr((string) ($orderRes['body'] ?? ''), 0, 800),
            'payment_config' => [
                'order_base' => $orderBase,
                'realm' => $ngeniusRealm,
                'outlet_id_length' => strlen($outletId),
                'backend_release' => 'create-order-live-order-diag-v1',
            ],
            'amount_meta' => [
                'plan' => (string) $amountResolved['plan'],
                'years' => (int) $amountResolved['years'],
                'subtotal' => (float) $amountResolved['subtotal'],
                'tax_amount' => (float) ($amountResolved['tax_amount'] ?? 0.0),
                'total' => (float) $amountResolved['total'],
                'checkout_currency' => (string) ($amountResolved['checkout_currency'] ?? 'SAR'),
                'usd_to_sar_rate' => $amountResolved['usd_to_sar_rate'] ?? null,
                'minor' => (int) $amountResolved['minor'],
            ],
        ]);
    }

    $orderData = json_decode($orderRes['body'], true);
    $paymentUrl = '';
    if (is_array($orderData)) {
        $links = $orderData['_links'] ?? null;
        $payLink = is_array($links) && isset($links['payment']) && is_array($links['payment'])
            ? $links['payment']
            : null;
        $paymentUrl = is_array($payLink) ? (string) ($payLink['href'] ?? '') : '';
    }
    $ngeniusRef = is_array($orderData) ? (string) ($orderData['reference'] ?? '') : '';

    if ($paymentUrl === '') {
        jsonOut(502, ['message' => 'Payment URL missing in gateway response.']);
    }

    try {
        if ($ngeniusRef !== '') {
            saveNgeniusReference($pdo, $localOrderId, $ngeniusRef);
        }
    } catch (Throwable $e) {
        paymentLog('DB save ngenius ref error', ['order_id' => $localOrderId, 'error' => $e->getMessage()]);
    }

    jsonOut(200, [
        'payment_url' => $paymentUrl,
        'control_request_id' => isset($controlRequestId) ? (int) $controlRequestId : 0,
        'amount_meta' => [
            'plan' => (string) $amountResolved['plan'],
            'years' => (int) $amountResolved['years'],
            'subtotal' => (float) $amountResolved['subtotal'],
            'tax_amount' => (float) ($amountResolved['tax_amount'] ?? 0.0),
            'total' => (float) $amountResolved['total'],
            'checkout_currency' => (string) ($amountResolved['checkout_currency'] ?? 'SAR'),
            'usd_to_sar_rate' => $amountResolved['usd_to_sar_rate'] ?? null,
            'minor' => (int) $amountResolved['minor'],
        ],
    ]);
} catch (Throwable $gw) {
    paymentLog('create-order gateway phase failed', [
        'error' => $gw->getMessage(),
        'file' => $gw->getFile(),
        'line' => $gw->getLine(),
        'local_order_id' => $localOrderId,
    ]);
    $gd = substr(preg_replace('/\s+/', ' ', $gw->getMessage()), 0, 220);
    jsonOut(500, [
        'message' => 'Payment gateway step failed after the order row was stored. See logs/payment.log.',
        'detail' => $gd,
        'phase' => 'gateway',
        'local_order_id' => $localOrderId,
        'hint' => 'If local_order_id > 0, the row exists in ngenius_reg_orders; fix gateway/config then retry or mark paid manually.',
    ]);
}
