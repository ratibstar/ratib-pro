<?php
/**
 * EN: Handles control-panel module behavior and admin-country operations in `control-panel/core/Auth.php`.
 * AR: يدير سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/core/Auth.php`.
 */
if (defined('AUTH_LOADED')) return;
require_once __DIR__ . '/Database.php';
class Auth {
    public static function login(string $username, string $password): array {
        $tenantId = defined('TENANT_ID') ? TENANT_ID : null;
        $pdo = Database::getConnection();
        $cols = 'user_id, username, password, role_id, status, country_id';
        $chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
        if ($chk && $chk->rowCount() > 0) $cols .= ', email';
        $chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'tenant_role'");
        if ($chk && $chk->rowCount() > 0) $cols .= ', tenant_role';
        $stmt = $pdo->prepare("SELECT {$cols} FROM users WHERE username = ? " . (strpos($cols, 'email') !== false ? "OR email = ?" : "") . " LIMIT 1");
        $stmt->execute(strpos($cols, 'email') !== false ? [$username, $username] : [$username]);
        $user = $stmt->fetch();
        if (!$user) return ['success' => false, 'user' => null, 'error' => 'Invalid username or password.'];
        if (!password_verify($password, $user['password'])) return ['success' => false, 'user' => null, 'error' => 'Invalid username or password.'];
        $status = strtolower(trim($user['status'] ?? ''));
        if ($status !== 'active') {
            $chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_active'");
            if ($chk && $chk->rowCount() > 0) {
                $s2 = $pdo->prepare("SELECT is_active FROM users WHERE user_id = ?");
                $s2->execute([$user['user_id']]);
                $r = $s2->fetch();
                if (!$r || empty($r['is_active'])) return ['success' => false, 'user' => null, 'error' => 'Account is inactive.'];
            } else return ['success' => false, 'user' => null, 'error' => 'Account is inactive.'];
        }
        $role = $user['tenant_role'] ?? null;
        if (empty($role)) $role = ((int)($user['role_id'] ?? 0) === 1) ? 'super_admin' : 'user';
        $userCountryId = $user['country_id'] !== null ? (int) $user['country_id'] : null;
        if ($role !== 'super_admin' && ($userCountryId === null || $userCountryId !== $tenantId)) return ['success' => false, 'user' => null, 'error' => 'Access denied for this tenant.'];
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $role;
        $_SESSION['tenant_role'] = $role;
        $_SESSION['country_id'] = $userCountryId;
        $_SESSION['logged_in'] = true;
        return ['success' => true, 'user' => $user, 'error' => ''];
    }
    public static function isSuperAdmin(): bool {
        if (($_SESSION['tenant_role'] ?? '') === 'super_admin') return true;
        if (((int)($_SESSION['role_id'] ?? 0)) === 1) return true;
        return !empty($_SESSION['control_logged_in']);
    }
    public static function isCountryAdmin(): bool { return ($_SESSION['tenant_role'] ?? '') === 'country_admin'; }
    public static function getCountryId(): ?int { $id = $_SESSION['country_id'] ?? null; return $id !== null ? (int) $id : null; }
    public static function isLoggedIn(): bool { return !empty($_SESSION['logged_in']); }
}
define('AUTH_LOADED', true);
