<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/check-login.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/check-login.php`.
 */
/**
 * Login diagnostic - helps debug login issues.
 * DELETE THIS FILE after fixing login!
 *
 * URL: https://out.ratib.sa/pages/check-login.php?run=1
 * Reset: https://out.ratib.sa/pages/check-login.php?run=1&reset=1
 */
require_once __DIR__ . '/../includes/config.php';

if (!isset($_GET['run']) || $_GET['run'] !== '1') {
    header('Content-Type: text/html; charset=UTF-8');
    die('<p>Add <code>?run=1</code> to run. <a href="?run=1">Run check</a></p><p><strong>Delete this file after use!</strong></p>');
}

$conn = $GLOBALS['conn'] ?? null;

// Reset password if requested
if (isset($_GET['reset']) && $_GET['reset'] === '1' && $conn) {
    $newPassword = 'admin123';
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $chkStatus = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
    $hasStatus = $chkStatus && $chkStatus->num_rows > 0;
    $sql = $hasStatus
        ? "UPDATE users SET password = ?, status = 'active' WHERE username = 'admin'"
        : "UPDATE users SET password = ? WHERE username = 'admin'";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $hash);
        $stmt->execute();
        $stmt->close();
        $doneParams = 'run=1&done=1';
        header('Location: check-login.php?' . $doneParams);
        exit;
    }
}

header('Content-Type: text/html; charset=UTF-8');
echo '<pre style="font-family:monospace;padding:20px;background:#f5f5f5;max-width:800px;">';

if (isset($_GET['done'])) {
    $loginUrl = 'login.php';
    echo "Password reset successfully!\n\n";
    echo "Login now with: admin / admin123\n";
    echo "<a href=\"" . htmlspecialchars($loginUrl) . "\">Go to login</a>\n\n";
    echo '</pre>';
    exit(0);
}
if (!$conn) {
    echo "ERROR: No database connection.\n";
    echo '</pre>';
    exit(1);
}

echo "Database: " . DB_NAME . "\n";
echo "Host: " . ($_SERVER['HTTP_HOST'] ?? '') . "\n";
echo "Mode: Ratib Pro\n\n";

$stmt = $conn->prepare("SELECT user_id, username, status, role_id, LEFT(password, 20) as pwd_preview FROM users WHERE username = 'admin' LIMIT 1");
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$user) {
    echo "admin user: NOT FOUND\n";
    echo "\nRun setup-admin.php?run=1 first to create the admin user.\n";
    echo '</pre>';
    exit(0);
}

echo "admin user: FOUND\n";
echo "  user_id: " . $user['user_id'] . "\n";
echo "  status: " . ($user['status'] ?? '(null)') . "\n";
echo "  role_id: " . ($user['role_id'] ?? '(null)') . "\n";
echo "  password hash: " . ($user['pwd_preview'] ?? '') . "...\n\n";

$testPassword = 'admin123';
$stmt2 = $conn->prepare("SELECT password FROM users WHERE username = 'admin' LIMIT 1");
$stmt2->execute();
$r2 = $stmt2->get_result()->fetch_assoc();
$stmt2->close();
$hash = $r2['password'] ?? '';

$ok = password_verify($testPassword, $hash);
echo "Password test ('admin123'): " . ($ok ? "OK - password matches!" : "FAIL - wrong password") . "\n\n";

if (!$ok) {
    $resetParams = 'run=1&reset=1';
    $resetUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'out.ratib.sa') . '/pages/check-login.php?' . $resetParams;
    echo ">>> RESET PASSWORD (click this link):\n";
    echo "    <a href=\"check-login.php?" . htmlspecialchars($resetParams) . "\" style=\"color:blue;font-weight:bold;font-size:16px;\">" . htmlspecialchars($resetUrl) . "</a>\n\n";
    echo "    Or copy this URL into your browser: " . htmlspecialchars($resetUrl) . "\n\n";
}

$statusOk = (strtolower(trim($user['status'] ?? '')) === 'active');
echo "Status check (active): " . ($statusOk ? "OK" : "FAIL - account may be inactive") . "\n\n";

echo "---\n";
echo "Login: https://" . ($_SERVER['HTTP_HOST'] ?? 'out.ratib.sa') . "/pages/login.php\n";
echo "Credentials: admin / admin123\n";
echo "\nDELETE this file (check-login.php) after use!\n";
echo '</pre>';
