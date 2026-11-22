<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_login();

function rateb_account_return_url(string $fallback = '/account'): string
{
    $return = (string) ($_POST['return'] ?? '');
    if ($return === '' || $return[0] !== '/' || str_starts_with($return, '//') || str_contains($return, "\n")) {
        $return = $fallback;
    }
    $path = (string) (parse_url($return, PHP_URL_PATH) ?: $fallback);
    if ($path === '' || $path[0] !== '/') {
        $path = $fallback;
    }
    $modal = (string) ($_POST['modal'] ?? '') === '1';
    $query = [];
    if ($modal) {
        $query['account'] = '1';
    }
    return $path . ($query !== [] ? ('?' . http_build_query($query)) : '');
}

function rateb_account_redirect(string $key, string $value): void
{
    $base = rateb_account_return_url('/account');
    $sep = str_contains($base, '?') ? '&' : '?';
    header('Location: ' . $base . $sep . rawurlencode($key) . '=' . rawurlencode($value));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /account');
    exit;
}

$csrf = (string) ($_POST['csrf_token'] ?? $_POST['csrf'] ?? '');
$expected = (string) ($_SESSION['csrf_token'] ?? $_SESSION['csrf'] ?? '');
if ($expected === '' || !hash_equals($expected, $csrf)) {
    rateb_account_redirect('error', 'csrf');
}

$userId = (int) $_SESSION['user_id'];
$action = (string) ($_POST['action'] ?? '');
$stmt = db()->prepare('SELECT id, name, email, password_hash FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    header('Location: /login?next=/account');
    exit;
}

if ($action === 'profile') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $name = preg_replace('/\s+/u', ' ', $name) ?? '';
    if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        rateb_account_redirect('error', 'name');
    }
    db()->prepare('UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?')->execute([$name, $userId]);
    rateb_account_redirect('ok', 'profile');
}

if ($action === 'password') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    $stored = (string) ($user['password_hash'] ?? '');
    $hasPassword = $stored !== '';

    if (strlen($new) < 8) {
        rateb_account_redirect('error', 'password_short');
    }
    if (!hash_equals($new, $confirm)) {
        rateb_account_redirect('error', 'password_match');
    }
    if ($hasPassword) {
        if ($current === '' || !password_verify($current, $stored)) {
            rateb_account_redirect('error', 'password_current');
        }
    }
    db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
    rateb_account_redirect('ok', 'password');
}

rateb_account_redirect('error', 'save');
