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
    $allowed = ['/dashboard', '/campaign-new.php'];
    if ($role === 'owner') {
        $allowed[] = '/admin';
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
    $cookie = (string) ($_COOKIE['rateb_ui_lang'] ?? '');
    if ($cookie === 'en' || $cookie === 'ar') {
        return $cookie;
    }
    return 'ar';
}

function require_admin(PDO $pdo): array
{
    require_once __DIR__ . '/../admin/auth.php';
    $actor = require_admin_access($pdo);
    return admin_actor_legacy_user($actor);
}

function rateb_app_origin(): string
{
    if (PHP_SAPI === 'cli') {
        return 'https://ai.rateb.sa';
    }
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || !preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host)) {
        return 'https://ai.rateb.sa';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    return ($https ? 'https' : 'http') . '://' . $host;
}
