<?php
declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';
require __DIR__ . '/../plans.php';

$github = require __DIR__ . '/../../config/github.php';
$local = __DIR__ . '/../../config/github.local.php';
if (is_file($local)) {
    $extra = require $local;
    if (is_array($extra)) {
        $github = array_merge($github, $extra);
    }
}
$clientId = (string) ($github['client_id'] ?? '');
$clientSecret = (string) ($github['client_secret'] ?? '');
$redirect = 'https://ai.rateb.sa/app/auth/github.php';

function rateb_github_user(PDO $pdo, string $email, string $name): void
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    if ($existing) {
        $userId = (int) $existing['id'];
    } else {
        $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
        $insert = $pdo->prepare('INSERT INTO users (name, email, password_hash, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $insert->execute([$name, $email, $hash]);
        $userId = (int) $pdo->lastInsertId();
        rateb_user_plan($pdo, $userId);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    header('Location: /dashboard.php');
    exit;
}

$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    if ($clientId === '' || $clientSecret === '') {
        header('Content-Type: text/plain; charset=utf-8', true, 503);
        echo 'GitHub client secret is not configured on the server.';
        exit;
    }
    $state = bin2hex(random_bytes(16));
    $_SESSION['github_oauth_state'] = $state;
    $url = 'https://github.com/login/oauth/authorize?' . http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirect,
        'scope' => 'read:user user:email',
        'state' => $state,
    ]);
    header('Location: ' . $url);
    exit;
}

$state = (string) ($_GET['state'] ?? '');
$expected = (string) ($_SESSION['github_oauth_state'] ?? '');
unset($_SESSION['github_oauth_state']);
if ($expected === '' || !hash_equals($expected, $state) || $clientSecret === '') {
    header('Location: /?auth_error=github');
    exit;
}

$ch = curl_init('https://github.com/login/oauth/access_token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: RATEB-AI'],
    CURLOPT_POSTFIELDS => http_build_query([
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'code' => $code,
        'redirect_uri' => $redirect,
    ]),
]);
$tokenBody = curl_exec($ch);
curl_close($ch);
$token = is_string($tokenBody) ? json_decode($tokenBody, true) : null;
$access = is_array($token) ? (string) ($token['access_token'] ?? '') : '';
if ($access === '') {
    header('Location: /?auth_error=github');
    exit;
}

$ch = curl_init('https://api.github.com/user/emails');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json', 'Authorization: Bearer ' . $access, 'User-Agent: RATEB-AI'],
]);
$emailBody = curl_exec($ch);
curl_close($ch);
$emails = is_string($emailBody) ? json_decode($emailBody, true) : null;
$email = '';
if (is_array($emails)) {
    foreach ($emails as $row) {
        if (!empty($row['primary']) && !empty($row['verified'])) {
            $email = strtolower((string) $row['email']);
            break;
        }
    }
}
$ch = curl_init('https://api.github.com/user');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json', 'Authorization: Bearer ' . $access, 'User-Agent: RATEB-AI'],
]);
$userBody = curl_exec($ch);
curl_close($ch);
$profile = is_string($userBody) ? json_decode($userBody, true) : null;
if ($email === '' && is_array($profile)) {
    $email = strtolower(trim((string) ($profile['email'] ?? '')));
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /?auth_error=github');
    exit;
}
$name = trim((string) (is_array($profile) ? ($profile['name'] ?? $profile['login'] ?? '') : ''));
if ($name === '') {
    $name = strstr($email, '@', true) ?: 'RATEB';
}
rateb_github_user($pdo, $email, $name);
