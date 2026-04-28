<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/migrations/clear_control_admins_keep_admin.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/migrations/clear_control_admins_keep_admin.php`.
 */
/**
 * Clear all control panel users and keep only admin (password: admin123)
 * Run from browser: https://out.ratib.sa/config/migrations/clear_control_admins_keep_admin.php
 * Or via CLI: php config/migrations/clear_control_admins_keep_admin.php
 */
define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';

// Require control login when run from web (session already started by config)
// CLI: no login needed (for recovery when locked out)
if (php_sapi_name() !== 'cli') {
    if (empty($_SESSION['control_logged_in'])) {
        header('Content-Type: text/html; charset=UTF-8');
        http_response_code(401);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Login required</title></head><body>';
        echo '<p>Control panel login required.</p>';
        echo '<p><strong>Option 1:</strong> <a href="../pages/login.php?control=1">Log in</a> first, then open this script again.</p>';
        echo '<p><strong>Option 2:</strong> Run from command line (no login):<br><code>php config/migrations/clear_control_admins_keep_admin.php</code></p>';
        echo '<p>Or double-click: <code>config/migrations/run_clear_admin.bat</code></p>';
        echo '</body></html>';
        exit;
    }
}

$conn = $GLOBALS['control_conn'] ?? null;
if (!$conn || !($conn instanceof mysqli)) {
    die("Control database not available.\n");
}

try {
    // Delete all control admins
    $conn->query("DELETE FROM control_admins");
    
    // Insert admin with password admin123
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $username = 'admin';
    $fullName = 'Admin';
    $stmt = $conn->prepare("INSERT INTO control_admins (username, password, full_name, is_active) VALUES (?, ?, ?, 1)");
    $stmt->bind_param('sss', $username, $hash, $fullName);
    $stmt->execute();
    
    echo "Done. Cleared all users. Admin restored with password: admin123\n";
    if (php_sapi_name() !== 'cli') {
        echo "\n<a href='" . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../pages/control/control-panel-users.php') . "'>Back to Control Panel Users</a>";
    }
} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
}
