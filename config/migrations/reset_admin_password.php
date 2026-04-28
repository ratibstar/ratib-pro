<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/migrations/reset_admin_password.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/migrations/reset_admin_password.php`.
 */
/**
 * Reset admin password to admin123 (no login required - for recovery when locked out)
 * Run from browser: https://out.ratib.sa/config/migrations/reset_admin_password.php
 * Or CLI: php config/migrations/reset_admin_password.php
 * DELETE this file after use for security.
 */
define('IS_CONTROL_PANEL', true);
require_once __DIR__ . '/../../includes/config.php';

$conn = $GLOBALS['control_conn'] ?? null;
if (!$conn || !($conn instanceof mysqli)) {
    die("Control database not available.\n");
}

$username = 'admin';
$newPassword = 'admin123';
$hash = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    // Update existing admin
    $stmt = $conn->prepare("UPDATE control_admins SET password = ?, is_active = 1 WHERE username = ?");
    $stmt->bind_param('ss', $hash, $username);
    $stmt->execute();
    $updated = $stmt->affected_rows;
    
    $adminId = 0;
    if ($updated > 0) {
        $msg = "Password reset. Login: admin / admin123";
        $stmt = $conn->prepare("SELECT id FROM control_admins WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r && $row = $r->fetch_assoc()) $adminId = (int)$row['id'];
    } else {
        // No admin found - insert one
        $fullName = 'Admin';
        $stmt = $conn->prepare("INSERT INTO control_admins (username, password, full_name, is_active) VALUES (?, ?, ?, 1)");
        $stmt->bind_param('sss', $username, $hash, $fullName);
        $stmt->execute();
        $adminId = (int)$conn->insert_id;
        $msg = "Admin created. Login: admin / admin123";
    }
    
    // Grant full access so admin can see all countries/agencies
    if ($adminId > 0) {
        $chk = $conn->query("SHOW TABLES LIKE 'control_admin_permissions'");
        if ($chk && $chk->num_rows > 0) {
            $permsJson = json_encode(['*']);
            $stmt = $conn->prepare("INSERT INTO control_admin_permissions (user_id, permissions) VALUES (?, ?) ON DUPLICATE KEY UPDATE permissions = VALUES(permissions)");
            if ($stmt) {
                $stmt->bind_param('is', $adminId, $permsJson);
                $stmt->execute();
            }
        }
    }
    
    if (php_sapi_name() === 'cli') {
        echo $msg . "\n";
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Password Reset</title></head><body>';
        echo '<p style="color:green;font-weight:bold;">' . htmlspecialchars($msg) . '</p>';
        echo '<p><a href="../../pages/login.php?control=1">Go to Control Panel Login</a></p>';
        echo '<p style="color:#666;font-size:12px;">Delete this file (reset_admin_password.php) after use for security.</p>';
        echo '</body></html>';
    }
} catch (Exception $e) {
    $err = "Error: " . $e->getMessage();
    if (php_sapi_name() === 'cli') {
        echo $err . "\n";
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><body><p style="color:red;">' . htmlspecialchars($err) . '</p></body></html>';
    }
}
