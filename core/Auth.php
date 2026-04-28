<?php
/**
 * EN: Handles core framework/runtime behavior in `core/Auth.php`.
 * AR: يدير سلوك النواة والإطار الأساسي للتشغيل في `core/Auth.php`.
 */
/**
 * Auth - Multi-tenant authentication
 *
 * Validates login with tenant isolation.
 * super_admin: no tenant restriction
 * country_admin / user: must match TENANT_ID
 */
if (defined('AUTH_LOADED')) {
    return;
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../admin/core/EventBus.php';

class Auth
{
    /**
     * Validate credentials and set session. Regenerates session ID.
     *
     * @param string $username Or email
     * @param string $password Plain text
     * @return array ['success' => bool, 'user' => array|null, 'error' => string]
     */
    public static function login(string $username, string $password): array
    {
        $tenantId = defined('TENANT_ID') ? TENANT_ID : null;

        $pdo = Database::getInstance()->getConnection();
        $cols = 'user_id, username, password, role_id, status, country_id';
        $chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
        if ($chk && $chk->rowCount() > 0) $cols .= ', email';
        $chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'tenant_role'");
        if ($chk && $chk->rowCount() > 0) $cols .= ', tenant_role';
        $stmt = $pdo->prepare("SELECT {$cols} FROM users WHERE username = ? " . (strpos($cols, 'email') !== false ? "OR email = ?" : "") . " LIMIT 1");
        $stmt->execute(strpos($cols, 'email') !== false ? [$username, $username] : [$username]);
        $user = $stmt->fetch();

        if (!$user) {
            emitEvent('AUTH_LOGIN_FAILED', 'warn', 'Login failed: user not found', [
                'tenant_id' => $tenantId,
                'source' => 'auth',
                'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'auth/login'),
                'mode' => 'password',
            ]);
            return ['success' => false, 'user' => null, 'error' => 'Invalid username or password.'];
        }

        if (!password_verify($password, $user['password'])) {
            emitEvent('AUTH_LOGIN_FAILED', 'warn', 'Login failed: invalid password', [
                'tenant_id' => $tenantId,
                'user_id' => (int) ($user['user_id'] ?? 0),
                'source' => 'auth',
                'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'auth/login'),
                'mode' => 'password',
            ]);
            return ['success' => false, 'user' => null, 'error' => 'Invalid username or password.'];
        }

        $status = strtolower(trim($user['status'] ?? ''));
        if ($status !== 'active') {
            $chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_active'");
            if ($chk && $chk->rowCount() > 0) {
                $s2 = $pdo->prepare("SELECT is_active FROM users WHERE user_id = ?");
                $s2->execute([$user['user_id']]);
                $r = $s2->fetch();
                if (!$r || empty($r['is_active'])) {
                    emitEvent('AUTH_LOGIN_BLOCKED', 'warn', 'Login blocked: inactive account', [
                        'tenant_id' => $tenantId,
                        'user_id' => (int) ($user['user_id'] ?? 0),
                        'source' => 'auth',
                        'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'auth/login'),
                        'mode' => 'password',
                    ]);
                    return ['success' => false, 'user' => null, 'error' => 'Account is inactive.'];
                }
            } else {
                emitEvent('AUTH_LOGIN_BLOCKED', 'warn', 'Login blocked: inactive account', [
                    'tenant_id' => $tenantId,
                    'user_id' => (int) ($user['user_id'] ?? 0),
                    'source' => 'auth',
                    'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'auth/login'),
                    'mode' => 'password',
                ]);
                return ['success' => false, 'user' => null, 'error' => 'Account is inactive.'];
            }
        }

        $role = $user['tenant_role'] ?? null;
        if (empty($role)) {
            $role = ((int)($user['role_id'] ?? 0) === 1) ? 'super_admin' : 'user';
        }
        $userCountryId = $user['country_id'] !== null ? (int) $user['country_id'] : null;

        // super_admin: allow without tenant restriction
        if ($role === 'super_admin') {
            // OK
        } else {
            // country_admin / user: must match tenant
            if ($userCountryId === null || $userCountryId !== $tenantId) {
                emitEvent('AUTH_LOGIN_BLOCKED', 'warn', 'Login blocked: tenant mismatch', [
                    'tenant_id' => $tenantId,
                    'user_id' => (int) ($user['user_id'] ?? 0),
                    'source' => 'auth',
                    'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'auth/login'),
                    'mode' => 'password',
                ]);
                return ['success' => false, 'user' => null, 'error' => 'Access denied for this tenant.'];
            }
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $role;
        $_SESSION['tenant_role'] = $role;
        $_SESSION['country_id'] = $userCountryId;
        $_SESSION['logged_in'] = true;

        emitEvent('AUTH_LOGIN_SUCCESS', 'info', 'User login success', [
            'tenant_id' => $tenantId,
            'user_id' => (int) ($user['user_id'] ?? 0),
            'source' => 'auth',
            'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'auth/login'),
            'mode' => 'password',
        ]);

        return ['success' => true, 'user' => $user, 'error' => ''];
    }

    public static function logout(): void
    {
        $tenantId = defined('TENANT_ID') ? (int) TENANT_ID : ((int) ($_SESSION['country_id'] ?? 0));
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        emitEvent('AUTH_LOGOUT', 'info', 'User logout', [
            'tenant_id' => $tenantId > 0 ? $tenantId : null,
            'user_id' => $userId > 0 ? $userId : null,
            'source' => 'auth',
            'endpoint' => (string) ($_SERVER['REQUEST_URI'] ?? 'auth/logout'),
            'mode' => 'session',
        ]);
    }

    public static function isSuperAdmin(): bool
    {
        if (($_SESSION['tenant_role'] ?? '') === 'super_admin') return true;
        if (((int)($_SESSION['role_id'] ?? 0)) === 1) return true; // Legacy: role_id 1 = admin
        return false;
    }

    public static function isCountryAdmin(): bool
    {
        return ($_SESSION['tenant_role'] ?? '') === 'country_admin';
    }

    public static function getCountryId(): ?int
    {
        $id = $_SESSION['country_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['logged_in']);
    }

    /**
     * Numeric user id for audit (control panel SSO may have none).
     */
    public static function userId(): ?int
    {
        $id = (int) ($_SESSION['user_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    /**
     * Control-center RBAC role: SUPER_ADMIN | ADMIN | VIEWER
     */
    public static function role(): string
    {
        require_once __DIR__ . '/../admin/core/ControlCenterAccess.php';
        return ControlCenterAccess::role();
    }

    /**
     * @param string[] $roles Allowed ControlCenterAccess::* constants
     */
    public static function requireRole(array $roles): void
    {
        require_once __DIR__ . '/../admin/core/ControlCenterAccess.php';
        ControlCenterAccess::requireRole($roles);
    }
}

define('AUTH_LOADED', true);
