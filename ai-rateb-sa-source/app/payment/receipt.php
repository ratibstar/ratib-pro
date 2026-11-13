<?php
declare(strict_types=1);

/**
 * Record one paid Moyasar receipt. A second call with the same moyasar id does not add credits.
 *
 * @return string applied|duplicate
 */
function rateb_record_moyasar_receipt(object $pdo, string $paymentId, int $userId, string $package, int $amount, int $credits): string
{
    $pdo->beginTransaction();
    try {
        $known = $pdo->prepare('SELECT id FROM payment_receipts WHERE moyasar_id = ?');
        $known->execute([$paymentId]);
        if ($known->fetch()) {
            $pdo->commit();
            return 'duplicate';
        }
        $pdo->prepare('INSERT INTO payment_receipts (moyasar_id, user_id, package_type, amount) VALUES (?, ?, ?, ?)')
            ->execute([$paymentId, $userId, $package, $amount]);
        $pdo->prepare('UPDATE users SET package_type = ?, credits_balance = credits_balance + ? WHERE id = ?')
            ->execute([$package, $credits, $userId]);
        $pdo->prepare("INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted) VALUES (?, NULL, ?, 1, 'completed', ?, 0)")
            ->execute([$userId, 'payment_' . $package, 'payment_' . $package]);
        $pdo->commit();
        return 'applied';
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($error instanceof PDOException && (string) $error->getCode() === '23000') {
            return 'duplicate';
        }
        throw $error;
    }
}
