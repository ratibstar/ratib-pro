<?php
declare(strict_types=1);

require __DIR__ . '/../../config/bootstrap.php';
require __DIR__ . '/../plans.php';

header('Content-Type: application/json');

function rateb_google_fail(string $error, int $code): void
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $error]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rateb_google_fail('invalid_login', 405);
}

$token = (string) ($_POST['csrf_token'] ?? '');
$expected = (string) ($_SESSION['csrf_token'] ?? $_SESSION['csrf'] ?? '');
if ($expected === '' || !hash_equals($expected, $token)) {
    rateb_google_fail('invalid_login', 419);
}

$credential = trim((string) ($_POST['credential'] ?? ''));
$google = require __DIR__ . '/../../config/google.php';
$clientId = (string) ($google['client_id'] ?? '');
if ($credential === '' || $clientId === '') {
    rateb_google_fail('google_failed', 503);
}

$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 12,
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$claims = is_string($body) ? json_decode($body, true) : null;
if ($code !== 200 || !is_array($claims)) {
    rateb_google_fail('google_failed', 401);
}

$aud = (string) ($claims['aud'] ?? '');
$email = strtolower(trim((string) ($claims['email'] ?? '')));
$verified = (string) ($claims['email_verified'] ?? '');
$issuer = (string) ($claims['iss'] ?? '');
if ($aud !== $clientId || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($verified, ['true', '1'], true)) {
    rateb_google_fail('google_failed', 401);
}
if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
    rateb_google_fail('google_failed', 401);
}

$name = trim((string) ($claims['name'] ?? ''));
if ($name === '') {
    $name = strstr($email, '@', true) ?: 'RATEB';
}

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
echo json_encode(['ok' => true, 'redirect' => '/dashboard.php']);
