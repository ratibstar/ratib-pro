<?php
declare(strict_types=1);

/**
 * CLI only — set platform owner password on ai.rateb.sa (run on server via SSH).
 * RATEB_OWNER_RESET_PASSWORD=yourpass php cli/set-platform-owner-password.php admin@rateb.sa
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require dirname(__DIR__) . '/config/bootstrap.php';

$email = strtolower(trim($argv[1] ?? 'admin@rateb.sa'));
$password = (string) (getenv('RATEB_OWNER_RESET_PASSWORD') ?: ($argv[2] ?? ''));

if ($email === '' || !str_contains($email, '@') || $password === '') {
    fwrite(STDERR, "Usage: RATEB_OWNER_RESET_PASSWORD=secret php cli/set-platform-owner-password.php admin@rateb.sa\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$row = $stmt->fetch();

if ($row) {
    $pdo->prepare("UPDATE users SET password_hash = ?, role = 'owner' WHERE id = ?")->execute([$hash, (int) $row['id']]);
    echo "owner_password_updated id=" . (int) $row['id'] . " email={$email}\n";
    exit(0);
}

$name = explode('@', $email)[0];
$pdo->prepare('INSERT INTO users (name, email, password_hash, role, credits_balance, created_at, updated_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())')
    ->execute([$name, $email, $hash, 'owner']);
echo "owner_password_created email={$email}\n";