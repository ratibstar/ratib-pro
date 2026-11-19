<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No database\n");
    exit(1);
}

$key = 'restore_balance_65:campaign:80:second';
$seen = $pdo->prepare('SELECT id FROM usage_events WHERE idempotency_key = ? LIMIT 1');
$seen->execute([$key]);
if ($seen->fetch()) {
    echo "restore_credits=already\n";
    exit(0);
}

$userId = (int) $pdo->query('SELECT user_id FROM campaigns WHERE id = 80 LIMIT 1')->fetchColumn();
if ($userId < 1) {
    echo "restore_credits=campaign_missing\n";
    exit(0);
}

$balance = $pdo->prepare('SELECT credits_balance FROM users WHERE id = ?');
$balance->execute([$userId]);
$current = (int) $balance->fetchColumn();
if ($current >= 65) {
    echo "restore_credits=kept current={$current}\n";
    exit(0);
}

$pdo->prepare('UPDATE users SET credits_balance = 65 WHERE id = ?')->execute([$userId]);
$pdo->prepare("INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted, idempotency_key) VALUES (?, 80, 'credit_restore', 1, 'completed', 'credit_restore', 0, ?)")
    ->execute([$userId, $key]);
echo "restore_credits=65 user={$userId} previous={$current}\n";
