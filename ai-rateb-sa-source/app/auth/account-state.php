<?php
declare(strict_types=1);

function rateb_users_column(object $pdo, string $column): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $found = false;
    try {
        foreach ($pdo->query('SHOW COLUMNS FROM users') as $row) {
            if ((string) ($row['Field'] ?? '') === $column) {
                $found = true;
                break;
            }
        }
    } catch (Throwable $error) {
        $found = false;
    }
    $cache[$key] = $found;
    return $found;
}

function rateb_stamp_login(object $pdo, int $userId): void
{
    if ($userId < 1 || !rateb_users_column($pdo, 'last_login_at')) {
        return;
    }
    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$userId]);
}

function rateb_user_is_disabled(object $pdo, int $userId): bool
{
    if ($userId < 1 || !rateb_users_column($pdo, 'disabled_at')) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT role, disabled_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    if ((string) ($row['role'] ?? '') === 'owner') {
        return false;
    }
    return $row['disabled_at'] !== null && (string) $row['disabled_at'] !== '';
}

function rateb_clear_disabled_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    session_regenerate_id(true);
}

function rateb_block_disabled_session(object $pdo): void
{
    if (empty($_SESSION['user_id']) || !rateb_user_is_disabled($pdo, (int) $_SESSION['user_id'])) {
        return;
    }
    rateb_clear_disabled_session();
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($script, ['login.php', 'register.php', 'logout.php', 'google.php', 'github.php', 'webhook.php'], true)) {
        return;
    }
    header('Location: /login?disabled=1');
    exit;
}
