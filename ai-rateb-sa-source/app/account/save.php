<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_login();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /account');
    exit;
}

$csrf = (string) ($_POST['csrf_token'] ?? $_POST['csrf'] ?? '');
$expected = (string) ($_SESSION['csrf_token'] ?? $_SESSION['csrf'] ?? '');
if ($expected === '' || !hash_equals($expected, $csrf)) {
    header('Location: /account?error=csrf');
    exit;
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
        header('Location: /account?error=name');
        exit;
    }
    db()->prepare('UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?')->execute([$name, $userId]);
    header('Location: /account?ok=profile');
    exit;
}

if ($action === 'password') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    $stored = (string) ($user['password_hash'] ?? '');
    $hasPassword = $stored !== '';

    if (strlen($new) < 8) {
        header('Location: /account?error=password_short');
        exit;
    }
    if (!hash_equals($new, $confirm)) {
        header('Location: /account?error=password_match');
        exit;
    }
    if ($hasPassword) {
        if ($current === '' || !password_verify($current, $stored)) {
            header('Location: /account?error=password_current');
            exit;
        }
    }
    db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
    header('Location: /account?ok=password');
    exit;
}

header('Location: /account?error=save');
exit;
