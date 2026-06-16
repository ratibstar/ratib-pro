<?php
declare(strict_types=1);

/**
 * Login barcode column detection, generation, and lookup.
 */
if (!function_exists('rateb_users_login_barcode_column')) {
    /**
     * @return string|null Column name on `users` used for barcode login, or null if unavailable.
     */
    function rateb_users_login_barcode_column(mysqli $db): ?string
    {
        static $cache = [];
        $key = spl_object_id($db);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $candidates = ['login_barcode', 'barcode', 'user_barcode', 'card_number'];
        $cols = [];
        $res = @$db->query('SHOW COLUMNS FROM users');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cols[] = (string) ($row['Field'] ?? '');
            }
        }

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $cols, true)) {
                return $cache[$key] = $candidate;
            }
        }

        if (!in_array('login_barcode', $cols, true)) {
            $tbl = @$db->query("SHOW TABLES LIKE 'users'");
            if ($tbl && $tbl->num_rows > 0) {
                @$db->query('ALTER TABLE users ADD COLUMN login_barcode VARCHAR(64) NULL DEFAULT NULL');
                $res2 = @$db->query('SHOW COLUMNS FROM users');
                $cols = [];
                if ($res2) {
                    while ($row = $res2->fetch_assoc()) {
                        $cols[] = (string) ($row['Field'] ?? '');
                    }
                }
            }
        }

        if (in_array('login_barcode', $cols, true)) {
            return $cache[$key] = 'login_barcode';
        }

        return $cache[$key] = null;
    }
}

if (!function_exists('rateb_users_primary_key_for_barcode')) {
    function rateb_users_primary_key_for_barcode(mysqli $db): string
    {
        $res = @$db->query('SHOW COLUMNS FROM users');
        if ($res) {
            $fields = [];
            while ($row = $res->fetch_assoc()) {
                $fields[] = (string) ($row['Field'] ?? '');
            }
            if (in_array('user_id', $fields, true)) {
                return 'user_id';
            }
            if (in_array('id', $fields, true)) {
                return 'id';
            }
        }
        return 'user_id';
    }
}

if (!function_exists('rateb_generate_login_barcode_value')) {
    function rateb_generate_login_barcode_value(int $userId, string $username = ''): string
    {
        $seed = strtoupper(preg_replace('/[^A-Z0-9]/', '', $username));
        $seed = $seed !== '' ? substr($seed, 0, 4) : 'USR';
        return sprintf('R%06d%s', max(1, $userId), $seed);
    }
}

if (!function_exists('rateb_user_ensure_login_barcode')) {
    /**
     * Return login barcode for user; create one if missing.
     *
     * @return array{ok:bool, barcode:?string, username:?string, message?:string}
     */
    function rateb_user_ensure_login_barcode(mysqli $db, int $userId): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'barcode' => null, 'username' => null, 'message' => 'Invalid user.'];
        }

        $col = rateb_users_login_barcode_column($db);
        if ($col === null || $col === '') {
            return ['ok' => false, 'barcode' => null, 'username' => null, 'message' => 'Barcode column is not available on users table.'];
        }

        $pk = rateb_users_primary_key_for_barcode($db);
        $stmt = $db->prepare("SELECT username, `{$col}` AS login_barcode FROM users WHERE `{$pk}` = ? LIMIT 1");
        if (!$stmt) {
            return ['ok' => false, 'barcode' => null, 'username' => null, 'message' => 'Database error.'];
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            return ['ok' => false, 'barcode' => null, 'username' => null, 'message' => 'User not found.'];
        }

        $username = trim((string) ($row['username'] ?? ''));
        $existing = trim((string) ($row['login_barcode'] ?? ''));
        if ($existing !== '') {
            return ['ok' => true, 'barcode' => $existing, 'username' => $username];
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = rateb_generate_login_barcode_value($userId, $username);
            if ($attempt > 0) {
                $candidate .= (string) random_int(10, 99);
            }
            $chk = $db->prepare("SELECT `{$pk}` FROM users WHERE `{$col}` = ? AND `{$pk}` <> ? LIMIT 1");
            if ($chk) {
                $chk->bind_param('si', $candidate, $userId);
                $chk->execute();
                $taken = $chk->get_result();
                $chk->close();
                if ($taken && $taken->num_rows > 0) {
                    continue;
                }
            }
            $up = $db->prepare("UPDATE users SET `{$col}` = ? WHERE `{$pk}` = ? LIMIT 1");
            if (!$up) {
                return ['ok' => false, 'barcode' => null, 'username' => $username, 'message' => 'Could not save barcode.'];
            }
            $up->bind_param('si', $candidate, $userId);
            $up->execute();
            $up->close();
            return ['ok' => true, 'barcode' => $candidate, 'username' => $username];
        }

        return ['ok' => false, 'barcode' => null, 'username' => $username, 'message' => 'Could not generate a unique barcode.'];
    }
}

if (!function_exists('rateb_user_fetch_login_barcode')) {
    function rateb_user_fetch_login_barcode(mysqli $db, int $userId): array
    {
        return rateb_user_ensure_login_barcode($db, $userId);
    }
}
