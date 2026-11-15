<?php
declare(strict_types=1);

require_once __DIR__ . '/../plans.php';
require_once __DIR__ . '/../notify/events.php';

/** @return array<string,mixed>|null */
function rateb_moyasar_api_get(string $path, string $secret): ?array
{
    $secret = trim($secret);
    if ($secret === '' || !preg_match('#^[a-z0-9/_?=&%.-]+$#i', $path)) {
        return null;
    }
    $ch = curl_init('https://api.moyasar.com/v1/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $secret . ':',
        CURLOPT_TIMEOUT => 14,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http < 200 || $http >= 300) {
        return null;
    }
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : null;
}

/** @param array<string,mixed> $invoice */
function rateb_moyasar_payment_ids_from_invoice(array $invoice): array
{
    $ids = [];
    $candidates = [];
    foreach (['payment_id', 'paymentId'] as $key) {
        if (isset($invoice[$key]) && is_string($invoice[$key]) && $invoice[$key] !== '') {
            $candidates[] = $invoice[$key];
        }
    }
    foreach (['payments', 'payment_ids'] as $key) {
        if (!isset($invoice[$key]) || !is_array($invoice[$key])) {
            continue;
        }
        foreach ($invoice[$key] as $item) {
            if (is_string($item) && $item !== '') {
                $candidates[] = $item;
            } elseif (is_array($item) && isset($item['id']) && is_string($item['id'])) {
                $candidates[] = $item['id'];
            }
        }
    }
    foreach ($candidates as $id) {
        if (preg_match('/^[a-f0-9-]{36}$/', $id)) {
            $ids[$id] = true;
        }
    }
    return array_keys($ids);
}

/** @return list<string> */
function rateb_moyasar_payments_for_invoice(string $invoiceId, string $secret): array
{
    if (!preg_match('/^[a-f0-9-]{36}$/', $invoiceId)) {
        return [];
    }
    $ids = [];
    $invoice = rateb_moyasar_api_get('invoices/' . rawurlencode($invoiceId), $secret);
    if (is_array($invoice)) {
        foreach (rateb_moyasar_payment_ids_from_invoice($invoice) as $id) {
            $ids[$id] = true;
        }
    }
    $listed = rateb_moyasar_api_get('payments?invoice_id=' . rawurlencode($invoiceId), $secret);
    if (is_array($listed) && isset($listed['payments']) && is_array($listed['payments'])) {
        foreach ($listed['payments'] as $payment) {
            if (is_array($payment) && isset($payment['id']) && is_string($payment['id'])) {
                $ids[$payment['id']] = true;
            }
        }
    }
    return array_keys($ids);
}

/**
 * Try to settle a pending order from Moyasar when the webhook is delayed.
 *
 * @return 'paid'|'pending'|'failed'|'missing'|'unconfigured'|'not_found'
 */
function rateb_payment_sync_order(PDO $pdo, int $orderId, int $userId, bool $notifyOnPaid = true): string
{
    if ($orderId < 1 || $userId < 1) {
        return 'not_found';
    }
    $stmt = $pdo->prepare('SELECT id, user_id, status, moyasar_invoice_id, package_code FROM payment_orders WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();
    if (!$order) {
        return 'not_found';
    }
    $status = (string) $order['status'];
    if ($status === 'paid') {
        return 'paid';
    }
    if ($status === 'failed') {
        return 'failed';
    }
    $invoiceId = trim((string) ($order['moyasar_invoice_id'] ?? ''));
    if ($invoiceId === '') {
        return 'pending';
    }
    $cfg = require __DIR__ . '/../../config/ai.php';
    $secret = (string) ($cfg['moyasar_secret_key'] ?? '');
    if (!rateb_moyasar_configured($cfg)) {
        return 'unconfigured';
    }

    foreach (rateb_moyasar_payments_for_invoice($invoiceId, $secret) as $paymentId) {
        $payment = rateb_moyasar_api_get('payments/' . rawurlencode($paymentId), $secret);
        if (!is_array($payment) || (string) ($payment['id'] ?? '') !== $paymentId) {
            continue;
        }
        if ((string) ($payment['status'] ?? '') !== 'paid') {
            continue;
        }
        $result = rateb_payment_record($pdo, $payment);
        if ($result === 'ok') {
            if ($notifyOnPaid) {
                $meta = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
                $code = (string) ($meta['package'] ?? (string) $order['package_code']);
                $receiptStmt = $pdo->prepare('SELECT id, amount FROM payment_receipts WHERE moyasar_id = ? LIMIT 1');
                $receiptStmt->execute([$paymentId]);
                $receiptRow = $receiptStmt->fetch();
                if ($receiptRow) {
                    rateb_notify_payment_paid($pdo, $userId, (int) $receiptRow['id'], $code, (int) $receiptRow['amount']);
                }
            }
            return 'paid';
        }
        if ($result === 'duplicate') {
            return 'paid';
        }
    }

    $invoice = rateb_moyasar_api_get('invoices/' . rawurlencode($invoiceId), $secret);
    if (is_array($invoice)) {
        $invStatus = strtolower((string) ($invoice['status'] ?? ''));
        if (in_array($invStatus, ['expired', 'canceled', 'cancelled', 'voided', 'failed'], true)) {
            rateb_payment_mark_failed($pdo, $orderId, $userId);
            return 'failed';
        }
    }

    return 'pending';
}

/** @return array{checked:int,paid:int,failed:int,pending:int} */
function rateb_payment_reconcile_pending(PDO $pdo, int $maxAgeDays = 7, int $limit = 40): array
{
    $maxAgeDays = max(1, min(30, $maxAgeDays));
    $limit = max(1, min(200, $limit));
    $stmt = $pdo->prepare("SELECT id, user_id FROM payment_orders WHERE status = 'pending' AND moyasar_invoice_id IS NOT NULL AND moyasar_invoice_id <> '' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY id ASC LIMIT {$limit}");
    $stmt->execute([$maxAgeDays]);
    $counts = ['checked' => 0, 'paid' => 0, 'failed' => 0, 'pending' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $counts['checked']++;
        $outcome = rateb_payment_sync_order($pdo, (int) $row['id'], (int) $row['user_id'], true);
        if (isset($counts[$outcome])) {
            $counts[$outcome]++;
        } else {
            $counts['pending']++;
        }
    }
    return $counts;
}