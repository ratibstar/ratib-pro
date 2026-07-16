<?php
declare(strict_types=1);
$_SERVER['HTTP_HOST'] = 'rateb.sa';
define('RATEB_ROOT', '/home/admin/domains/rateb.sa/public_html/rateb-erp');
require_once RATEB_ROOT . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
require_once RATEB_ROOT . '/config/database.php';
$email = trim((string) ($argv[1] ?? ''));
if ($email === '') {
    fwrite(STDERR, "usage: unlock-user.php email\n");
    exit(1);
}
$pdo = \Rateb\App\Core\Database::connection();
$st = $pdo->prepare('SELECT id, email, locked_until, failed_attempts FROM rateb_users WHERE email = ? LIMIT 1');
$st->execute([$email]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'user_missing', 'email' => $email]);
    exit(1);
}
$pdo->prepare('UPDATE rateb_users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')
    ->execute([(int) $row['id']]);
echo json_encode([
    'ok' => true,
    'id' => (int) $row['id'],
    'email' => $row['email'],
    'was_locked_until' => $row['locked_until'],
    'was_failed_attempts' => (int) $row['failed_attempts'],
], JSON_UNESCAPED_SLASHES);
