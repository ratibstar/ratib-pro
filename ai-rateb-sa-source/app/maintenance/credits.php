<?php
declare(strict_types=1);

function rateb_text_repair_credit(int $chargedEvents, int $refundEvents): int
{
    $extra = $chargedEvents - $refundEvents - 1;
    return $extra > 0 ? $extra * 5 : 0;
}

function rateb_maintenance_operation_key(string $operation, int $userId, ?int $campaignId): string
{
    if ($operation === 'test_credit_grant') {
        return 'test_credit_grant:user:' . $userId;
    }
    return 'text_campaign_repair:user:' . $userId . ':campaign:' . (int) $campaignId;
}

/**
 * @return string applied|duplicate|dry-run|missing-table
 */
function rateb_maintenance_grant(object $pdo, string $operation, int $userId, ?int $campaignId, int $amount, string $reason, bool $dryRun): string
{
    if (!in_array($operation, ['text_campaign_repair', 'test_credit_grant'], true) || $userId < 1 || $amount < 1) {
        return 'refused';
    }
    $key = rateb_maintenance_operation_key($operation, $userId, $campaignId);
    if ($dryRun) {
        return 'dry-run';
    }
    $pdo->beginTransaction();
    try {
        $priorUsage = $pdo->prepare('SELECT id FROM usage_events WHERE user_id = ? AND operation = ? AND (campaign_id <=> ? OR (? IS NULL AND campaign_id IS NULL)) LIMIT 1');
        $priorUsage->execute([$userId, $operation, $campaignId, $campaignId]);
        if ($priorUsage->fetch()) {
            $pdo->commit();
            return 'duplicate';
        }
        $pdo->prepare('INSERT INTO maintenance_grants (operation_key, user_id, campaign_id, amount, reason) VALUES (?, ?, ?, ?, ?)')
            ->execute([$key, $userId, $campaignId, $amount, mb_substr($reason, 0, 500)]);
        $pdo->prepare('UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?')->execute([$amount, $userId]);
        $pdo->prepare("INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted) VALUES (?, ?, ?, 1, 'completed', ?, ?)")
            ->execute([$userId, $campaignId, $operation, $operation, -$amount]);
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
