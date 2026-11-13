<?php
declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $_SESSION['csrf'] = $_SESSION['csrf_token'];
    return $_SESSION['csrf_token'];
}

function csrf_matches(string $expected, string $token): bool
{
    return $expected !== '' && hash_equals($expected, $token);
}

function verify_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? $_POST['csrf'] ?? '');
    $expected = (string) ($_SESSION['csrf_token'] ?? $_SESSION['csrf'] ?? '');
    if (!csrf_matches($expected, $token)) {
        http_response_code(419);
        exit('Invalid request.');
    }
}

function check_csrf(): void
{
    verify_csrf();
}

function rateb_normalize_next_path(string $next): string
{
    $next = trim($next);
    if ($next === '/dashboard.php') {
        $next = '/dashboard';
    }
    if ($next === '/admin') {
        $next = '/admin/';
    }
    if ($next === '') {
        return '';
    }
    if (str_contains($next, '://') || str_starts_with($next, '//') || str_contains($next, '\\')) {
        return '';
    }
    if (str_contains($next, '?') || str_contains($next, '#')) {
        return '';
    }
    if (str_contains($next, '..')) {
        return '';
    }
    if (preg_match('#^/[A-Za-z0-9_./-]*$#', $next) !== 1) {
        return '';
    }
    return $next;
}

function rateb_safe_next(string $next, ?string $role = null): string
{
    $next = rateb_normalize_next_path($next);
    if ($next === '') {
        return '/dashboard';
    }
    $allowed = ['/dashboard', '/campaign-new.php', '/brand.php'];
    if ($role === 'owner') {
        $allowed[] = '/admin/';
    }
    return in_array($next, $allowed, true) ? $next : '/dashboard';
}

function admin_role_allowed(?string $role): bool
{
    return $role === 'owner';
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }
    $authPdo = $GLOBALS['pdo'] ?? null;
    if (!$authPdo instanceof PDO && function_exists('db')) {
        $authPdo = db();
    }
    if (!$authPdo instanceof PDO) {
        return;
    }
    require_once __DIR__ . '/../app/auth/account-state.php';
    rateb_block_disabled_session($authPdo);
}

function ui_language(): string
{
    return (($_COOKIE['rateb_ui_lang'] ?? '') === 'ar') ? 'ar' : 'en';
}

function require_admin(PDO $pdo): array
{
    require_once __DIR__ . '/../admin/auth.php';
    $actor = require_admin_access($pdo);
    return admin_actor_legacy_user($actor);
}
