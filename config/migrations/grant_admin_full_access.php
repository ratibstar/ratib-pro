<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/migrations/grant_admin_full_access.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/migrations/grant_admin_full_access.php`.
 */
/**
 * Grant admin full access to see all countries/agencies (no login required)
 * Run: https://out.ratib.sa/config/migrations/grant_admin_full_access.php
 * Or CLI: php config/migrations/grant_admin_full_access.php
 * DELETE this file after use for security.
 */
define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';

$conn = $GLOBALS['control_conn'] ?? null;
if (!$conn || !($conn instanceof mysqli)) {
    die("Control database not available.\n");
}

try {
    $chk = $conn->query("SHOW TABLES LIKE 'control_admin_permissions'");
    if (!$chk || $chk->num_rows === 0) {
        $msg = "control_admin_permissions table not found. Admin may already have full access by default.";
    } else {
        $r = $conn->query("SELECT id FROM control_admins WHERE username = 'admin' LIMIT 1");
        if (!$r || $r->num_rows === 0) {
            $msg = "Admin user not found. Run reset_admin_password.php first.";
        } else {
            $row = $r->fetch_assoc();
            $adminId = (int)$row['id'];
            $permsJson = json_encode(['*']);
            $stmt = $conn->prepare("INSERT INTO control_admin_permissions (user_id, permissions) VALUES (?, ?) ON DUPLICATE KEY UPDATE permissions = VALUES(permissions)");
            $stmt->bind_param('is', $adminId, $permsJson);
            $stmt->execute();
            $msg = "Admin granted full access. Logout and login again to see all countries and agencies.";
        }
    }
    
    if (php_sapi_name() === 'cli') {
        echo $msg . "\n";
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Grant Full Access</title></head><body>';
        echo '<p style="color:green;font-weight:bold;">' . htmlspecialchars($msg) . '</p>';
        echo '<p><a href="../../pages/login.php?control=1">Go to Control Panel Login</a></p>';
        echo '<p style="color:#666;font-size:12px;">Delete this file after use for security.</p>';
        echo '</body></html>';
    }
} catch (Exception $e) {
    $err = "Error: " . $e->getMessage();
    if (php_sapi_name() === 'cli') echo $err . "\n";
    else echo '<p style="color:red;">' . htmlspecialchars($err) . '</p>';
}
