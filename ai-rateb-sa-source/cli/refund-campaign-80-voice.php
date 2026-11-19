<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No database\n");
    exit(1);
}

$key = 'refund_included_voice:campaign:80';
$seen = $pdo->prepare('SELECT id FROM usage_events WHERE idempotency_key = ? LIMIT 1');
$seen->execute([$key]);
if ($seen->fetch()) {
    echo "voice_refund=already\n";
    exit(0);
}

$charge = $pdo->prepare("SELECT id, user_id, credits_deducted FROM usage_events WHERE campaign_id = 80 AND operation = 'saudi_voice' AND status = 'completed' AND credits_deducted > 0 ORDER BY id DESC LIMIT 1");
$charge->execute();
$row = $charge->fetch();
if (!$row) {
    echo "voice_refund=none\n";
    exit(0);
}

$amount = (int) $row['credits_deducted'];
$userId = (int) $row['user_id'];
$pdo->prepare('UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?')->execute([$amount, $userId]);
$pdo->prepare("INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted, idempotency_key) VALUES (?, 80, 'saudi_voice_refund', 1, 'completed', 'saudi_voice_refund', 0, ?)")
    ->execute([$userId, $key]);
echo "voice_refund={$amount} user={$userId}\n";
