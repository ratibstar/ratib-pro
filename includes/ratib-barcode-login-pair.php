<?php
declare(strict_types=1);

/**
 * Temporary tokens for cross-device barcode login (PC waits, phone scans badge).
 * Uses MySQL when available; falls back to writable temp/cache directory.
 */
if (!function_exists('ratib_barcode_pair_db')) {
    function ratib_barcode_pair_db(): ?mysqli
    {
        static $resolved = null;
        if ($resolved instanceof mysqli) {
            return $resolved;
        }
        if (!function_exists('get_control_lookup_conn') && !isset($GLOBALS['conn'])) {
            $cfg = dirname(__DIR__) . '/config/env/load.php';
            if (is_file($cfg)) {
                require_once $cfg;
            }
            if (!isset($GLOBALS['conn']) && defined('DB_HOST') && defined('DB_NAME')) {
                try {
                    $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
                    $GLOBALS['conn'] = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, $port);
                    if ($GLOBALS['conn'] instanceof mysqli) {
                        $GLOBALS['conn']->set_charset('utf8mb4');
                    }
                } catch (Throwable $e) {
                    error_log('ratib_barcode_pair_db connect: ' . $e->getMessage());
                }
            }
        }
        $candidates = [];
        if (function_exists('get_control_lookup_conn')) {
            $lookup = get_control_lookup_conn();
            if ($lookup instanceof mysqli) {
                $candidates[] = $lookup;
            }
        }
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn instanceof mysqli) {
            $candidates[] = $conn;
        }
        foreach ($candidates as $db) {
            if (ratib_barcode_pair_ensure_table($db)) {
                return $resolved = $db;
            }
        }
        return null;
    }
}

if (!function_exists('ratib_barcode_pair_ensure_table')) {
    function ratib_barcode_pair_ensure_table(mysqli $db): bool
    {
        static $readyIds = [];
        $id = function_exists('spl_object_id') ? spl_object_id($db) : spl_object_hash($db);
        if (isset($readyIds[$id])) {
            return true;
        }
        try {
            $chk = $db->query("SHOW TABLES LIKE 'login_barcode_pairs'");
            if ($chk && $chk->num_rows > 0) {
                if ($chk instanceof mysqli_result) {
                    $chk->free();
                }
                $readyIds[$id] = true;
                return true;
            }
            if ($chk instanceof mysqli_result) {
                $chk->free();
            }
            $sql = 'CREATE TABLE IF NOT EXISTS login_barcode_pairs (
                token VARCHAR(32) NOT NULL PRIMARY KEY,
                status VARCHAR(20) NOT NULL DEFAULT \'pending\',
                context_json MEDIUMTEXT NULL,
                session_json MEDIUMTEXT NULL,
                expires_at INT UNSIGNED NOT NULL,
                created_at INT UNSIGNED NOT NULL,
                KEY idx_login_barcode_pairs_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
            if ($db->query($sql)) {
                $readyIds[$id] = true;
                return true;
            }
        } catch (Throwable $e) {
            error_log('ratib_barcode_pair_ensure_table: ' . $e->getMessage());
        }
        return false;
    }
}

if (!function_exists('ratib_barcode_pair_storage_mode')) {
  /**
   * @return 'db'|'file'|null
   */
    function ratib_barcode_pair_storage_mode(): ?string
    {
        static $mode = null;
        if ($mode !== null) {
            return $mode === '' ? null : $mode;
        }
        $dir = ratib_barcode_pair_file_dir();
        if ($dir !== null && is_writable($dir)) {
            $mode = 'file';
            return 'file';
        }
        try {
            $db = ratib_barcode_pair_db();
            if ($db instanceof mysqli && ratib_barcode_pair_ensure_table($db)) {
                $mode = 'db';
                return 'db';
            }
        } catch (Throwable $e) {
            error_log('ratib_barcode_pair_storage_mode db: ' . $e->getMessage());
        }
        $mode = '';
        return null;
    }
}

if (!function_exists('ratib_barcode_pair_file_dir')) {
    function ratib_barcode_pair_file_dir(): ?string
    {
        $tmp = sys_get_temp_dir();
        $candidates = [
            ($tmp !== '' ? $tmp . '/ratib_barcode_login_pairs' : ''),
            dirname(__DIR__) . '/cache/barcode_login_pairs',
        ];
        foreach ($candidates as $dir) {
            if ($dir === '' || $dir === false) {
                continue;
            }
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }
        return null;
    }
}

if (!function_exists('ratib_barcode_pair_path')) {
    function ratib_barcode_pair_path(string $token): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $dir = ratib_barcode_pair_file_dir();
        if ($dir === null) {
            return null;
        }
        return $dir . '/' . $token . '.json';
    }
}

if (!function_exists('ratib_barcode_pair_db_read')) {
    /**
     * @return array<string, mixed>|null
     */
    function ratib_barcode_pair_db_read(mysqli $db, string $token): ?array
    {
        $row = null;
        try {
            $stmt = $db->prepare(
                'SELECT status, context_json, session_json, expires_at FROM login_barcode_pairs WHERE token = ? LIMIT 1'
            );
            if (!$stmt) {
                return null;
            }
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
            $stmt->close();
        } catch (Throwable $e) {
            error_log('ratib_barcode_pair_db_read: ' . $e->getMessage());
            return null;
        }
        if (!$row) {
            return null;
        }
        $expires = (int) ($row['expires_at'] ?? 0);
        if ($expires > 0 && time() > $expires) {
            $del = $db->prepare('DELETE FROM login_barcode_pairs WHERE token = ? LIMIT 1');
            if ($del) {
                $del->bind_param('s', $token);
                $del->execute();
                $del->close();
            }
            return null;
        }
        $ctx = json_decode((string) ($row['context_json'] ?? ''), true);
        $session = json_decode((string) ($row['session_json'] ?? ''), true);
        return [
            'status' => (string) ($row['status'] ?? 'pending'),
            'created' => 0,
            'expires' => $expires,
            'context' => is_array($ctx) ? $ctx : [],
            'session' => is_array($session) ? $session : null,
        ];
    }
}

if (!function_exists('ratib_barcode_pair_db_write')) {
    /**
     * @param array<string, mixed> $data
     */
    function ratib_barcode_pair_db_write(mysqli $db, string $token, array $data): bool
    {
        try {
            $status = (string) ($data['status'] ?? 'pending');
            $contextJson = json_encode($data['context'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $sessionJson = '';
            if (isset($data['session']) && is_array($data['session'])) {
                $encoded = json_encode($data['session'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $sessionJson = $encoded !== false ? $encoded : '';
            }
            $expires = (int) ($data['expires'] ?? (time() + 300));
            $created = (int) ($data['created'] ?? time());
            if ($contextJson === false) {
                return false;
            }
            $stmt = $db->prepare(
                'INSERT INTO login_barcode_pairs (token, status, context_json, session_json, expires_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                 status = VALUES(status),
                 context_json = VALUES(context_json),
                 session_json = VALUES(session_json),
                 expires_at = VALUES(expires_at)'
            );
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('ssssii', $token, $status, $contextJson, $sessionJson, $expires, $created);
            $ok = $stmt->execute();
            $stmt->close();
            return (bool) $ok;
        } catch (Throwable $e) {
            error_log('ratib_barcode_pair_db_write: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('ratib_barcode_pair_read')) {
    /**
     * @return array<string, mixed>|null
     */
    function ratib_barcode_pair_read(string $token): ?array
    {
        $mode = ratib_barcode_pair_storage_mode();
        if ($mode === 'db') {
            $db = ratib_barcode_pair_db();
            if ($db instanceof mysqli) {
                return ratib_barcode_pair_db_read($db, $token);
            }
        }
        $path = ratib_barcode_pair_path($token);
        if ($path === null || !is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        $expires = (int) ($data['expires'] ?? 0);
        if ($expires > 0 && time() > $expires) {
            @unlink($path);
            return null;
        }
        return $data;
    }
}

if (!function_exists('ratib_barcode_pair_write')) {
    /**
     * @param array<string, mixed> $data
     */
    function ratib_barcode_pair_write(string $token, array $data): bool
    {
        $mode = ratib_barcode_pair_storage_mode();
        if ($mode === 'db') {
            $db = ratib_barcode_pair_db();
            if ($db instanceof mysqli) {
                return ratib_barcode_pair_db_write($db, $token, $data);
            }
        }
        $path = ratib_barcode_pair_path($token);
        if ($path === null) {
            return false;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        return @file_put_contents($path, $json, LOCK_EX) !== false;
    }
}

if (!function_exists('ratib_barcode_pair_create')) {
    /**
     * @param array<string, mixed> $context
     * @return array{ok:bool, token?:string, message?:string}
     */
    function ratib_barcode_pair_create(array $context): array
    {
        if (ratib_barcode_pair_storage_mode() === null) {
            return ['ok' => false, 'message' => 'Login session storage is not available on the server.'];
        }
        try {
            $token = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Could not create login session.'];
        }
        $data = [
            'status' => 'pending',
            'created' => time(),
            'expires' => time() + 300,
            'context' => $context,
            'session' => null,
        ];
        if (!ratib_barcode_pair_write($token, $data)) {
            return ['ok' => false, 'message' => 'Could not store login session.'];
        }
        return ['ok' => true, 'token' => $token];
    }
}

if (!function_exists('ratib_barcode_pair_approve')) {
    /**
     * @param array<string, mixed> $sessionPayload
     */
    function ratib_barcode_pair_approve(string $token, array $sessionPayload): bool
    {
        $data = ratib_barcode_pair_read($token);
        if ($data === null || ($data['status'] ?? '') !== 'pending') {
            return false;
        }
        $data['status'] = 'approved';
        $data['session'] = $sessionPayload;
        $data['approved_at'] = time();
        return ratib_barcode_pair_write($token, $data);
    }
}

if (!function_exists('ratib_barcode_pair_poll')) {
    /**
     * @return array{ok:bool, status?:string, message?:string}
     */
    function ratib_barcode_pair_poll(string $token): array
    {
        $data = ratib_barcode_pair_read($token);
        if ($data === null) {
            return ['ok' => false, 'status' => 'expired', 'message' => 'Session expired.'];
        }
        return [
            'ok' => true,
            'status' => (string) ($data['status'] ?? 'pending'),
        ];
    }
}

if (!function_exists('ratib_barcode_pair_consume_session')) {
    /**
     * @return array<string, mixed>|null
     */
    function ratib_barcode_pair_consume_session(string $token): ?array
    {
        $data = ratib_barcode_pair_read($token);
        if ($data === null) {
            return null;
        }
        if (($data['status'] ?? '') !== 'approved' || !is_array($data['session'] ?? null)) {
            return null;
        }
        $session = $data['session'];
        $mode = ratib_barcode_pair_storage_mode();
        if ($mode === 'db') {
            $db = ratib_barcode_pair_db();
            if ($db instanceof mysqli) {
                $del = $db->prepare('DELETE FROM login_barcode_pairs WHERE token = ? LIMIT 1');
                if ($del) {
                    $del->bind_param('s', $token);
                    $del->execute();
                    $del->close();
                }
            }
        } else {
            $path = ratib_barcode_pair_path($token);
            if ($path !== null) {
                @unlink($path);
            }
        }
        return $session;
    }
}
