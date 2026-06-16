<?php
declare(strict_types=1);
/**
 * One-time: reset workforce login admin / 123456 in every country DB from control_agencies.
 * DELETE this file after use.
 *
 * Browser: https://rateb.sa/pages/rateb-reset-country-test-admin.php?run=1
 *   Header: X-Rateb-Migrate-Token: <same as deploy / RATEB_ERP_MIGRATE_TOKEN>
 * CLI (on server): php pages/rateb-reset-country-test-admin.php
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$cli = (PHP_SAPI === 'cli');
if ($cli) {
    // In CLI there is no HTTP host, so force rateb.sa env resolution.
    $_SERVER['HTTP_HOST'] = 'rateb.sa';
    $_GET['control'] = '1';
}
if (!$cli && (!isset($_GET['run']) || (string) $_GET['run'] !== '1')) {
    http_response_code(403);
    exit("Forbidden. Use ?run=1 with X-Rateb-Migrate-Token, or run via CLI on the server.\n");
}

if (!$cli) {
    $provided = trim((string) ($_SERVER['HTTP_X_RATEB_MIGRATE_TOKEN'] ?? ''));
    $expected = getenv('RATEB_ERP_MIGRATE_TOKEN') ?: '';
    if ($expected === '' && defined('RATEB_ERP_MIGRATE_TOKEN')) {
        $expected = (string) RATEB_ERP_MIGRATE_TOKEN;
    }
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        exit("Forbidden — set X-Rateb-Migrate-Token header (deploy token).\n");
    }
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/control_lookup_conn.php';

$username = 'admin';
$password = '123456';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

$lines = [];
$lines[] = 'RATEB country test admin reset';
$lines[] = 'username=' . $username . ' password=' . $password;
$lines[] = 'hash=' . $hash;
$lines[] = '';

$ctrl = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
if (!$ctrl instanceof mysqli) {
    $lines[] = 'FAIL control DB connection (CONTROL_PANEL_DB_NAME)';
    echo implode("\n", $lines) . "\n";
    exit(1);
}

$agencies = [];
$res = $ctrl->query(
    'SELECT DISTINCT db_host, db_port, db_user, db_pass, db_name, name, country_id '
    . 'FROM control_agencies WHERE is_active = 1 AND db_name IS NOT NULL AND TRIM(db_name) <> "" ORDER BY db_name'
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $agencies[] = $row;
    }
}

if ($agencies === []) {
    $lines[] = 'No active agencies with db_name in control_agencies.';
    echo implode("\n", $lines) . "\n";
    exit(1);
}

$hostDefault = defined('DB_HOST') ? (string) DB_HOST : '127.0.0.1';
$portDefault = defined('DB_PORT') ? (int) DB_PORT : 3306;
$userDefault = defined('DB_USER') ? (string) DB_USER : '';
$passDefault = defined('DB_PASS') ? (string) DB_PASS : '';

$resetOne = static function (mysqli $conn, string $uname, string $pwdHash) use (&$lines): bool {
    $res = $conn->query('SHOW TABLES LIKE \'users\'');
    if (!$res || $res->num_rows === 0) {
        $lines[] = '  skip: no users table';

        return false;
    }
    $cols = [];
    $cRes = $conn->query('SHOW COLUMNS FROM users');
    while ($cRes && ($c = $cRes->fetch_assoc())) {
        $cols[] = (string) ($c['Field'] ?? '');
    }
    if (!in_array('username', $cols, true) || !in_array('password', $cols, true)) {
        $lines[] = '  skip: users missing username/password columns';

        return false;
    }
    $pk = in_array('user_id', $cols, true) ? 'user_id' : (in_array('id', $cols, true) ? 'id' : 'user_id');
    $hasEmail = in_array('email', $cols, true);
    $hasRoleId = in_array('role_id', $cols, true);
    $hasStatus = in_array('status', $cols, true);
    $hasIsActive = in_array('is_active', $cols, true);

    $stmt = $conn->prepare("SELECT `{$pk}` FROM users WHERE username = ? LIMIT 1");
    if (!$stmt) {
        $lines[] = '  FAIL prepare select: ' . $conn->error;

        return false;
    }
    $stmt->bind_param('s', $uname);
    $stmt->execute();
    $r = $stmt->get_result();
    $row = $r ? $r->fetch_assoc() : null;
    $stmt->close();

    if ($row) {
        $uid = (int) ($row[$pk] ?? 0);
        $extra = '';
        if ($hasStatus) {
            $extra .= ", status = 'active'";
        }
        if ($hasIsActive) {
            $extra .= ', is_active = 1';
        }
        $sql = "UPDATE users SET password = ?{$extra} WHERE `{$pk}` = ?";
        $up = $conn->prepare($sql);
        if (!$up) {
            $lines[] = '  FAIL prepare update: ' . $conn->error;

            return false;
        }
        $up->bind_param('si', $pwdHash, $uid);
        $ok = $up->execute();
        $up->close();
        $lines[] = $ok ? '  OK updated admin (id=' . $uid . ')' : '  FAIL update: ' . $conn->error;

        return $ok;
    }

    $email = 'admin@rateb.sa';
    $roleId = 1;
    if ($hasEmail && $hasRoleId && $hasStatus) {
        $ins = $conn->prepare('INSERT INTO users (username, password, email, role_id, status) VALUES (?, ?, ?, ?, ?)');
        $status = 'active';
        $ins->bind_param('sssis', $uname, $pwdHash, $email, $roleId, $status);
    } elseif ($hasEmail && $hasRoleId) {
        $ins = $conn->prepare('INSERT INTO users (username, password, email, role_id) VALUES (?, ?, ?, ?)');
        $ins->bind_param('sssi', $uname, $pwdHash, $email, $roleId);
    } elseif ($hasRoleId) {
        $ins = $conn->prepare('INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)');
        $ins->bind_param('ssi', $uname, $pwdHash, $roleId);
    } else {
        $ins = $conn->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
        $ins->bind_param('ss', $uname, $pwdHash);
    }
    if (!$ins) {
        $lines[] = '  FAIL prepare insert: ' . $conn->error;

        return false;
    }
    $ok = $ins->execute();
    $ins->close();
    $lines[] = $ok ? '  OK inserted admin' : '  FAIL insert: ' . $conn->error;

    return $ok;
};

$seen = [];
foreach ($agencies as $ag) {
    $dbName = trim((string) ($ag['db_name'] ?? ''));
    if ($dbName === '' || isset($seen[$dbName])) {
        continue;
    }
    $seen[$dbName] = true;
    $label = trim((string) ($ag['name'] ?? '')) . ' → ' . $dbName;
    $lines[] = $label;

    $host = trim((string) ($ag['db_host'] ?? '')) ?: $hostDefault;
    $port = (int) ($ag['db_port'] ?? 0) ?: $portDefault;
    $user = trim((string) ($ag['db_user'] ?? '')) ?: $userDefault;
    $pass = (string) ($ag['db_pass'] ?? '');
    if ($pass === '' && $user === $userDefault) {
        $pass = $passDefault;
    }

    try {
        $tenant = @new mysqli($host, $user, $pass, $dbName, $port);
        if ($tenant->connect_error) {
            $lines[] = '  FAIL connect: ' . $tenant->connect_error;
            continue;
        }
        $tenant->set_charset('utf8mb4');
        $resetOne($tenant, $username, $hash);
        $tenant->close();
    } catch (Throwable $e) {
        $lines[] = '  FAIL ' . $e->getMessage();
    }
    $lines[] = '';
}

$lines[] = 'Done. Test: https://rateb.sa/bangladesh/login (admin / 123456)';
$lines[] = 'DELETE pages/rateb-reset-country-test-admin.php after use.';

echo implode("\n", $lines) . "\n";
