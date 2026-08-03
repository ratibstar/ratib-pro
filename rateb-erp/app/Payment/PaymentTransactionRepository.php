<?php
declare(strict_types=1);

namespace Rateb\App\Payment;

use PDO;
use Rateb\App\Core\Database;

final class PaymentTransactionRepository
{
    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM rateb_payment_transactions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByIdForUpdate(int $id): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM rateb_payment_transactions WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByCallbackToken(string $token): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM rateb_payment_transactions WHERE callback_token = :tok LIMIT 1');
        $stmt->execute(['tok' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByExternalId(string $gatewaySlug, string $externalId): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_payment_transactions WHERE gateway_slug = :gw AND external_id = :ext ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['gw' => $gatewaySlug, 'ext' => $externalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findPendingForInvoice(int $invoiceId, int $companyId): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT * FROM rateb_payment_transactions
             WHERE invoice_id = :iid AND company_id = :cid
               AND status IN ('pending','processing')
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute(['iid' => $invoiceId, 'cid' => $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_payment_transactions
             (company_id, invoice_id, gateway_slug, external_id, idempotency_key, amount, currency, status,
              redirect_url, callback_token, raw_request_json, raw_response_json, initiated_at)
             VALUES (:cid, :iid, :gw, :ext, :idem, :amt, :cur, :st, :redir, :cb, :req, :res, NOW())'
        )->execute([
            'cid' => (int) $data['company_id'],
            'iid' => (int) $data['invoice_id'],
            'gw' => (string) $data['gateway_slug'],
            'ext' => $data['external_id'] ?? null,
            'idem' => (string) $data['idempotency_key'],
            'amt' => (float) $data['amount'],
            'cur' => (string) ($data['currency'] ?? 'SAR'),
            'st' => (string) ($data['status'] ?? 'pending'),
            'redir' => $data['redirect_url'] ?? null,
            'cb' => (string) $data['callback_token'],
            'req' => $data['raw_request_json'] ?? null,
            'res' => $data['raw_response_json'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    /** @param array<string, mixed> $fields */
    public function update(int $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }
        $sets = [];
        $params = ['id' => $id];
        foreach ($fields as $k => $v) {
            $sets[] = $k . ' = :' . $k;
            $params[$k] = $v;
        }
        $db = Database::connection();
        $db->prepare('UPDATE rateb_payment_transactions SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    }

    /** @return list<array<string, mixed>> */
    public function listRecent(?int $companyId = null, ?string $status = null, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $db = Database::connection();
        $sql = 'SELECT t.*, i.invoice_no FROM rateb_payment_transactions t
                LEFT JOIN rateb_invoices i ON i.id = t.invoice_id WHERE 1=1';
        $params = [];
        if ($companyId !== null) {
            $sql .= ' AND t.company_id = :cid';
            $params['cid'] = $companyId;
        }
        if ($status !== null && $status !== '') {
            $sql .= ' AND t.status = :st';
            $params['st'] = $status;
        }
        $sql .= ' ORDER BY t.id DESC LIMIT ' . $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function findWebhookByEventId(string $gatewaySlug, string $eventId): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_payment_webhooks WHERE gateway_slug = :gw AND event_id = :eid LIMIT 1'
        );
        $stmt->execute(['gw' => $gatewaySlug, 'eid' => $eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function createWebhook(array $data): int
    {
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_payment_webhooks
             (gateway_slug, event_id, transaction_id, signature_valid, payload_hash, status, payload_json, client_ip, received_at)
             VALUES (:gw, :eid, :tid, :sig, :hash, :st, :payload, :ip, NOW())'
        )->execute([
            'gw' => (string) $data['gateway_slug'],
            'eid' => (string) $data['event_id'],
            'tid' => $data['transaction_id'] ?? null,
            'sig' => !empty($data['signature_valid']) ? 1 : 0,
            'hash' => (string) $data['payload_hash'],
            'st' => (string) ($data['status'] ?? 'received'),
            'payload' => $data['payload_json'] ?? null,
            'ip' => $data['client_ip'] ?? null,
        ]);

        return (int) $db->lastInsertId();
    }

    public function markWebhookProcessed(int $id, string $status = 'processed'): void
    {
        $db = Database::connection();
        $db->prepare('UPDATE rateb_payment_webhooks SET status = :st, processed_at = NOW() WHERE id = :id')
            ->execute(['st' => $status, 'id' => $id]);
    }
}
