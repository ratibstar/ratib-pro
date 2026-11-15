<?php
declare(strict_types=1);

require_once __DIR__ . '/../../migrations/payment_commerce_schema.php';

function rateb_refund_credits_for_package(string $packageType): int
{
    if ($packageType === 'starter') {
        return 500;
    }
    if ($packageType === 'pro') {
        return 2500;
    }
    return 0;
}

function rateb_refunds_sum_halalas(PDO $pdo, string $start, string $end): int
{
    if (!commerce_table_has($pdo, 'payment_refunds', 'amount_halalas')) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount_halalas),0) FROM payment_refunds WHERE created_at >= ? AND created_at < ?');
    $stmt->execute([$start, $end]);
    return (int) $stmt->fetchColumn();
}

/** @return 'applied'|'duplicate'|'invalid' */
function rateb_record_payment_refund(PDO $pdo, int $receiptId, int $amountHalalas, int $creditsReversed, string $reason, string $source = 'admin', ?string $moyasarId = null): string
{
    ensure_payment_commerce_schema($pdo);
    if ($receiptId < 1 || $amountHalalas < 1) {
        return 'invalid';
    }
    $stmt = $pdo->prepare('SELECT id, user_id, package_type, amount, moyasar_id FROM payment_receipts WHERE id = ? LIMIT 1');
    $stmt->execute([$receiptId]);
    $receipt = $stmt->fetch();
    if (!$receipt) {
        return 'invalid';
    }
    $existing = $pdo->prepare('SELECT COALESCE(SUM(amount_halalas),0) FROM payment_refunds WHERE receipt_id = ?');
    $existing->execute([$receiptId]);
    $already = (int) $existing->fetchColumn();
    if ($already + $amountHalalas > (int) $receipt['amount']) {
        return 'invalid';
    }
    $dup = $pdo->prepare('SELECT id FROM payment_refunds WHERE receipt_id = ? AND amount_halalas = ? AND source = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)');
    $dup->execute([$receiptId, $amountHalalas, $source]);
    if ($dup->fetchColumn()) {
        return 'duplicate';
    }
    $userId = (int) $receipt['user_id'];
    if ($creditsReversed < 1 && $source === 'moyasar') {
        $creditsReversed = rateb_refund_credits_for_package((string) $receipt['package_type']);
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO payment_refunds (receipt_id, user_id, moyasar_id, amount_halalas, credits_reversed, reason, source) VALUES (?,?,?,?,?,?,?)')
            ->execute([
                $receiptId,
                $userId,
                $moyasarId ?? (string) ($receipt['moyasar_id'] ?? ''),
                $amountHalalas,
                $creditsReversed,
                $reason !== '' ? $reason : null,
                $source,
            ]);
        if ($creditsReversed > 0) {
            $pdo->prepare('UPDATE users SET credits_balance = GREATEST(0, credits_balance - ?) WHERE id = ?')
                ->execute([$creditsReversed, $userId]);
            $pdo->prepare("INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted, idempotency_key) VALUES (?, NULL, 'refund_credit', 1, 'completed', 'refund_credit', ?, ?)")
                ->execute([$userId, $creditsReversed, 'refund:R' . $receiptId . ':' . $amountHalalas]);
        }
        $pdo->commit();
        return 'applied';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function rateb_refund_from_moyasar_payment(PDO $pdo, array $payment): string
{
    $paymentId = (string) ($payment['id'] ?? '');
    if (!preg_match('/^[a-f0-9-]{36}$/', $paymentId)) {
        return 'invalid';
    }
    $stmt = $pdo->prepare('SELECT id, amount, package_type FROM payment_receipts WHERE moyasar_id = ? LIMIT 1');
    $stmt->execute([$paymentId]);
    $receipt = $stmt->fetch();
    if (!$receipt) {
        return 'invalid';
    }
    $amount = (int) $receipt['amount'];
    if (isset($payment['amount']) && is_numeric($payment['amount'])) {
        $amount = (int) $payment['amount'];
    }
    return rateb_record_payment_refund(
        $pdo,
        (int) $receipt['id'],
        $amount,
        rateb_refund_credits_for_package((string) $receipt['package_type']),
        'Moyasar refunded',
        'moyasar',
        $paymentId
    );
}