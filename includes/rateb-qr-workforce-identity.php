<?php
declare(strict_types=1);

/**
 * Workforce QR identity — persistent credentials, PIN, trusted devices, challenges.
 * Extends rateb-qr-login.php; does not replace password/session architecture.
 */
require_once __DIR__ . '/rateb-qr-login.php';

if (!defined('RATEB_QR_TRUST_DAYS')) {
    define('RATEB_QR_TRUST_DAYS', 30);
}

if (!defined('RATEB_QR_PIN_CHALLENGE_TTL')) {
    define('RATEB_QR_PIN_CHALLENGE_TTL', 300);
}

if (!function_exists('rateb_qr_workforce_signing_secret')) {
    function rateb_qr_workforce_signing_secret(): string
    {
        $pepper = defined('DB_PASS') ? (string) DB_PASS : 'rateb';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'rateb');
        return hash('sha256', 'rateb_qr_sign|' . $pepper . '|' . $host);
    }
}

if (!function_exists('rateb_qr_login_build_signed_payload')) {
    /** Build RATEBLOGIN:{64hex}.{8hex_sig} — no user data in QR. */
    function rateb_qr_login_build_signed_payload(string $plainHex): string
    {
        $plainHex = preg_replace('/[^a-f0-9]/', '', strtolower($plainHex));
        $sig = substr(hash_hmac('sha256', $plainHex, rateb_qr_workforce_signing_secret()), 0, 8);
        return RATEB_QR_LOGIN_PREFIX . $plainHex . '.' . $sig;
    }
}

if (!function_exists('rateb_qr_login_verify_signed_plain')) {
    function rateb_qr_login_verify_signed_plain(string $plainHex, string $sig): bool
    {
        $plainHex = preg_replace('/[^a-f0-9]/', '', strtolower($plainHex));
        $sig = preg_replace('/[^a-f0-9]/', '', strtolower($sig));
        if (strlen($plainHex) < 32 || strlen($sig) < 8) {
            return false;
        }
        $expected = substr(hash_hmac('sha256', $plainHex, rateb_qr_workforce_signing_secret()), 0, 8);
        return hash_equals($expected, substr($sig, 0, 8));
    }
}

if (!function_exists('rateb_qr_workforce_ensure_schema')) {
    function rateb_qr_workforce_ensure_schema(mysqli $db): void
    {
        rateb_qr_login_ensure_schema($db);
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
        if (!in_array('qr_login_enabled', $cols, true)) {
            $alters[] = 'ADD COLUMN qr_login_enabled TINYINT(1) NOT NULL DEFAULT 1';
        }
        if (!in_array('qr_last_used_at', $cols, true)) {
            $alters[] = 'ADD COLUMN qr_last_used_at DATETIME NULL DEFAULT NULL';
        }
        if (!in_array('qr_pin_enabled', $cols, true)) {
            $alters[] = 'ADD COLUMN qr_pin_enabled TINYINT(1) NOT NULL DEFAULT 0';
        }
        if (!in_array('qr_pin_hash', $cols, true)) {
            $alters[] = 'ADD COLUMN qr_pin_hash VARCHAR(255) NULL DEFAULT NULL';
        }
        if (!in_array('trusted_device_limit', $cols, true)) {
            $alters[] = 'ADD COLUMN trusted_device_limit INT UNSIGNED NOT NULL DEFAULT 5';
        }
        if ($alters !== []) {
            @$db->query('ALTER TABLE users ' . implode(', ', $alters));
        }
        @$db->query(
            'CREATE TABLE IF NOT EXISTS user_trusted_devices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                device_token_hash VARCHAR(64) NOT NULL,
                device_label VARCHAR(128) NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                expires_at DATETIME NOT NULL,
                last_used_at DATETIME NULL,
                revoked_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_trusted_device (user_id, device_token_hash),
                KEY idx_trusted_user (user_id),
                KEY idx_trusted_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        @$db->query(
            'CREATE TABLE IF NOT EXISTS qr_login_challenges (
                challenge_token VARCHAR(64) NOT NULL PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                pair_token VARCHAR(32) NULL,
                context_json TEXT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_qr_challenge_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $done[$key] = true;
    }
}

if (!function_exists('rateb_qr_workforce_user_row')) {
    function rateb_qr_workforce_user_row(mysqli $db, int $userId): ?array
    {
        rateb_qr_workforce_ensure_schema($db);
        $pk = rateb_users_primary_key_for_barcode($db);
        $stmt = $db->prepare("SELECT * FROM users WHERE `{$pk}` = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }
}

if (!function_exists('rateb_qr_workforce_status')) {
    /**
     * @return array<string, mixed>
     */
    function rateb_qr_workforce_status(mysqli $db, int $userId): array
    {
        $row = rateb_qr_workforce_user_row($db, $userId);
        if (!$row) {
            return ['ok' => false, 'message' => 'User not found.'];
        }
        $pk = rateb_users_primary_key_for_barcode($db);
        $hasToken = !empty($row['qr_login_token']);
        $revoked = !empty($row['qr_token_revoked_at']);
        $expires = (string) ($row['qr_token_expires_at'] ?? '');
        $expTs = $expires !== '' ? strtotime($expires) : 0;
        $expired = $expTs > 0 && time() > $expTs;
        $enabled = !isset($row['qr_login_enabled']) || (int) $row['qr_login_enabled'] === 1;
        $active = $hasToken && !$revoked && !$expired && $enabled;
        $status = 'none';
        if ($revoked) {
            $status = 'revoked';
        } elseif ($hasToken && $expired) {
            $status = 'expired';
        } elseif ($active) {
            $status = 'active';
        } elseif ($hasToken) {
            $status = 'inactive';
        }
        $uid = (int) ($row[$pk] ?? $userId);
        $devices = rateb_qr_trusted_devices_list($db, $uid);
        return [
            'ok' => true,
            'user_id' => $uid,
            'username' => (string) ($row['username'] ?? ''),
            'legacy_ref' => (string) ($row['login_barcode'] ?? ''),
            'qr_status' => $status,
            'qr_login_enabled' => $enabled,
            'qr_pin_enabled' => !empty((int) ($row['qr_pin_enabled'] ?? 0)),
            'has_pin' => !empty($row['qr_pin_hash']),
            'expires_at' => $expires !== '' ? $expires : null,
            'last_used_at' => (string) ($row['qr_last_used_at'] ?? $row['last_qr_scan_at'] ?? '') ?: null,
            'revoked_at' => (string) ($row['qr_token_revoked_at'] ?? '') ?: null,
            'trusted_device_limit' => (int) ($row['trusted_device_limit'] ?? 5),
            'trusted_devices' => $devices,
            'audit_recent' => rateb_qr_workforce_recent_audit($db, $uid, 8),
        ];
    }
}

if (!function_exists('rateb_qr_workforce_recent_audit')) {
    function rateb_qr_workforce_recent_audit(mysqli $db, int $userId, int $limit = 10): array
    {
        rateb_qr_login_ensure_schema($db);
        $limit = max(1, min(50, $limit));
        $stmt = @$db->prepare(
            'SELECT event_type, outcome, ip_address, created_at FROM qr_login_audit
             WHERE user_id = ? ORDER BY id DESC LIMIT ?'
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('rateb_qr_login_ensure_persistent_token')) {
    /**
     * Issue token only when missing/revoked/expired, unless $forceRegenerate.
     *
     * @return array{ok:bool, qr_payload?:string|null, expires_at?:string, regenerated?:bool, message?:string, status?:string}
     */
    function rateb_qr_login_ensure_persistent_token(mysqli $db, int $userId, bool $forceRegenerate = false): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'message' => 'Invalid user.'];
        }
        rateb_qr_workforce_ensure_schema($db);
        $status = rateb_qr_workforce_status($db, $userId);
        if (empty($status['ok'])) {
            return $status;
        }
        if (!$forceRegenerate && ($status['qr_status'] ?? '') === 'active') {
            rateb_qr_login_audit($db, 'token_ensure', 'ok', $userId, null, ['persistent' => true]);
            return [
                'ok' => true,
                'qr_payload' => null,
                'expires_at' => $status['expires_at'],
                'regenerated' => false,
                'status' => 'active',
                'message' => 'Persistent credential already active. Use Regenerate to issue a new QR.',
            ];
        }
        return rateb_qr_login_issue_token($db, $userId, 0, true);
    }
}

if (!function_exists('rateb_qr_login_set_enabled')) {
    function rateb_qr_login_set_enabled(mysqli $db, int $userId, bool $enabled): bool
    {
        rateb_qr_workforce_ensure_schema($db);
        $pk = rateb_users_primary_key_for_barcode($db);
        $val = $enabled ? 1 : 0;
        $stmt = $db->prepare("UPDATE users SET qr_login_enabled = ? WHERE `{$pk}` = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $val, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            rateb_qr_login_audit($db, $enabled ? 'qr_enabled' : 'qr_disabled', 'ok', $userId);
        }
        return $ok;
    }
}

if (!function_exists('rateb_qr_pin_set')) {
    function rateb_qr_pin_set(mysqli $db, int $userId, ?string $pin, bool $enabled): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'message' => 'Invalid user.'];
        }
        rateb_qr_workforce_ensure_schema($db);
        $pk = rateb_users_primary_key_for_barcode($db);
        $pinEnabled = $enabled ? 1 : 0;
        $hash = null;
        if ($enabled) {
            $pin = preg_replace('/\D/', '', (string) $pin);
            if (strlen($pin) !== 4) {
                return ['ok' => false, 'message' => 'PIN must be exactly 4 digits.'];
            }
            $hash = password_hash($pin, PASSWORD_BCRYPT, ['cost' => 10]);
        }
        if ($hash !== null) {
            $stmt = $db->prepare(
                "UPDATE users SET qr_pin_enabled = ?, qr_pin_hash = ? WHERE `{$pk}` = ? LIMIT 1"
            );
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Database error.'];
            }
            $stmt->bind_param('isi', $pinEnabled, $hash, $userId);
        } else {
            $stmt = $db->prepare(
                "UPDATE users SET qr_pin_enabled = ?, qr_pin_hash = NULL WHERE `{$pk}` = ? LIMIT 1"
            );
            if (!$stmt) {
                return ['ok' => false, 'message' => 'Database error.'];
            }
            $stmt->bind_param('ii', $pinEnabled, $userId);
        }
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            rateb_qr_login_audit($db, 'pin_updated', 'ok', $userId, null, ['enabled' => $enabled]);
        }
        return ['ok' => $ok, 'message' => $ok ? 'PIN settings saved.' : 'Failed.'];
    }
}

if (!function_exists('rateb_qr_pin_verify')) {
    function rateb_qr_pin_verify(mysqli $db, int $userId, string $pin): bool
    {
        if (!rateb_qr_login_rate_limit_ok('pin_' . $userId, 8)) {
            return false;
        }
        $row = rateb_qr_workforce_user_row($db, $userId);
        if (!$row || empty($row['qr_pin_hash']) || empty((int) ($row['qr_pin_enabled'] ?? 0))) {
            return false;
        }
        $pin = preg_replace('/\D/', '', $pin);
        if (strlen($pin) !== 4) {
            rateb_qr_login_audit($db, 'pin_fail', 'fail', $userId, null, ['reason' => 'format']);
            return false;
        }
        $ok = password_verify($pin, (string) $row['qr_pin_hash']);
        rateb_qr_login_audit($db, $ok ? 'pin_ok' : 'pin_fail', $ok ? 'ok' : 'fail', $userId);
        return $ok;
    }
}

if (!function_exists('rateb_qr_pin_required_for_user')) {
    function rateb_qr_pin_required_for_user(array $user): bool
    {
        if (empty((int) ($user['qr_pin_enabled'] ?? 0))) {
            return false;
        }
        return !empty($user['qr_pin_hash']);
    }
}

if (!function_exists('rateb_qr_challenge_create')) {
    function rateb_qr_challenge_create(mysqli $db, int $userId, ?string $pairToken, array $ctx): ?string
    {
        rateb_qr_workforce_ensure_schema($db);
        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            return null;
        }
        $expires = date('Y-m-d H:i:s', time() + RATEB_QR_PIN_CHALLENGE_TTL);
        $ctxJson = json_encode($ctx, JSON_UNESCAPED_UNICODE);
        $pairTok = $pairToken ?? '';
        $stmt = $db->prepare(
            'INSERT INTO qr_login_challenges (challenge_token, user_id, pair_token, context_json, expires_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('sisss', $token, $userId, $pairTok, $ctxJson, $expires);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok ? $token : null;
    }
}

if (!function_exists('rateb_qr_challenge_consume')) {
    /**
     * @return array{ok:bool, user_id?:int, pair_token?:string, context?:array, message?:string}
     */
    function rateb_qr_challenge_consume(mysqli $db, string $challengeToken): array
    {
        rateb_qr_workforce_ensure_schema($db);
        $challengeToken = preg_replace('/[^a-f0-9]/', '', strtolower($challengeToken));
        if (strlen($challengeToken) < 32) {
            return ['ok' => false, 'message' => 'Invalid challenge.'];
        }
        $stmt = $db->prepare(
            'SELECT user_id, pair_token, context_json, expires_at FROM qr_login_challenges WHERE challenge_token = ? LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('s', $challengeToken);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return ['ok' => false, 'message' => 'Challenge expired. Scan again.'];
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            @$db->query('DELETE FROM qr_login_challenges WHERE challenge_token = ' . $db->real_escape_string($challengeToken));
            return ['ok' => false, 'message' => 'Challenge expired. Scan again.'];
        }
        $del = $db->prepare('DELETE FROM qr_login_challenges WHERE challenge_token = ? LIMIT 1');
        if ($del) {
            $del->bind_param('s', $challengeToken);
            $del->execute();
            $del->close();
        }
        $ctx = json_decode((string) ($row['context_json'] ?? ''), true);
        return [
            'ok' => true,
            'user_id' => (int) $row['user_id'],
            'pair_token' => (string) ($row['pair_token'] ?? ''),
            'context' => is_array($ctx) ? $ctx : [],
        ];
    }
}

if (!function_exists('rateb_qr_device_fingerprint_hash')) {
    function rateb_qr_device_fingerprint_hash(string $fingerprint, string $deviceToken = ''): string
    {
        $fp = trim($fingerprint);
        $tok = preg_replace('/[^a-f0-9]/', '', strtolower($deviceToken));
        return hash('sha256', 'rateb_dev|' . rateb_qr_workforce_signing_secret() . '|' . $fp . '|' . $tok);
    }
}

if (!function_exists('rateb_qr_trusted_device_register')) {
    function rateb_qr_trusted_device_register(mysqli $db, int $userId, string $deviceTokenPlain, string $label = ''): bool
    {
        rateb_qr_workforce_ensure_schema($db);
        $row = rateb_qr_workforce_user_row($db, $userId);
        if (!$row) {
            return false;
        }
        $limit = max(1, (int) ($row['trusted_device_limit'] ?? 5));
        $devices = rateb_qr_trusted_devices_list($db, $userId, true);
        if (count($devices) >= $limit) {
            rateb_qr_trusted_device_revoke_oldest($db, $userId);
        }
        $hash = rateb_qr_device_fingerprint_hash('', $deviceTokenPlain);
        $meta = rateb_qr_login_client_meta();
        $expires = date('Y-m-d H:i:s', time() + (RATEB_QR_TRUST_DAYS * 86400));
        $label = substr(trim($label), 0, 128);
        $stmt = $db->prepare(
            'INSERT INTO user_trusted_devices (user_id, device_token_hash, device_label, ip_address, user_agent, expires_at, last_used_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at), revoked_at = NULL, last_used_at = NOW(), device_label = VALUES(device_label)'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('isssss', $userId, $hash, $label, $meta['ip'], $meta['ua'], $expires);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            rateb_qr_login_audit($db, 'trusted_device_add', 'ok', $userId, null, ['label' => $label]);
        }
        return $ok;
    }
}

if (!function_exists('rateb_qr_trusted_devices_list')) {
    function rateb_qr_trusted_devices_list(mysqli $db, int $userId, bool $activeOnly = false): array
    {
        rateb_qr_workforce_ensure_schema($db);
        $sql = 'SELECT id, device_label, ip_address, user_agent, expires_at, last_used_at, revoked_at, created_at
                FROM user_trusted_devices WHERE user_id = ?';
        if ($activeOnly) {
            $sql .= ' AND revoked_at IS NULL AND expires_at > NOW()';
        }
        $sql .= ' ORDER BY id DESC LIMIT 20';
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = [
                    'id' => (int) ($r['id'] ?? 0),
                    'label' => (string) ($r['device_label'] ?? ''),
                    'ip' => (string) ($r['ip_address'] ?? ''),
                    'expires_at' => (string) ($r['expires_at'] ?? ''),
                    'last_used_at' => (string) ($r['last_used_at'] ?? ''),
                    'revoked' => !empty($r['revoked_at']),
                    'active' => empty($r['revoked_at']) && strtotime((string) $r['expires_at']) > time(),
                ];
            }
        }
        $stmt->close();
        return $rows;
    }
}

if (!function_exists('rateb_qr_trusted_device_revoke')) {
    function rateb_qr_trusted_device_revoke(mysqli $db, int $userId, int $deviceId): bool
    {
        rateb_qr_workforce_ensure_schema($db);
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare(
            'UPDATE user_trusted_devices SET revoked_at = ? WHERE id = ? AND user_id = ? LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sii', $now, $deviceId, $userId);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        if ($ok) {
            rateb_qr_login_audit($db, 'trusted_device_revoke', 'ok', $userId, null, ['device_id' => $deviceId]);
        }
        return $ok;
    }
}

if (!function_exists('rateb_qr_trusted_device_revoke_oldest')) {
    function rateb_qr_trusted_device_revoke_oldest(mysqli $db, int $userId): void
    {
        $stmt = @$db->prepare(
            'SELECT id FROM user_trusted_devices WHERE user_id = ? AND revoked_at IS NULL ORDER BY last_used_at ASC LIMIT 1'
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row) {
            rateb_qr_trusted_device_revoke($db, $userId, (int) $row['id']);
        }
    }
}

if (!function_exists('rateb_qr_trusted_device_validate_cookie')) {
    /**
     * @return array{ok:bool, user_id?:int, message?:string}
     */
    function rateb_qr_trusted_device_validate_cookie(mysqli $db, string $deviceTokenPlain): array
    {
        $tok = preg_replace('/[^a-f0-9]/', '', strtolower($deviceTokenPlain));
        if (strlen($tok) < 32) {
            return ['ok' => false, 'message' => 'Invalid device token.'];
        }
        rateb_qr_workforce_ensure_schema($db);
        $hash = rateb_qr_device_fingerprint_hash('', $tok);
        $stmt = $db->prepare(
            'SELECT user_id FROM user_trusted_devices
             WHERE device_token_hash = ? AND revoked_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return ['ok' => false, 'message' => 'Device not trusted.'];
        }
        $uid = (int) $row['user_id'];
        $touch = $db->prepare('UPDATE user_trusted_devices SET last_used_at = NOW() WHERE device_token_hash = ? LIMIT 1');
        if ($touch) {
            $touch->bind_param('s', $hash);
            $touch->execute();
            $touch->close();
        }
        rateb_qr_login_audit($db, 'trusted_device_use', 'ok', $uid);
        return ['ok' => true, 'user_id' => $uid];
    }
}

if (!function_exists('rateb_qr_login_apply_session')) {
    /**
     * @param array<string, mixed> $session
     */
    function rateb_qr_login_apply_session(array $session): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (function_exists('session_regenerate_id')) {
            @session_regenerate_id(true);
        }
        foreach ($session as $k => $v) {
            $_SESSION[$k] = $v;
        }
        $_SESSION['logged_in'] = true;
        $_SESSION['login_method'] = 'qr_workforce';
    }
}

if (!function_exists('rateb_qr_set_device_cookie')) {
    function rateb_qr_set_device_cookie(string $deviceTokenPlain): void
    {
        $tok = preg_replace('/[^a-f0-9]/', '', strtolower($deviceTokenPlain));
        if (strlen($tok) < 32) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        setcookie('rateb_device', $tok, [
            'expires' => time() + (RATEB_QR_TRUST_DAYS * 86400),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('rateb_qr_workforce_metrics_snapshot')) {
    /**
     * @return array<string, int>
     */
    function rateb_qr_workforce_metrics_snapshot(mysqli $db): array
    {
        rateb_qr_login_ensure_schema($db);
        $out = [
            'scans_ok_24h' => 0,
            'scans_fail_24h' => 0,
            'pin_fail_24h' => 0,
            'revoked_attempts_24h' => 0,
            'active_credentials' => 0,
        ];
        $since = date('Y-m-d H:i:s', time() - 86400);
        $q = @$db->query(
            "SELECT event_type, outcome, COUNT(*) AS c FROM qr_login_audit
             WHERE created_at >= " . $db->real_escape_string($since) . "
             GROUP BY event_type, outcome"
        );
        if ($q) {
            while ($r = $q->fetch_assoc()) {
                $ev = (string) ($r['event_type'] ?? '');
                $oc = (string) ($r['outcome'] ?? '');
                $c = (int) ($r['c'] ?? 0);
                if ($ev === 'scan_validate' && $oc === 'ok') {
                    $out['scans_ok_24h'] += $c;
                } elseif ($ev === 'scan_validate' && $oc === 'fail') {
                    $out['scans_fail_24h'] += $c;
                } elseif ($ev === 'pin_fail') {
                    $out['pin_fail_24h'] += $c;
                }
            }
        }
        $rev = @$db->query(
            "SELECT COUNT(*) AS c FROM qr_login_audit
             WHERE event_type = 'scan_validate' AND outcome = 'fail'
             AND meta_json LIKE '%revoked%' AND created_at >= " . $db->real_escape_string($since)
        );
        if ($rev && ($row = $rev->fetch_assoc())) {
            $out['revoked_attempts_24h'] = (int) ($row['c'] ?? 0);
        }
        rateb_qr_workforce_ensure_schema($db);
        $act = @$db->query(
            'SELECT COUNT(*) AS c FROM users
             WHERE qr_login_token IS NOT NULL AND qr_token_revoked_at IS NULL
             AND (qr_token_expires_at IS NULL OR qr_token_expires_at > NOW())
             AND qr_login_enabled = 1'
        );
        if ($act && ($row = $act->fetch_assoc())) {
            $out['active_credentials'] = (int) ($row['c'] ?? 0);
        }
        return $out;
    }
}
