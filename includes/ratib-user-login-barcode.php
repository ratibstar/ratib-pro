<?php
declare(strict_types=1);

/**
 * Login barcode column detection / optional schema ensure.
 */
if (!function_exists('ratib_users_login_barcode_column')) {
    /**
     * @return string|null Column name on `users` used for barcode login, or null if unavailable.
     */
    function ratib_users_login_barcode_column(mysqli $db): ?string
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
