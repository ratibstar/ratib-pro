<?php
declare(strict_types=1);
/**
 * Shared users-table schema helpers (no session, no permissions side effects).
 */
if (!function_exists('rateb_users_primary_key_column')) {
    /**
     * @param mysqli|PDO $conn
     * @return 'user_id'|'id'
     */
    function rateb_users_primary_key_column($conn): string
    {
        if ($conn instanceof mysqli) {
            static $cache = [];
            $oid = spl_object_hash($conn);
            if (isset($cache[$oid])) {
                return $cache[$oid];
            }
            $pk = 'user_id';
            $chk = @$conn->query("SHOW COLUMNS FROM users LIKE 'user_id'");
            if ($chk && $chk->num_rows > 0) {
                $cache[$oid] = $pk;

                return $pk;
            }
            $chk2 = @$conn->query("SHOW COLUMNS FROM users LIKE 'id'");
            if ($chk2 && $chk2->num_rows > 0) {
                $pk = 'id';
            }
            $cache[$oid] = $pk;

            return $pk;
        }
        if ($conn instanceof PDO) {
            static $cachePdo = [];
            $oid = spl_object_hash($conn);
            if (isset($cachePdo[$oid])) {
                return $cachePdo[$oid];
            }
            $pk = 'user_id';
            try {
                $r = $conn->query("SHOW COLUMNS FROM users LIKE 'user_id'");
                if ($r && $r->rowCount() > 0) {
                    $cachePdo[$oid] = $pk;

                    return $pk;
                }
                $r2 = $conn->query("SHOW COLUMNS FROM users LIKE 'id'");
                if ($r2 && $r2->rowCount() > 0) {
                    $pk = 'id';
                }
            } catch (Throwable $e) {
                /* ignore */
            }
            $cachePdo[$oid] = $pk;

            return $pk;
        }

        return 'user_id';
    }
}
