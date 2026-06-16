<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/api/control/agency-db-helper.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/api/control/agency-db-helper.php`.
 */
/**
 * Shared helper: Connect to agency/country database.
 * Used by country-users-api.php, get-users-per-country.php, and pages/login.php for consistent connection logic.
 *
 * @param array $agency Row from control_agencies (db_host, db_port, db_user, db_pass, db_name, optional country_slug, country_id)
 * @param int $countryId For main-DB fallback: filter users by this country_id
 * @return array|null ['conn' => mysqli, 'db_name' => string, 'use_country_filter' => bool,
 *                     'connect_host','connect_port','connect_user','connect_pass' => actual DSN used] or null on failure
 */

if (!function_exists('rateb_ensure_minimal_rateb_pro_schema')) {
    /**
     * New tenant DBs (empty cPanel database) often have no tables. Create minimal roles + users so login and
     * Country Users API work without a manual SQL import.
     */
    function rateb_ensure_minimal_rateb_pro_schema(mysqli $conn): void
    {
        try {
            @$conn->query(
                "CREATE TABLE IF NOT EXISTS `roles` (
                    `role_id` int NOT NULL AUTO_INCREMENT,
                    `role_name` varchar(100) NOT NULL DEFAULT '',
                    `description` text,
                    `permissions` json DEFAULT NULL,
                    PRIMARY KEY (`role_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $r = @$conn->query('SELECT COUNT(*) AS c FROM `roles`');
            $cnt = ($r && ($row = $r->fetch_assoc())) ? (int) ($row['c'] ?? 0) : 0;
            if ($cnt === 0) {
                @$conn->query("INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES (1, 'Admin', 'Administrator')");
            }

            $chk = @$conn->query("SHOW TABLES LIKE 'users'");
            if ($chk && $chk->num_rows > 0) {
                return;
            }

            @$conn->query(
                "CREATE TABLE IF NOT EXISTS `users` (
                    `user_id` int NOT NULL AUTO_INCREMENT,
                    `username` varchar(100) NOT NULL,
                    `password` varchar(255) NOT NULL,
                    `email` varchar(255) DEFAULT NULL,
                    `role_id` int NOT NULL DEFAULT 1,
                    `status` varchar(50) NOT NULL DEFAULT 'active',
                    `country_id` int DEFAULT NULL,
                    `agency_id` int DEFAULT NULL,
                    `permissions` json DEFAULT NULL,
                    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    `last_login` datetime DEFAULT NULL,
                    PRIMARY KEY (`user_id`),
                    UNIQUE KEY `username` (`username`),
                    KEY `idx_role` (`role_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            error_log('rateb_ensure_minimal_rateb_pro_schema: ' . $e->getMessage());
        }
    }
}

if (function_exists('getAgencyDbConnection')) {
    return;
}

if (!function_exists('getAgencyDbConnectionLastError')) {
    /**
     * Last mysqli error from getAgencyDbConnection (for admin-facing API messages).
     */
    function getAgencyDbConnectionLastError() {
        return isset($GLOBALS['__agency_db_connect_error']) ? (string)$GLOBALS['__agency_db_connect_error'] : '';
    }
}

function getAgencyDbConnection($agency, $countryId = 0) {
    $GLOBALS['__agency_db_connect_error'] = '';
    $port = (int)($agency['db_port'] ?? 3306);
    $dbHost = trim($agency['db_host'] ?? '') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
    $dbUser = trim($agency['db_user'] ?? '') ?: (defined('DB_USER') ? DB_USER : '');
    $envUser = defined('DB_USER') ? (string) DB_USER : '';
    $envPass = defined('DB_PASS') ? (string) DB_PASS : '';
    // Shared MySQL user (admin_rateb): .env password is source of truth — agency rows often store stale cPanel passwords.
    $agencyPass = (string) ($agency['db_pass'] ?? '');
    $dbPass = ($dbUser !== '' && $dbUser === $envUser) ? $envPass : ($agencyPass !== '' ? $agencyPass : $envPass);
    $dbName = trim($agency['db_name'] ?? '');
    if (empty($dbName)) {
        $GLOBALS['__agency_db_connect_error'] = 'control_agencies.db_name is empty for this agency.';
        return null;
    }

    // mysqli + PDO must use the same host/user/pass after fallbacks (main creds, alt DB name, etc.).
    $cHost = $dbHost;
    $cPort = $port;
    $cUser = $dbUser;
    $cPass = $dbPass;

    $conn = null;
    $connectErr = '';
    try {
        $conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName, $port);
        if ($conn && $conn->connect_error) {
            $connectErr = $conn->connect_error;
            $conn = null;
        }
    } catch (Throwable $e) {
        $connectErr = $e->getMessage();
    }

    // Resolve country slug for canonical DB names (admin_{slug}) before retrying with main creds on a bad db_name.
    $slugRaw = trim((string)($agency['country_slug'] ?? ''));
    if ($slugRaw === '' && $countryId > 0 && function_exists('get_control_lookup_conn')) {
        $lk = get_control_lookup_conn();
        if ($lk instanceof mysqli) {
            $chkT = @$lk->query("SHOW TABLES LIKE 'control_countries'");
            if ($chkT && $chkT->num_rows > 0) {
                $ps = @$lk->prepare('SELECT slug FROM control_countries WHERE id = ? LIMIT 1');
                if ($ps) {
                    $ps->bind_param('i', $countryId);
                    $ps->execute();
                    $rs = $ps->get_result();
                    if ($rs && $rs->num_rows > 0) {
                        $slugRow = $rs->fetch_assoc();
                        $slugRaw = trim((string)($slugRow['slug'] ?? ''));
                    }
                    $ps->close();
                }
            }
        }
    }

    if (!$conn && defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && ($countryId > 0 || $slugRaw !== '')) {
        $norm = strtolower(preg_replace('/[^a-z0-9_-]+/i', '', str_replace([' ', '.'], '_', $slugRaw)));
        $norm = str_replace('-', '_', $norm);
        $slugCandidates = [];
        if (function_exists('rateb_country_database_candidates')) {
            $slugCandidates = rateb_country_database_candidates($norm);
        } elseif ($norm !== '') {
            $slugCandidates[] = 'admin_' . $norm;
            $slugCandidates[] = 'admin_' . $norm;
        }
        $slugCandidates = array_unique(array_filter($slugCandidates));
        foreach ($slugCandidates as $altDb) {
            if ($altDb === $dbName) {
                continue;
            }
            $try = @new mysqli(DB_HOST, DB_USER, DB_PASS, $altDb, defined('DB_PORT') ? DB_PORT : 3306);
            if ($try && !$try->connect_error) {
                $conn = $try;
                $dbName = $altDb;
                $cHost = defined('DB_HOST') ? DB_HOST : $dbHost;
                $cPort = defined('DB_PORT') ? (int) DB_PORT : 3306;
                $cUser = defined('DB_USER') ? DB_USER : $dbUser;
                $cPass = defined('DB_PASS') ? DB_PASS : $dbPass;
                break;
            }
            if ($try) {
                $connectErr = $try->connect_error ?: $connectErr;
                $try->close();
            }
        }
    }

    // Same database name, main app credentials (when agency row points at correct db but wrong user).
    if (!$conn && defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS')) {
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, $dbName, defined('DB_PORT') ? DB_PORT : 3306);
        if ($conn && $conn->connect_error) {
            $connectErr = $conn->connect_error;
            $conn = null;
        } elseif ($conn) {
            $cHost = DB_HOST;
            $cPort = defined('DB_PORT') ? (int) DB_PORT : 3306;
            $cUser = DB_USER;
            $cPass = DB_PASS;
        }
    }
    // Access denied: try alternate host (MySQL treats localhost vs 127.0.0.1 as different)
    if (!$conn && strpos($connectErr, 'Access denied') !== false) {
        $tryHost = (($dbHost ?: (defined('DB_HOST') ? DB_HOST : '')) === 'localhost') ? '127.0.0.1' : 'localhost';
        $tryUser = $dbUser ?: (defined('DB_USER') ? DB_USER : '');
        $tryPass = $dbPass ?: (defined('DB_PASS') ? DB_PASS : '');
        $conn = @new mysqli($tryHost, $tryUser, $tryPass, $dbName, $port);
        if ($conn && $conn->connect_error) {
            $connectErr = $conn->connect_error;
            $conn = null;
        } elseif ($conn) {
            $cHost = $tryHost;
            $cUser = $tryUser;
            $cPass = $tryPass;
        }
    }
    $isBangladesh = (stripos($dbName, 'bangladesh') !== false || stripos($dbName, 'bangladish') !== false);
    $isSriLanka = (stripos($dbName, 'sri_lanka') !== false || stripos($dbName, 'sri-lanka') !== false || stripos($dbName, 'sri lanka') !== false);
    if (!$conn && ($isBangladesh || $isSriLanka)) {
        $alts = [];
        if ($isBangladesh) {
            $alts = [];
            foreach (['bangladish', 'bangladesh'] as $slug) {
                foreach ((function_exists('rateb_country_database_candidates') ? rateb_country_database_candidates($slug) : ['admin_' . $slug, 'admin_' . $slug]) as $db) {
                    $alts[] = [$db, $dbHost, $dbUser, $dbPass];
                }
            }
        } elseif ($isSriLanka) {
            $alts = [];
            foreach ((function_exists('rateb_country_database_candidates') ? rateb_country_database_candidates('sri_lanka') : ['admin_sri_lanka', 'admin_sri_lanka', 'admin_sri Lanka']) as $db) {
                $alts[] = [$db, $dbHost, $dbUser, $dbPass];
            }
        }
        if (defined('DB_NAME') && DB_NAME !== $dbName) {
            $alts[] = [DB_NAME, defined('DB_HOST') ? DB_HOST : $dbHost, defined('DB_USER') ? DB_USER : $dbUser, defined('DB_PASS') ? DB_PASS : $dbPass];
        }
        foreach ($alts as $a) {
            list($alt, $h, $u, $p) = $a;
            if ($alt === $dbName) continue;
            $conn = @new mysqli($h, $u, $p, $alt, $port);
            if ($conn && !$conn->connect_error) {
                $dbName = $alt;
                $cHost = $h;
                $cUser = $u;
                $cPass = $p;
                break;
            }
            $conn = null;
        }
    }
    if (!$conn && defined('DB_NAME') && DB_NAME !== $dbName && $countryId > 0) {
        $h1 = defined('DB_HOST') ? DB_HOST : 'localhost';
        $p1 = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $u1 = defined('DB_USER') ? DB_USER : '';
        $pw1 = defined('DB_PASS') ? DB_PASS : '';
        $conn = @new mysqli($h1, $u1, $pw1, DB_NAME, $p1);
        $usedHost = $h1;
        if ($conn && $conn->connect_error) {
            $tryH = ($h1 === 'localhost') ? '127.0.0.1' : 'localhost';
            $conn = @new mysqli($tryH, $u1, $pw1, DB_NAME, $p1);
            $usedHost = $tryH;
        }
        if ($conn && !$conn->connect_error) {
            $dbName = DB_NAME;
            $cHost = $usedHost;
            $cPort = $p1;
            $cUser = $u1;
            $cPass = $pw1;
        } else {
            $conn = null;
        }
    }
    if (!$conn || $conn->connect_error) {
        $GLOBALS['__agency_db_connect_error'] = trim($connectErr !== '' ? $connectErr : ($conn && $conn->connect_error ? $conn->connect_error : 'Could not connect to agency database.'));
        return null;
    }
    $GLOBALS['__agency_db_connect_error'] = '';
    $conn->set_charset('utf8mb4');
    rateb_ensure_minimal_rateb_pro_schema($conn);
    $useCountryFilter = ($dbName === (defined('DB_NAME') ? DB_NAME : '')) && $countryId > 0;
    return [
        'conn' => $conn,
        'db_name' => $dbName,
        'use_country_filter' => $useCountryFilter,
        'connect_host' => $cHost,
        'connect_port' => $cPort,
        'connect_user' => $cUser,
        'connect_pass' => $cPass,
    ];
}
