<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class PurchaseOrder extends Model
{
    protected string $table = 'rateb_purchase_orders';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'order_no', 'barcode', 'qr_code', 'supplier_id', 'cost_center_id', 'warehouse_id',
        'purchase_request_id', 'quotation_id', 'status', 'order_date',
        'expected_date', 'subtotal', 'tax_amount', 'total_amount', 'currency',
        'discount_amount', 'shipping_amount', 'customs_clearance_amount',
        'customs_declaration_no', 'customs_clearance_date', 'customs_broker_id', 'customs_clearance_status',
        'notes', 'notes_history',
    ];

    public function generateOrderNo(): string
    {
        return $this->nextSequentialNo('PO-', 'order_no');
    }

    /** @return array<int, array<string, mixed>> */
    public function listCustomsClearance(int $limit = 100, int $offset = 0, string $search = ''): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1" . $this->customsScopeSql();
        $params = $this->customsScopeParams();
        $sql .= $this->buildSearchClause($search, $params);
        $sql .= ' ORDER BY ' . $this->listOrderSql() . ' LIMIT :limit OFFSET :offset';
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
        $sql = "SELECT COUNT(*) AS c FROM {$this->table} WHERE 1=1" . $this->customsScopeSql();
        $params = $this->customsScopeParams();
        $sql .= $this->buildSearchClause($search, $params);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /** @return array<string, mixed> */
    private function customsScopeParams(): array
    {
        [, $extraParams] = $this->tenantFilterClause();
        return $extraParams;
    }

    private function customsScopeSql(): string
    {
        [$tenantSql, ] = $this->tenantFilterClause();
        return $tenantSql . " AND (
            customs_clearance_amount > 0
            OR (customs_declaration_no IS NOT NULL AND customs_declaration_no <> '')
            OR (customs_clearance_status IS NOT NULL AND customs_clearance_status <> '')
        )";
    }
}
