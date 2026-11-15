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

function verify_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? $_POST['csrf'] ?? '');
    $expected = (string) ($_SESSION['csrf_token'] ?? $_SESSION['csrf'] ?? '');
    if ($expected === '' || !hash_equals($expected, $token)) {
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

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

function ui_language(): string
{
    return (($_COOKIE['rateb_ui_lang'] ?? '') === 'ar') ? 'ar' : 'en';
}

function require_admin(PDO $pdo): array
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || (string) ($user['role'] ?? '') !== 'owner') {
        http_response_code(403);
        $arabic = ui_language() === 'ar';
        header('Content-Type: text/html; charset=utf-8');
        echo $arabic
            ? '<!doctype html><html lang="ar" dir="rtl"><body><p>هذه الصفحة للمالك فقط.</p></body></html>'
            : '<!doctype html><html lang="en" dir="ltr"><body><p>This page is for the owner only.</p></body></html>';
        exit;
    }
    return $user;
}
