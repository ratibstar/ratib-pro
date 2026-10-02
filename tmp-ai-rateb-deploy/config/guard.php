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

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}
