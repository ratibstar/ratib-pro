<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/setup-admin.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/setup-admin.php`.
 */
/**
 * Setup or reset admin user - run once from command line:
 *   php config/setup-admin.php
 *
 * Or from browser (DELETE THIS FILE after use for security):
 *   https://out.ratib.sa/config/setup-admin.php?run=1
 */
$cli = (php_sapi_name() === 'cli');

if (!$cli) {
    // Web: require ?run=1 for safety
    if (!isset($_GET['run']) || $_GET['run'] !== '1') {
        die('Add ?run=1 to run. Delete this file after use.');
    }
}

// Load config without full app
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'out.ratib.sa';
require_once __DIR__ . '/env/load.php';

// Override to use out.ratib.sa env when run from CLI (no HTTP_HOST)
if ($cli && !defined('DB_NAME')) {
    require __DIR__ . '/env/out_ratib_sa.php';
}

if (!defined('DB_NAME')) {
    die("Error: Could not load database config.\n");
}

$username = 'admin';
$password = 'Admin@123';  // Change this after first login

echo "Connecting to " . DB_NAME . "...\n";

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

// Ensure roles table has admin role (role_id = 1)
$chk = $conn->query("SHOW TABLES LIKE 'roles'");
if ($chk && $chk->num_rows > 0) {
    $r = $conn->query("SELECT role_id FROM roles WHERE role_id = 1 LIMIT 1");
    if (!$r || $r->num_rows === 0) {
        $conn->query("INSERT IGNORE INTO roles (role_id, role_name, description) VALUES (1, 'Admin', 'Administrator')");
        echo "Created Admin role.\n";
    }
}

// Check users table structure
$cols = [];
$res = $conn->query("SHOW COLUMNS FROM users");
if (!$res) {
    die("Users table not found. Run your database migrations first.\n");
}
while ($row = $res->fetch_assoc()) {
    $cols[] = $row['Field'];
}

$hasEmail = in_array('email', $cols);
$hasStatus = in_array('status', $cols);
$hasRoleId = in_array('role_id', $cols);

$stmt = $conn->prepare("SELECT user_id, username FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();
$stmt->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

if ($existing) {
    // Update existing admin password
    $stmt = $conn->prepare("UPDATE users SET password = ?" . ($hasStatus ? ", status = 'active'" : "") . " WHERE user_id = ?");
    $stmt->bind_param("si", $hashedPassword, $existing['user_id']);
    $stmt->execute();
    $stmt->close();
    echo "Password reset for user '{$username}'.\n";
} else {
    // Insert new admin user
    $email = 'admin@ratibprogram.com';
    $roleId = 1;
    $status = 'active';

    if ($hasEmail && $hasRoleId && $hasStatus) {
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role_id, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $username, $hashedPassword, $email, $roleId, $status);
    } elseif ($hasEmail && $hasRoleId) {
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $username, $hashedPassword, $email, $roleId);
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password, role_id) VALUES (?, ?, 1)");
        $stmt->bind_param("ss", $username, $hashedPassword);
    }
    $stmt->execute();
    $stmt->close();
    echo "Created user '{$username}'.\n";
}

$conn->close();

echo "\n";
echo "========================================\n";
echo "Login credentials:\n";
echo "  Username: {$username}\n";
echo "  Password: {$password}\n";
echo "========================================\n";
echo "\n";
echo "Login URLs:\n";
echo "  Normal (agency):  https://out.ratib.sa/pages/login.php\n";
echo "\n";
echo "IMPORTANT: Change the password after first login!\n";
if (!$cli) {
    echo "\nSECURITY: Delete this file (config/setup-admin.php) now!\n";
}
