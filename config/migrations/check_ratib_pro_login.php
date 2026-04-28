<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/migrations/check_ratib_pro_login.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/migrations/check_ratib_pro_login.php`.
 */
/**
 * Diagnostic: Check why Ratib Pro login might fail.
 * Run from browser: https://out.ratib.sa/config/migrations/check_ratib_pro_login.php
 */
require_once __DIR__ . '/../../config/env/load.php';
header('Content-Type: text/plain; charset=utf-8');

$issues = [];
$ok = [];

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, defined('DB_PORT') ? DB_PORT : 3306);
    $conn->set_charset("utf8mb4");
    $ok[] = "Connected to " . DB_NAME;
} catch (Exception $e) {
    die("DB connection failed: " . $e->getMessage());
}

// Check control_admins
$chk = @$conn->query("SHOW TABLES LIKE 'control_admins'");
if (!$chk || $chk->num_rows === 0) {
    $issues[] = "control_admins table does not exist";
} else {
    $ok[] = "control_admins table exists";
    $r = $conn->query("SELECT id, username, is_active FROM control_admins");
    if ($r) {
        $count = $r->num_rows;
        $ok[] = "control_admins has $count user(s)";
        while ($row = $r->fetch_assoc()) {
            $ok[] = "  - " . $row['username'] . " (id=" . $row['id'] . ", active=" . $row['is_active'] . ")";
        }
    }
}

// Check control_agencies for Bangladesh
$chk = @$conn->query("SHOW TABLES LIKE 'control_agencies'");
if (!$chk || $chk->num_rows === 0) {
    $issues[] = "control_agencies table does not exist";
} else {
    $stmt = $conn->prepare("SELECT c.id, c.name, a.db_name, a.db_user FROM control_agencies a JOIN control_countries c ON a.country_id = c.id WHERE c.slug = 'bangladesh' AND a.is_active = 1 LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $ok[] = "Bangladesh agency: db_name=" . $row['db_name'] . ", db_user=" . $row['db_user'];
            if (trim($row['db_name'] ?? '') === trim(DB_NAME ?? '')) {
                $ok[] = "Bangladesh uses main DB (same as " . DB_NAME . ") - OK";
            }
        } else {
            $issues[] = "No agency found for Bangladesh";
        }
        $stmt->close();
    }
}

// Check users table in main DB
$chk = @$conn->query("SHOW TABLES LIKE 'users'");
if ($chk && $chk->num_rows > 0) {
    $r = $conn->query("SELECT COUNT(*) as c FROM users");
    $count = $r ? (int)$r->fetch_assoc()['c'] : 0;
    $ok[] = "users table has $count user(s) in " . DB_NAME;
}

echo "=== Ratib Pro Login Diagnostic ===\n\n";
echo "OK:\n" . implode("\n", $ok) . "\n\n";
if (!empty($issues)) {
    echo "Issues:\n" . implode("\n", $issues) . "\n\n";
}
echo "SINGLE_URL_MODE: " . (defined('SINGLE_URL_MODE') && SINGLE_URL_MODE ? 'yes' : 'no') . "\n";
echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'not set') . "\n";
