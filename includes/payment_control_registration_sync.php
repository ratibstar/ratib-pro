<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/payment_control_registration_sync.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/payment_control_registration_sync.php`.
 */

declare(strict_types=1);

/**
 * mysqli for control_registration_requests from payment APIs (create-order / verify).
 * payment_api_bootstrap does not set $GLOBALS['conn'], so we must open mysqli explicitly.
 *
 * Order: reuse control_conn if present → connect to dedicated CONTROL_PANEL_DB_NAME when
 * it differs from DB_NAME → else connect to DB_NAME (shared DB installs).
 */
function payment_control_panel_mysqli(): ?mysqli
{
    $globalCtrl = $GLOBALS['control_conn'] ?? null;
    if ($globalCtrl instanceof mysqli && !$globalCtrl->connect_error) {
        return $globalCtrl;
    }

    if (!defined('DB_HOST') || !defined('DB_USER')) {
        return null;
    }

    $dbToOpen = payment_control_database_name();
    if ($dbToOpen !== false && $dbToOpen !== '') {
        try {
            $conn = @new mysqli(
                DB_HOST,
                DB_USER,
                defined('DB_PASS') ? DB_PASS : '',
                $dbToOpen,
                defined('DB_PORT') ? (int) DB_PORT : 3306
            );
            if ($conn && !$conn->connect_error) {
                $conn->set_charset('utf8mb4');
                return $conn;
            }
        } catch (Throwable $e) {
            // fall through
        }
    }

    $globalMain = $GLOBALS['conn'] ?? null;
    if ($globalMain instanceof mysqli && !$globalMain->connect_error) {
        return $globalMain;
    }

    return null;
}

/**
 * Which MySQL schema holds control_registration_requests for this install.
 *
 * @return non-empty-string|false
 */
function payment_control_database_name()
{
    $mainDb = defined('DB_NAME') ? (string) DB_NAME : '';
    $ctrlDb = defined('CONTROL_PANEL_DB_NAME') ? (string) CONTROL_PANEL_DB_NAME : '';
    if ($ctrlDb !== '' && $mainDb !== '' && $ctrlDb !== $mainDb) {
        return $ctrlDb;
    }
    if ($mainDb !== '') {
        return $mainDb;
    }
    if ($ctrlDb !== '') {
        return $ctrlDb;
    }
    return false;
}

/**
 * PDO to the same DB as payment_control_panel_mysqli (no mysqli bind_param quirks).
 */
function payment_control_pdo(): ?PDO
{
    if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
        return null;
    }
    if (!defined('DB_HOST') || !defined('DB_USER')) {
        return null;
    }
    $db = payment_control_database_name();
    if ($db === false || $db === '') {
        return null;
    }
    $host = (string) DB_HOST;
    $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
    $user = (string) DB_USER;
    $pass = defined('DB_PASS') ? (string) DB_PASS : '';
    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $db . ';charset=utf8mb4';
    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        if (function_exists('paymentLog')) {
            paymentLog('payment_control_pdo failed', ['error' => $e->getMessage()]);
        }
        return null;
    }
}

/**
 * @param array<string,mixed> $input
 * @param array<string,mixed> $amountResolved
 * @throws PDOException
 */
function payment_insert_control_registration_via_pdo(PDO $pdo, array $input, array $amountResolved): int
{
    $t = $pdo->query("SHOW TABLES LIKE 'control_registration_requests'");
    if (!$t || count($t->fetchAll()) === 0) {
        return 0;
    }

    $hasCol = static function (PDO $pdoConn, string $c): bool {
        $q = $pdoConn->query('SHOW COLUMNS FROM control_registration_requests LIKE ' . $pdoConn->quote($c));

        return $q && count($q->fetchAll()) > 0;
    };

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

    $cols = ['agency_name'];
    $params = ['agency_name' => substr($agencyForRow, 0, 255)];
    if ($hasCol($pdo, 'agency_id')) {
        $cols[] = 'agency_id';
        $params['agency_id'] = trim((string) ($input['agency_id'] ?? ''));
    }
    $cols = array_merge($cols, ['country_id', 'country_name', 'contact_email', 'contact_phone', 'desired_site_url', 'notes', 'plan', 'ip_address', 'user_agent']);
    $params['country_id'] = $countryId;
    $params['country_name'] = $countryName;
    $params['contact_email'] = $contactEmail;
    $params['contact_phone'] = $contactPhone;
    $params['desired_site_url'] = $desiredSiteUrl;
    $params['notes'] = $notes;
    $params['plan'] = $plan;
    $params['ip_address'] = $ip;
    $params['user_agent'] = $userAgent;

    if ($hasCol($pdo, 'plan_amount')) {
        $cols[] = 'plan_amount';
        $params['plan_amount'] = $planAmount;
    }
    if ($hasCol($pdo, 'years')) {
        $cols[] = 'years';
        $params['years'] = $years;
    }
    if ($hasCol($pdo, 'payment_status')) {
        $cols[] = 'payment_status';
        $params['payment_status'] = 'pending';
    }
    if ($hasCol($pdo, 'payment_method')) {
        $cols[] = 'payment_method';
        $params['payment_method'] = 'register';
    }

    $quotedCols = array_map(static fn (string $c): string => '`' . str_replace('`', '', $c) . '`', $cols);
    $ph = array_map(static fn (string $c): string => ':' . $c, $cols);
    $sql = 'INSERT INTO control_registration_requests (' . implode(', ', $quotedCols) . ') VALUES (' . implode(', ', $ph) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array<string,mixed> $snap
 * @return array<string,mixed>
 */
function payment_enrich_snapshot_with_pdo_country_id(?PDO $pdo, array $snap): array
{
    if (!$pdo instanceof PDO) {
        return $snap;
    }
    $cid = (int) ($snap['reg_country_id'] ?? 0);
    $name = trim((string) ($snap['reg_country_name'] ?? ''));
    if ($cid > 0 || $name === '') {
        return $snap;
    }
    $t = $pdo->query("SHOW TABLES LIKE 'control_countries'");
    if (!$t || count($t->fetchAll()) === 0) {
        return $snap;
    }
    $st = $pdo->prepare('SELECT id FROM control_countries WHERE name = :n AND is_active = 1 LIMIT 1');
    $st->execute(['n' => $name]);
    $row = $st->fetch();
    if ($row && (int) ($row['id'] ?? 0) > 0) {
        $snap['reg_country_id'] = (int) $row['id'];
    }
    return $snap;
}

function payment_sync_control_row_via_pdo(PDO $pdo, int $controlRequestId, array $order, ?string $notesCell): void
{
    $t = $pdo->query("SHOW TABLES LIKE 'control_registration_requests'");
    if (!$t || count($t->fetchAll()) === 0) {
        return;
    }

    $col = static function (PDO $pdoConn, string $name): bool {
        $q = $pdoConn->query("SHOW COLUMNS FROM control_registration_requests LIKE " . $pdoConn->quote($name));

        return $q && count($q->fetchAll()) > 0;
    };

    $hasPlanAmount = $col($pdo, 'plan_amount');
    $hasYears = $col($pdo, 'years');
    $hasAgencyIdUser = $col($pdo, 'agency_id');

    $okPlan = strtolower(trim((string) ($order['plan_key'] ?? '')));
    $planVal = substr($okPlan !== '' ? $okPlan : 'pro', 0, 32);
    $totalVal = (float) ($order['total_amount'] ?? 0.0);
    $yearsVal = max(1, (int) ($order['years'] ?? 1));

    $a = trim((string) ($order['reg_agency_name'] ?? ''));
    $agencyVal = $a !== '' ? $a : ('N-Genius ' . strtoupper($okPlan !== '' ? $okPlan : 'PLAN'));
    $aid = trim((string) ($order['reg_agency_id'] ?? ''));
    $cn = trim((string) ($order['reg_country_name'] ?? ''));
    $cid = (int) ($order['reg_country_id'] ?? 0);
    $ph = trim((string) ($order['reg_contact_phone'] ?? ''));
    $site = trim((string) ($order['reg_desired_site_url'] ?? ''));
    $email = trim((string) ($order['email'] ?? ''));

    $parts = ['agency_name = :agency_name', 'plan = :plan'];
    $params = [
        'agency_name' => substr($agencyVal, 0, 255),
        'plan' => $planVal,
    ];

    if ($hasAgencyIdUser && $aid !== '') {
        $parts[] = 'agency_id = :agency_id';
        $params['agency_id'] = substr($aid, 0, 64);
    }
    if ($cid > 0) {
        $parts[] = 'country_id = :country_id';
        $params['country_id'] = $cid;
    }
    if ($cn !== '') {
        $parts[] = 'country_name = :country_name';
        $params['country_name'] = substr($cn, 0, 255);
    }
    if ($email !== '') {
        $parts[] = 'contact_email = :contact_email';
        $params['contact_email'] = substr($email, 0, 255);
    }
    if ($ph !== '') {
        $parts[] = 'contact_phone = :contact_phone';
        $params['contact_phone'] = substr($ph, 0, 64);
    }
    if ($site !== '') {
        $parts[] = 'desired_site_url = :desired_site_url';
        $params['desired_site_url'] = substr($site, 0, 512);
    }
    if ($hasPlanAmount) {
        $parts[] = 'plan_amount = :plan_amount';
        $params['plan_amount'] = $totalVal;
    }
    if ($hasYears) {
        $parts[] = 'years = :years';
        $params['years'] = $yearsVal;
    }
    if ($notesCell !== null) {
        $parts[] = 'notes = :notes';
        $params['notes'] = $notesCell;
    }

    $parts[] = 'updated_at = NOW()';
    $params['id'] = $controlRequestId;

    $sql = 'UPDATE control_registration_requests SET ' . implode(', ', $parts) . ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if (function_exists('paymentLog') && (int) $stmt->rowCount() < 1) {
        paymentLog('payment_sync PDO no rows matched', ['control_request_id' => $controlRequestId]);
    }
}

/**
 * mysqli_stmt::bind_param requires arguments by reference. Spreading a plain array
 * (e.g. bind_param($types, ...$bind)) often fails on PHP; registration-request.php uses
 * call_user_func_array with references — same here.
 *
 * @param list<mixed> $bind
 */
function payment_mysqli_stmt_bind_param_safe(mysqli_stmt $stmt, string $types, array $bind): bool
{
    if (strlen($types) !== count($bind)) {
        return false;
    }
    $params = [$types];
    foreach (array_keys($bind) as $i) {
        $params[] = &$bind[$i];
    }
    return call_user_func_array([$stmt, 'bind_param'], $params);
}

/**
 * Match api/registration-request.php: resolve country_id from control_countries by name.
 */
function payment_resolve_country_id_from_control_db(?mysqli $conn, string $countryName): int
{
    if (!$conn instanceof mysqli || $conn->connect_error) {
        return 0;
    }
    $countryName = trim($countryName);
    if ($countryName === '') {
        return 0;
    }
    $chk = $conn->query("SHOW TABLES LIKE 'control_countries'");
    if (!$chk || $chk->num_rows === 0) {
        return 0;
    }
    $esc = $conn->real_escape_string($countryName);
    $r = $conn->query("SELECT id FROM control_countries WHERE name = '{$esc}' AND is_active = 1 LIMIT 1");
    if ($r && ($row = $r->fetch_assoc())) {
        return (int) ($row['id'] ?? 0);
    }
    return 0;
}

/**
 * @param array<string,mixed> $snap
 * @return array<string,mixed>
 */
function payment_enrich_snapshot_with_country_id(?mysqli $conn, array $snap): array
{
    $cid = (int) ($snap['reg_country_id'] ?? 0);
    $name = trim((string) ($snap['reg_country_name'] ?? ''));
    if ($cid > 0 || $name === '') {
        return $snap;
    }
    $resolved = payment_resolve_country_id_from_control_db($conn, $name);
    if ($resolved > 0) {
        $snap['reg_country_id'] = $resolved;
    }
    return $snap;
}

/**
 * @param array<string,mixed> $order
 * @return array<string,mixed>
 */
function payment_enrich_order_row_country_id(?mysqli $conn, array $order): array
{
    $cid = (int) ($order['reg_country_id'] ?? 0);
    $name = trim((string) ($order['reg_country_name'] ?? ''));
    if ($cid > 0 || $name === '') {
        return $order;
    }
    $resolved = payment_resolve_country_id_from_control_db($conn, $name);
    if ($resolved > 0) {
        $order['reg_country_id'] = $resolved;
    }
    return $order;
}

/**
 * Copy N-Genius order snapshot → control_registration_requests row (control_request_id).
 * Used from api/create-order.php (after local order id exists) and api/verify.php.
 *
 * @param array<string,mixed> $order Keys: email, plan_key, years, total_amount, reg_* (same as ngenius_reg_orders)
 */
function payment_sync_control_row_from_ngenius_order(?mysqli $conn, int $controlRequestId, array $order, ?string $notesCell = null): void
{
    if ($controlRequestId <= 0) {
        return;
    }
    $pdo = payment_control_pdo();
    if ($pdo instanceof PDO) {
        try {
            payment_sync_control_row_via_pdo($pdo, $controlRequestId, $order, $notesCell);
            return;
        } catch (Throwable $e) {
            if (function_exists('paymentLog')) {
                paymentLog('payment_sync PDO failed, trying mysqli', [
                    'error' => $e->getMessage(),
                    'control_request_id' => $controlRequestId,
                ]);
            }
        }
    }
    if (!$conn instanceof mysqli || $conn->connect_error) {
        return;
    }
    $chk = $conn->query("SHOW TABLES LIKE 'control_registration_requests'");
    if (!$chk || $chk->num_rows === 0) {
        return;
    }

    $hasPlanAmount = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'plan_amount'")->num_rows ?? 0) > 0;
    $hasYears = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'years'")->num_rows ?? 0) > 0;
    $hasAgencyIdUser = ($conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'agency_id'")->num_rows ?? 0) > 0;

    $okPlan = strtolower(trim((string) ($order['plan_key'] ?? '')));
    $planVal = substr($okPlan !== '' ? $okPlan : 'pro', 0, 32);
    $totalVal = (float) ($order['total_amount'] ?? 0.0);
    $yearsVal = max(1, (int) ($order['years'] ?? 1));

    $a = trim((string) ($order['reg_agency_name'] ?? ''));
    $agencyVal = $a !== '' ? $a : ('N-Genius ' . strtoupper($okPlan !== '' ? $okPlan : 'PLAN'));
    $aid = trim((string) ($order['reg_agency_id'] ?? ''));
    $cn = trim((string) ($order['reg_country_name'] ?? ''));
    $cid = (int) ($order['reg_country_id'] ?? 0);
    $ph = trim((string) ($order['reg_contact_phone'] ?? ''));
    $site = trim((string) ($order['reg_desired_site_url'] ?? ''));
    $email = trim((string) ($order['email'] ?? ''));

    $set = ['agency_name = ?', 'plan = ?'];
    $types = 'ss';
    $bind = [substr($agencyVal, 0, 255), $planVal];

    if ($hasAgencyIdUser && $aid !== '') {
        $set[] = 'agency_id = ?';
        $types .= 's';
        $bind[] = substr($aid, 0, 64);
    }
    if ($cid > 0) {
        $set[] = 'country_id = ?';
        $types .= 'i';
        $bind[] = $cid;
    }
    if ($cn !== '') {
        $set[] = 'country_name = ?';
        $types .= 's';
        $bind[] = substr($cn, 0, 255);
    }
    if ($email !== '') {
        $set[] = 'contact_email = ?';
        $types .= 's';
        $bind[] = substr($email, 0, 255);
    }
    if ($ph !== '') {
        $set[] = 'contact_phone = ?';
        $types .= 's';
        $bind[] = substr($ph, 0, 64);
    }
    if ($site !== '') {
        $set[] = 'desired_site_url = ?';
        $types .= 's';
        $bind[] = substr($site, 0, 512);
    }

    if ($hasPlanAmount) {
        $set[] = 'plan_amount = ?';
        $types .= 'd';
        $bind[] = $totalVal;
    }
    if ($hasYears) {
        $set[] = 'years = ?';
        $types .= 'i';
        $bind[] = $yearsVal;
    }

    if ($notesCell !== null) {
        $set[] = 'notes = ?';
        $types .= 's';
        $bind[] = $notesCell;
    }

    $set[] = 'updated_at = NOW()';
    $types .= 'i';
    $bind[] = $controlRequestId;

    $sql = 'UPDATE control_registration_requests SET ' . implode(', ', $set) . ' WHERE id = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        if (function_exists('paymentLog')) {
            paymentLog('payment_sync prepare failed', ['error' => (string) $conn->error]);
        }
        return;
    }
    if (!payment_mysqli_stmt_bind_param_safe($stmt, $types, $bind)) {
        if (function_exists('paymentLog')) {
            paymentLog('payment_sync bind_param failed', ['types' => $types]);
        }
        $stmt->close();
        return;
    }
    if (!$stmt->execute()) {
        if (function_exists('paymentLog')) {
            paymentLog('payment_sync execute failed', ['error' => (string) $stmt->error]);
        }
        $stmt->close();
        return;
    }
    $stmt->close();
}

/**
 * @param array<string,mixed> $input
 * @param array<string,mixed> $amountResolved
 * @return array<string,mixed>
 */
function payment_build_ngenius_order_snapshot_for_control_sync(array $input, array $amountResolved): array
{
    $countryId = isset($input['country_id']) && ctype_digit((string) $input['country_id'])
        ? (int) $input['country_id'] : 0;

    return [
        'email' => trim((string) ($input['contact_email'] ?? $input['email'] ?? '')),
        'plan_key' => (string) ($amountResolved['plan'] ?? ''),
        'years' => max(1, (int) ($amountResolved['years'] ?? 1)),
        'total_amount' => (float) ($amountResolved['total'] ?? 0.0),
        'reg_agency_name' => trim((string) ($input['agency_name'] ?? '')),
        'reg_agency_id' => trim((string) ($input['agency_id'] ?? '')),
        'reg_country_id' => $countryId,
        'reg_country_name' => trim((string) ($input['country_name'] ?? $input['country'] ?? '')),
        'reg_contact_phone' => trim((string) ($input['contact_phone'] ?? $input['phone'] ?? '')),
        'reg_desired_site_url' => trim((string) ($input['desired_site_url'] ?? '')),
    ];
}

/**
 * After INSERT into ngenius_reg_orders, re-read that row so control sync matches persisted data.
 */
function payment_fetch_ngenius_order_row_for_control_sync(PDO $mainPdo, int $orderId): ?array
{
    if ($orderId < 1 || !defined('RATIB_NGENIUS_ORDERS_TABLE')) {
        return null;
    }
    $t = RATIB_NGENIUS_ORDERS_TABLE;
    $stmt = $mainPdo->prepare(
        "SELECT email, plan_key, years, total_amount,
                reg_agency_name, reg_agency_id, reg_country_id, reg_country_name,
                reg_contact_phone, reg_desired_site_url
         FROM `{$t}` WHERE id = :id LIMIT 1"
    );
    $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * @param array<string,mixed> $row Row from payment_fetch_ngenius_order_row_for_control_sync
 * @return array<string,mixed>
 */
function payment_order_row_to_control_sync_snapshot(array $row): array
{
    return [
        'email' => trim((string) ($row['email'] ?? '')),
        'plan_key' => (string) ($row['plan_key'] ?? ''),
        'years' => max(1, (int) ($row['years'] ?? 1)),
        'total_amount' => (float) ($row['total_amount'] ?? 0.0),
        'reg_agency_name' => trim((string) ($row['reg_agency_name'] ?? '')),
        'reg_agency_id' => trim((string) ($row['reg_agency_id'] ?? '')),
        'reg_country_id' => (int) ($row['reg_country_id'] ?? 0),
        'reg_country_name' => trim((string) ($row['reg_country_name'] ?? '')),
        'reg_contact_phone' => trim((string) ($row['reg_contact_phone'] ?? '')),
        'reg_desired_site_url' => trim((string) ($row['reg_desired_site_url'] ?? '')),
    ];
}
