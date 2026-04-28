<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/setup-admin.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/setup-admin.php`.
 */
/**
 * Setup or reset admin user - run once.
 * DELETE THIS FILE after use for security!
 *
 * URL: https://out.ratib.sa/pages/setup-admin.php?run=1
 */
$cli = (php_sapi_name() === 'cli');

// Load config first (before any output) to avoid "headers already sent" warning
require_once __DIR__ . '/../includes/config.php';

if (!$cli) {
    if (!isset($_GET['run']) || $_GET['run'] !== '1') {
        header('Content-Type: text/html; charset=UTF-8');
        die('<p>Add <code>?run=1</code> to the URL to run. Example: <a href="?run=1">setup-admin.php?run=1</a></p><p><strong>Delete this file after use!</strong></p>');
    }
    header('Content-Type: text/html; charset=UTF-8');
    echo '<pre style="font-family:monospace;padding:20px;background:#f5f5f5;">';
}

$username = 'admin';
$password = 'admin123';  // Simpler - no special chars (change after first login)

$conn = $GLOBALS['conn'] ?? null;
if (!$conn) {
    $msg = "Database connection failed.";
    echo $cli ? $msg . "\n" : '</pre><p style="color:red">' . htmlspecialchars($msg) . '</p>';
    exit(1);
}

echo "Connecting to " . DB_NAME . "...\n";

// Ensure roles table has admin role (role_id = 1)
$chk = $conn->query("SHOW TABLES LIKE 'roles'");
if ($chk && $chk->num_rows > 0) {
    $r = $conn->query("SELECT role_id FROM roles WHERE role_id = 1 LIMIT 1");
    if (!$r || $r->num_rows === 0) {
        $conn->query("INSERT IGNORE INTO roles (role_id, role_name, description) VALUES (1, 'Admin', 'Administrator')");
        echo "Created Admin role.\n";
    }
}

$cols = [];
$res = $conn->query("SHOW COLUMNS FROM users");
if (!$res) {
    $msg = "Users table not found.";
    echo $cli ? $msg . "\n" : '</pre><p style="color:red">' . htmlspecialchars($msg) . '</p>';
    exit(1);
}
while ($row = $res->fetch_assoc()) {
    $cols[] = $row['Field'];
}

$hasEmail = in_array('email', $cols);
$hasStatus = in_array('status', $cols);
$hasRoleId = in_array('role_id', $cols);
$hasCountryId = in_array('country_id', $cols);

$stmt = $conn->prepare("SELECT user_id, username FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();
$stmt->close();

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

if ($existing) {
    $stmt = $conn->prepare("UPDATE users SET password = ?" . ($hasStatus ? ", status = 'active'" : "") . " WHERE user_id = ?");
    $stmt->bind_param("si", $hashedPassword, $existing['user_id']);
    $stmt->execute();
    $stmt->close();
    echo "Password reset for user '{$username}'.\n";
} else {
    $email = 'admin@ratibprogram.com';
    $roleId = 1;
    $status = 'active';
    $countryId = 1;
    if ($hasCountryId) {
        $chk = $conn->query("SHOW TABLES LIKE 'countries'");
        if ($chk && $chk->num_rows > 0) {
            $chkCountry = $conn->query("SELECT id FROM countries ORDER BY id ASC LIMIT 1");
            if ($chkCountry && $chkCountry->num_rows > 0) {
                $countryId = (int)$chkCountry->fetch_assoc()['id'];
            }
        }
    }
    if ($hasEmail && $hasRoleId && $hasStatus && $hasCountryId) {
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role_id, status, country_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssisi", $username, $hashedPassword, $email, $roleId, $status, $countryId);
    } elseif ($hasEmail && $hasRoleId && $hasStatus) {
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role_id, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $username, $hashedPassword, $email, $roleId, $status);
    } elseif ($hasEmail && $hasRoleId && $hasCountryId) {
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role_id, country_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $username, $hashedPassword, $email, $roleId, $countryId);
    } elseif ($hasEmail && $hasRoleId) {
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $username, $hashedPassword, $email, $roleId);
    } elseif ($hasCountryId) {
        $stmt = $conn->prepare("INSERT INTO users (username, password, role_id, country_id) VALUES (?, ?, 1, ?)");
        $stmt->bind_param("ssi", $username, $hashedPassword, $countryId);
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, password, role_id) VALUES (?, ?, 1)");
        $stmt->bind_param("ss", $username, $hashedPassword);
    }
    $stmt->execute();
    $stmt->close();
    echo "Created user '{$username}'.\n";
}

$conn->close();

echo "\n========================================\n";
echo "Login credentials:\n";
echo "  Username: {$username}\n";
echo "  Password: {$password}\n";
echo "========================================\n\n";
echo "Login: <a href=\"login.php\">login.php</a>\n\n";
echo "IMPORTANT: Change password after first login!\n";
echo "\nSECURITY: Delete this file (pages/setup-admin.php) now!\n";

if (!$cli) {
    echo '</pre>';
}
