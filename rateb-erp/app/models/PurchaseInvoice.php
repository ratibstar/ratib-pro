<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class PurchaseInvoice extends Model
{
    protected string $table = 'rateb_purchase_invoices';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'purchase_order_id', 'supplier_id', 'invoice_no', 'invoice_date', 'status', 'currency',
        'line_subtotal', 'discount_amount', 'tax_amount', 'shipping_amount', 'customs_clearance_amount',
        'total_amount', 'customs_declaration_no', 'customs_clearance_date', 'customs_broker_id',
        'customs_clearance_status', 'notes',
    ];

    public function generateInvoiceNo(): string
    {
        return $this->nextSequentialNo('PI-', 'invoice_no');
    }

    /** @return array<string, mixed>|null */
    public function findByPurchaseOrderId(int $purchaseOrderId): ?array
    {
        $row = $this->queryOne(
            "SELECT * FROM {$this->table} WHERE purchase_order_id = :po LIMIT 1",
            ['po' => $purchaseOrderId]
        );
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function listCustomsClearance(int $limit = 100, int $offset = 0, string $search = ''): array
    {
        $sql = "SELECT pi.*, po.order_no, po.order_date, po.status AS po_status
                FROM {$this->table} pi
                INNER JOIN rateb_purchase_orders po ON po.id = pi.purchase_order_id
                WHERE 1=1" . $this->customsScopeSql();
        $params = $this->customsScopeParams();
        $sql .= $this->buildSearchClause($search, $params, 'pi');
        $sql .= ' ORDER BY ' . $this->listOrderSql('pi') . ' LIMIT :limit OFFSET :offset';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function countCustomsClearance(string $search = ''): int
    {
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} pi WHERE 1=1" . $this->customsScopeSql();
        $params = $this->customsScopeParams();
        $sql .= $this->buildSearchClause($search, $params, 'pi');
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /** @return array<string, mixed> */
    private function customsScopeParams(): array
    {
        [, $extraParams] = $this->tenantFilterClause('pi');
        return $extraParams;
    }

    private function customsScopeSql(): string
    {
        [$tenantSql] = $this->tenantFilterClause('pi');
        return $tenantSql;
    }
}
