<?php
declare(strict_types=1);

/**
 * Enterprise QR login tokens (RATIBLOGIN:…) — validation, issuance, audit, rate limits.
 * Extends existing barcode login; does not replace password/session architecture.
 */
require_once __DIR__ . '/ratib-user-login-barcode.php';
require_once __DIR__ . '/ratib-barcode-login-auth.php'; // legacy + session builder (no circular authenticate)

if (!defined('RATIB_QR_LOGIN_PREFIX')) {
    define('RATIB_QR_LOGIN_PREFIX', 'RATIBLOGIN:');
}

if (!function_exists('ratib_qr_login_token_hash')) {
    function ratib_qr_login_token_hash(string $plainToken): string
    {
        $pepper = defined('DB_PASS') ? (string) DB_PASS : 'ratib';
        return hash('sha256', $pepper . '|' . $plainToken);
    }
}

if (!function_exists('ratib_qr_login_ensure_schema')) {
    function ratib_qr_login_ensure_schema(mysqli $db): void
    {
        static $done = [];
        $key = function_exists('spl_object_id') ? spl_object_id($db) : spl_object_hash($db);
        if (isset($done[$key])) {
            return;
        }
        $cols = [];
        $res = @$db->query('SHOW COLUMNS FROM users');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cols[] = strtolower((string) ($row['Field'] ?? ''));
            }
        }
        $alters = [];
        if (!in_array('qr_login_token', $cols, true)) {
            $alters[] = 'ADD COLUMN qr_login_token VARCHAR(64) NULL DEFAULT NULL';
        }
        if (!in_array('qr_token_expires_at', $cols, true)) {
            $alters[] = 'ADD COLUMN qr_token_expires_at DATETIME NULL DEFAULT NULL';
        }
        if (!in_array('qr_token_revoked_at', $cols, true)) {
            $alters[] = 'ADD COLUMN qr_token_revoked_at DATETIME NULL DEFAULT NULL';
        }
        if (!in_array('last_qr_scan_at', $cols, true)) {
            $alters[] = 'ADD COLUMN last_qr_scan_at DATETIME NULL DEFAULT NULL';
        }
        if ($alters !== []) {
            @$db->query('ALTER TABLE users ' . implode(', ', $alters));
        }
        @$db->query(
            'CREATE TABLE IF NOT EXISTS qr_login_audit (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                event_type VARCHAR(32) NOT NULL,
                outcome VARCHAR(16) NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                pair_token VARCHAR(32) NULL,
                meta_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_qr_audit_user (user_id),
                KEY idx_qr_audit_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $done[$key] = true;
    }
}

if (!function_exists('ratib_qr_login_client_meta')) {
    /**
     * @return array{ip:string, ua:string}
     */
    function ratib_qr_login_client_meta(): array
    {
        $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        return ['ip' => $ip, 'ua' => $ua];
    }
}

if (!function_exists('ratib_qr_login_rate_limit_ok')) {
    function ratib_qr_login_rate_limit_ok(string $bucket, int $maxPerMinute = 30): bool
    {
        $dir = sys_get_temp_dir() . '/ratib_qr_ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $meta = ratib_qr_login_client_meta();
        $key = hash('sha256', $bucket . '|' . $meta['ip']);
        $file = $dir . '/' . $key . '.json';
        $now = time();
        $window = [];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = $raw ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                foreach ($decoded as $ts) {
                    if ($now - (int) $ts < 60) {
                        $window[] = (int) $ts;
                    }
                }
            }
        }
        if (count($window) >= $maxPerMinute) {
            return false;
        }
        $window[] = $now;
        @file_put_contents($file, json_encode($window), LOCK_EX);
        return true;
    }
}

if (!function_exists('ratib_qr_login_audit')) {
    function ratib_qr_login_audit(
        mysqli $db,
        string $eventType,
        string $outcome,
        ?int $userId = null,
        ?string $pairToken = null,
        ?array $meta = null
    ): void {
        ratib_qr_login_ensure_schema($db);
        $m = ratib_qr_login_client_meta();
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $stmt = @$db->prepare(
            'INSERT INTO qr_login_audit (user_id, event_type, outcome, ip_address, user_agent, pair_token, meta_json)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return;
        }
        $uidBind = $userId ?? 0;
        $pairTok = $pairToken ?? '';
        $metaStr = $metaJson ?? '';
        $stmt->bind_param('issssss', $uidBind, $eventType, $outcome, $m['ip'], $m['ua'], $pairTok, $metaStr);
        @$stmt->execute();
        $stmt->close();
        error_log(sprintf(
            'QR_LOGIN_AUDIT event=%s outcome=%s user_id=%s ip=%s',
            $eventType,
            $outcome,
            $userId !== null ? (string) $userId : '-',
            $m['ip']
        ));
    }
}

if (!function_exists('ratib_qr_login_normalize_payload')) {
    /**
     * @return array{type:string, value:string}
     */
    function ratib_qr_login_normalize_payload(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['type' => 'empty', 'value' => ''];
        }
        if (stripos($raw, RATIB_QR_LOGIN_PREFIX) === 0) {
            $token = preg_replace('/[^a-f0-9]/', '', strtolower(substr($raw, strlen(RATIB_QR_LOGIN_PREFIX))));
            return ['type' => 'secure', 'value' => $token];
        }
        if (preg_match('#^https?://#i', $raw) || preg_match('#login[-/]scan|login-scan\.php#i', $raw)) {
            return ['type' => 'pairing_url', 'value' => $raw];
        }
        if (preg_match('/^[Rr]\d{5,}[A-Za-z0-9]{0,8}$/', $raw)) {
            return ['type' => 'legacy', 'value' => $raw];
        }
        return ['type' => 'legacy', 'value' => $raw];
    }
}

if (!function_exists('ratib_qr_login_issue_token')) {
    /**
     * Issue a new secure QR token for a user (invalidates previous by overwrite).
     *
     * @return array{ok:bool, qr_payload?:string, expires_at?:string, message?:string}
     */
    function ratib_qr_login_issue_token(mysqli $db, int $userId, int $ttlSeconds = 31536000): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'message' => 'Invalid user.'];
        }
        ratib_qr_login_ensure_schema($db);
        $pk = ratib_users_primary_key_for_barcode($db);
        try {
            $plain = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Could not generate token.'];
        }
        $hash = ratib_qr_login_token_hash($plain);
        $expires = date('Y-m-d H:i:s', time() + max(300, $ttlSeconds));
        $stmt = $db->prepare(
            "UPDATE users SET qr_login_token = ?, qr_token_expires_at = ?, qr_token_revoked_at = NULL WHERE `{$pk}` = ? LIMIT 1"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('ssi', $hash, $expires, $userId);
        $stmt->execute();
        $stmt->close();
        ratib_qr_login_audit($db, 'token_issued', 'ok', $userId, null, ['expires' => $expires]);
        return [
            'ok' => true,
            'qr_payload' => RATIB_QR_LOGIN_PREFIX . $plain,
            'expires_at' => $expires,
        ];
    }
}

if (!function_exists('ratib_qr_login_revoke_token')) {
    function ratib_qr_login_revoke_token(mysqli $db, int $userId): bool
    {
        ratib_qr_login_ensure_schema($db);
        $pk = ratib_users_primary_key_for_barcode($db);
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare(
            "UPDATE users SET qr_token_revoked_at = ?, qr_login_token = NULL WHERE `{$pk}` = ? LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $now, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            ratib_qr_login_audit($db, 'token_revoked', 'ok', $userId);
        }
        return $ok;
    }
}

if (!function_exists('ratib_qr_login_find_user_by_secure_token')) {
    /**
     * @return array{ok:bool, user?:array<string,mixed>, message?:string, code?:string}
     */
    function ratib_qr_login_find_user_by_secure_token(mysqli $db, string $plainHex): array
    {
        ratib_qr_login_ensure_schema($db);
        $plainHex = preg_replace('/[^a-f0-9]/', '', strtolower($plainHex));
        if (strlen($plainHex) < 32) {
            return ['ok' => false, 'message' => 'Invalid QR code.', 'code' => 'invalid'];
        }
        $hash = ratib_qr_login_token_hash($plainHex);
        $pk = ratib_users_primary_key_for_barcode($db);
        $stmt = $db->prepare(
            "SELECT * FROM users WHERE qr_login_token = ? LIMIT 1"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Database error.', 'code' => 'error'];
        }
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$user) {
            return ['ok' => false, 'message' => 'QR not recognized.', 'code' => 'invalid'];
        }
        if (!empty($user['qr_token_revoked_at'])) {
            return ['ok' => false, 'message' => 'This badge has been revoked.', 'code' => 'revoked'];
        }
        $expires = strtotime((string) ($user['qr_token_expires_at'] ?? ''));
        if ($expires > 0 && time() > $expires) {
            return ['ok' => false, 'message' => 'This QR code has expired.', 'code' => 'expired'];
        }
        $uid = (int) ($user[$pk] ?? $user['user_id'] ?? $user['id'] ?? 0);
        if ($uid > 0) {
            $last = strtotime((string) ($user['last_qr_scan_at'] ?? ''));
            if ($last > 0 && (time() - $last) < 2) {
                return ['ok' => false, 'message' => 'Please wait before scanning again.', 'code' => 'replay'];
            }
        }
        if (!isset($user['user_id'])) {
            $user['user_id'] = $uid;
        }
        return ['ok' => true, 'user' => $user];
    }
}

if (!function_exists('ratib_qr_login_mark_scan')) {
    function ratib_qr_login_mark_scan(mysqli $db, int $userId): void
    {
        $pk = ratib_users_primary_key_for_barcode($db);
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE users SET last_qr_scan_at = ? WHERE `{$pk}` = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('si', $now, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('ratib_qr_login_authenticate_payload')) {
    /**
     * Validate scanned QR/barcode and return session payload (same shape as password login).
     *
     * @param array<string, mixed> $ctx country_id, agency_id, …
     * @return array{ok:bool, session?:array<string,mixed>, message?:string, code?:string}
     */
    function ratib_qr_login_authenticate_payload(string $payload, array $ctx, ?string $pairToken = null): array
    {
        if (!ratib_qr_login_rate_limit_ok('validate', 40)) {
            return ['ok' => false, 'message' => 'Too many attempts. Try again shortly.', 'code' => 'rate_limit'];
        }
        $loginConn = ratib_barcode_login_resolve_connection($ctx);
        if (!($loginConn instanceof mysqli)) {
            return ['ok' => false, 'message' => 'Database unavailable.', 'code' => 'error'];
        }
        $parsed = ratib_qr_login_normalize_payload($payload);
        if ($parsed['type'] === 'empty') {
            return ['ok' => false, 'message' => 'Empty scan.', 'code' => 'invalid'];
        }
        if ($parsed['type'] === 'pairing_url') {
            return [
                'ok' => false,
                'message' => 'That is the computer pairing QR. Scan the employee badge from Users → Barcode instead.',
                'code' => 'pairing_qr',
            ];
        }
        $user = null;
        if ($parsed['type'] === 'secure') {
            $found = ratib_qr_login_find_user_by_secure_token($loginConn, $parsed['value']);
            if (empty($found['ok'])) {
                ratib_qr_login_audit($loginConn, 'scan_validate', 'fail', null, $pairToken, [
                    'code' => $found['code'] ?? 'invalid',
                ]);
                return [
                    'ok' => false,
                    'message' => $found['message'] ?? 'QR not recognized.',
                    'code' => $found['code'] ?? 'invalid',
                ];
            }
            $user = $found['user'];
        } else {
            $legacy = ratib_barcode_login_authenticate_legacy($parsed['value'], $ctx);
            if (empty($legacy['ok'])) {
                ratib_qr_login_audit($loginConn, 'scan_validate', 'fail', null, $pairToken, ['legacy' => true]);
                return [
                    'ok' => false,
                    'message' => $legacy['message'] ?? 'Not recognized.',
                    'code' => 'invalid',
                ];
            }
            return $legacy;
        }
        $session = ratib_barcode_login_build_session($loginConn, $user, $ctx);
        if ($session === null) {
            ratib_qr_login_audit($loginConn, 'scan_validate', 'fail', (int) ($user['user_id'] ?? 0), $pairToken, ['reason' => 'inactive']);
            return ['ok' => false, 'message' => 'Account inactive or not allowed.', 'code' => 'inactive'];
        }
        $uid = (int) ($session['user_id'] ?? 0);
        ratib_qr_login_mark_scan($loginConn, $uid);
        ratib_qr_login_audit($loginConn, 'scan_validate', 'ok', $uid, $pairToken, ['secure' => $parsed['type'] === 'secure']);
        return ['ok' => true, 'session' => $session];
    }
}
