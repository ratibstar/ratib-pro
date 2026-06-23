<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Billing;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class InvoiceService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $tenantId, ?string $status = null): array
    {
        $sql = 'SELECT * FROM rcc_invoices WHERE tenant_id = :tid';
        $params = ['tid' => $tenantId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND status = :st';
            $params['st'] = $status;
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 200';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function find(int $tenantId, int $invoiceId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_invoices WHERE tenant_id = :tid AND id = :id');
        $stmt->execute(['tid' => $tenantId, 'id' => $invoiceId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(int $tenantId, array $data, ?int $userId): array
    {
        $pdo = Database::connection();
        $no = $this->nextInvoiceNo($tenantId);
        $subtotal = (float) ($data['subtotal'] ?? $data['total_amount'] ?? 0);
        $tax = (float) ($data['tax_amount'] ?? 0);
        $total = (float) ($data['total_amount'] ?? ($subtotal + $tax));
        $pdo->prepare(
            'INSERT INTO rcc_invoices (tenant_id, subscription_id, invoice_no, status, currency, subtotal, tax_amount, total_amount, due_at, line_items_json, notes)
             VALUES (:tid, :sid, :no, :st, :cur, :sub, :tax, :tot, :due, :lines, :notes)'
        )->execute([
            'tid' => $tenantId,
            'sid' => $data['subscription_id'] ?? null,
            'no' => $no,
            'st' => (string) ($data['status'] ?? 'open'),
            'cur' => (string) ($data['currency'] ?? 'SAR'),
            'sub' => $subtotal,
            'tax' => $tax,
            'tot' => $total,
            'due' => $data['due_at'] ?? date('Y-m-d H:i:s', strtotime('+7 days')),
            'lines' => isset($data['line_items']) ? json_encode($data['line_items'], JSON_UNESCAPED_UNICODE) : null,
            'notes' => $data['notes'] ?? null,
        ]);
        $id = (int) $pdo->lastInsertId();
        $this->audit->log($tenantId, 'billing.invoice.created', $userId, 'invoice', $id, ['invoice_no' => $no]);
        EventBus::instance()->emit([
            'type' => EventType::BILLING_INVOICE_CREATED,
            'tenant_id' => $tenantId,
            'payload' => ['invoice_id' => $id, 'invoice_no' => $no],
        ]);
        return $this->find($tenantId, $id) ?? [];
    }

    public function markPaid(int $tenantId, int $invoiceId, float $amountPaid, ?int $userId): array
    {
        Database::connection()->prepare(
            "UPDATE rcc_invoices SET status = 'paid', amount_paid = :paid, paid_at = NOW() WHERE tenant_id = :tid AND id = :id"
        )->execute(['paid' => $amountPaid, 'tid' => $tenantId, 'id' => $invoiceId]);
        $this->audit->log($tenantId, 'billing.invoice.paid', $userId, 'invoice', $invoiceId);
        EventBus::instance()->emit([
            'type' => EventType::BILLING_INVOICE_PAID,
            'tenant_id' => $tenantId,
            'payload' => ['invoice_id' => $invoiceId],
        ]);
        return $this->find($tenantId, $invoiceId) ?? [];
    }

    private function nextInvoiceNo(int $tenantId): string
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM rcc_invoices WHERE tenant_id = :tid'
        );
        $stmt->execute(['tid' => $tenantId]);
        $n = (int) $stmt->fetchColumn() + 1;
        return sprintf('INV-%d-%05d', $tenantId, $n);
    }
}
