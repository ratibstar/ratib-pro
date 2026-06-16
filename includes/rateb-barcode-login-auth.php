<?php
declare(strict_types=1);

/**
 * Barcode lookup + session payload for cross-device / API login.
 */
require_once __DIR__ . '/rateb-user-login-barcode.php';

if (!function_exists('rateb_barcode_login_resolve_connection')) {
    /**
     * @param array<string, mixed> $ctx country_id, agency_id
     */
    function rateb_barcode_login_resolve_connection(array $ctx): ?mysqli
    {
        $conn = $GLOBALS['conn'] ?? null;
        if (!($conn instanceof mysqli)) {
            return null;
        }
        $agencyId = (int) ($ctx['agency_id'] ?? 0);
        $countryId = (int) ($ctx['country_id'] ?? 0);
        $countrySlug = trim((string) ($ctx['country_slug'] ?? ''));
        if ($countryId <= 0 && $countrySlug !== '' && function_exists('rateb_qr_login_country_id_from_slug')) {
            $countryId = rateb_qr_login_country_id_from_slug($countrySlug);
        }
        $singleUrlMode = defined('SINGLE_URL_MODE') && SINGLE_URL_MODE;
        if (!$singleUrlMode || ($agencyId <= 0 && $countryId <= 0)) {
            return $conn;
        }
        $lookupConn = (function_exists('get_control_lookup_conn') && get_control_lookup_conn())
            ? get_control_lookup_conn()
            : $conn;
        $chk = @$lookupConn->query("SHOW TABLES LIKE 'control_agencies'");
        if (!$chk || $chk->num_rows === 0) {
            return $conn;
        }
        $susp = function_exists('rateb_control_agency_active_fragment')
            ? rateb_control_agency_active_fragment($lookupConn, 'a')
            : '1=1';
        $rowA = null;
        if ($agencyId > 0) {
            $stmtA = $lookupConn->prepare(
                "SELECT a.id, a.name, a.country_id, a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name "
                . "FROM control_agencies a WHERE a.id = ? AND a.is_active = 1 AND {$susp} LIMIT 1"
            );
            if ($stmtA) {
                $stmtA->bind_param('i', $agencyId);
                $stmtA->execute();
                $resA = $stmtA->get_result();
                $rowA = ($resA && $resA->num_rows > 0) ? $resA->fetch_assoc() : null;
                $stmtA->close();
            }
        } elseif ($countryId > 0) {
            $stmtA = $lookupConn->prepare(
                "SELECT a.id, a.name, a.country_id, a.db_host, a.db_port, a.db_user, a.db_pass, a.db_name "
                . "FROM control_agencies a WHERE a.country_id = ? AND a.is_active = 1 AND {$susp} ORDER BY a.id ASC LIMIT 1"
            );
            if ($stmtA) {
                $stmtA->bind_param('i', $countryId);
                $stmtA->execute();
                $resA = $stmtA->get_result();
                $rowA = ($resA && $resA->num_rows > 0) ? $resA->fetch_assoc() : null;
                $stmtA->close();
            }
        }
        if (!$rowA) {
            return $conn;
        }
        $agencyDbName = trim((string) ($rowA['db_name'] ?? ''));
        $mainDbName = defined('DB_NAME') ? trim((string) DB_NAME) : '';
        if ($agencyDbName !== '' && $agencyDbName === $mainDbName) {
            return $conn;
        }
        $helper = dirname(__DIR__) . '/control-panel/api/control/agency-db-helper.php';
        if (!is_file($helper)) {
            return $conn;
        }
        require_once $helper;
        if (!function_exists('getAgencyDbConnection')) {
            return $conn;
        }
        $cid = (int) ($rowA['country_id'] ?? $countryId);
        $acct = getAgencyDbConnection($rowA, $cid);
        if ($acct && isset($acct['conn']) && $acct['conn'] instanceof mysqli) {
            return $acct['conn'];
        }
        return $conn;
    }
}

if (!function_exists('rateb_barcode_login_build_session')) {
    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>|null
     */
    function rateb_barcode_login_build_session(mysqli $loginConn, array $user, array $ctx): ?array
    {
        if (function_exists('rateb_qr_login_resolve_tenant_names')) {
            $ctx = rateb_qr_login_resolve_tenant_names($ctx);
        }
        $st = strtolower(trim((string) ($user['status'] ?? '')));
        $statusOk = ($st === 'active' || $st === '1' || $st === 'enabled');
        if (!$statusOk && array_key_exists('is_active', $user)) {
            $statusOk = !empty((int) ($user['is_active'] ?? 0));
        }
        if (!$statusOk) {
            return null;
        }
        $uid = (int) ($user['user_id'] ?? $user['id'] ?? 0);
        if ($uid <= 0) {
            return null;
        }
        $username = trim((string) ($user['username'] ?? ''));
        if ($username === '' || strncmp($username, 'Control:', 8) === 0) {
            return null;
        }
        $agencyId = (int) ($ctx['agency_id'] ?? 0);
        if ($agencyId > 0 && isset($user['agency_id']) && (int) $user['agency_id'] > 0) {
            if ((int) $user['agency_id'] !== $agencyId) {
                return null;
            }
        }
        $roleId = (int) ($user['role_id'] ?? 1);
        $roleName = 'User';
        try {
            $rStmt = $loginConn->prepare('SELECT role_name FROM roles WHERE role_id = ? LIMIT 1');
            if ($rStmt) {
                $rStmt->bind_param('i', $roleId);
                $rStmt->execute();
                $rRes = $rStmt->get_result();
                if ($rRes && $rRes->num_rows > 0 && ($rRow = $rRes->fetch_assoc())) {
                    $roleName = trim((string) ($rRow['role_name'] ?? '')) ?: 'User';
                }
                $rStmt->close();
            }
        } catch (Throwable $e) {
            /* ignore */
        }
        $session = [
            'user_id' => $uid,
            'username' => $username,
            'role_id' => $roleId,
            'logged_in' => true,
            'role' => $roleName,
            'country_id' => (int) ($ctx['country_id'] ?? 0) ?: null,
            'country_name' => (string) ($ctx['country_name'] ?? ''),
            'agency_id' => $agencyId > 0 ? $agencyId : null,
            'agency_name' => (string) ($ctx['agency_name'] ?? ''),
        ];
        try {
            $loginConn->query('UPDATE users SET last_login = NOW() WHERE user_id = ' . $uid);
        } catch (Throwable $e) {
            /* ignore */
        }
        $permFile = __DIR__ . '/permissions.php';
        if (is_file($permFile)) {
            require_once $permFile;
            if (function_exists('getUserPermissions')) {
                $_SESSION['user_id'] = $uid;
                $_SESSION['role_id'] = $roleId;
                $_SESSION['logged_in'] = true;
                $session['user_permissions'] = getUserPermissions();
            }
        }
        try {
            $loginPk = function_exists('rateb_users_primary_key_column')
                ? rateb_users_primary_key_column($loginConn)
                : 'user_id';
            $permStmt = $loginConn->prepare("SELECT permissions FROM users WHERE `{$loginPk}` = ? LIMIT 1");
            if ($permStmt) {
                $permStmt->bind_param('i', $uid);
                $permStmt->execute();
                $permResult = $permStmt->get_result();
                if ($permResult && ($permRow = $permResult->fetch_assoc())) {
                    $session['user_specific_permissions'] = !empty($permRow['permissions'])
                        ? json_decode((string) $permRow['permissions'], true)
                        : null;
                }
                $permStmt->close();
            }
        } catch (Throwable $e) {
            $session['user_specific_permissions'] = null;
        }
        return $session;
    }
}

if (!function_exists('rateb_barcode_login_authenticate_legacy')) {
    /**
     * Legacy static barcode column lookup (login_barcode value).
     *
     * @param array<string, mixed> $ctx
     * @return array{ok:bool, session?:array<string,mixed>, message?:string}
     */
    function rateb_barcode_login_authenticate_legacy(string $barcode, array $ctx): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return ['ok' => false, 'message' => 'Barcode is empty.'];
        }
        $loginConn = rateb_barcode_login_resolve_connection($ctx);
        if (!($loginConn instanceof mysqli)) {
            return ['ok' => false, 'message' => 'Database unavailable.'];
        }
        $barcodeCol = rateb_users_login_barcode_column($loginConn);
        if ($barcodeCol === null || $barcodeCol === '') {
            return ['ok' => false, 'message' => 'Barcode login is not configured.'];
        }
        $pk = rateb_users_primary_key_for_barcode($loginConn);
        $stmt = $loginConn->prepare("SELECT * FROM users WHERE `{$barcodeCol}` = ? LIMIT 1");
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('s', $barcode);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$user) {
            return ['ok' => false, 'message' => 'Barcode not recognized.'];
        }
        if (!isset($user['user_id']) && isset($user['id'])) {
            $user['user_id'] = $user['id'];
        }
        $session = rateb_barcode_login_build_session($loginConn, $user, $ctx);
        if ($session === null) {
            return ['ok' => false, 'message' => 'Account inactive or not allowed.'];
        }
        return ['ok' => true, 'session' => $session];
    }
}

if (!function_exists('rateb_barcode_login_authenticate')) {
    /**
     * @param array<string, mixed> $ctx
     * @return array{ok:bool, session?:array<string,mixed>, message?:string, code?:string}
     */
    function rateb_barcode_login_authenticate(string $barcode, array $ctx, ?string $pairToken = null): array
    {
        $qrHelper = __DIR__ . '/rateb-qr-login.php';
        if (is_file($qrHelper)) {
            require_once $qrHelper;
            if (function_exists('rateb_qr_login_authenticate_payload')) {
                return rateb_qr_login_authenticate_payload($barcode, $ctx, $pairToken);
            }
        }
        return rateb_barcode_login_authenticate_legacy($barcode, $ctx);
    }
}
