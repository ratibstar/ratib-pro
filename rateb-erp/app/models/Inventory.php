<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class Inventory extends Model
{
    protected string $table = 'rateb_inventory';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'warehouse_id', 'item_name', 'sku', 'category', 'category_id', 'barcode', 'qr_code',
        'quantity', 'unit', 'unit_cost', 'reorder_level', 'min_stock', 'max_stock',
        'expiry_date', 'status',
    ];

    public function totalValue(): float
    {
        $companyId = \Rateb\App\Core\TenantContext::companyId();
        if ($companyId === null) {
            $row = $this->queryOne('SELECT COALESCE(SUM(quantity * unit_cost), 0) AS v FROM rateb_inventory');
        } else {
            $row = $this->queryOne(
                'SELECT COALESCE(SUM(quantity * unit_cost), 0) AS v FROM rateb_inventory WHERE company_id = :cid',
                ['cid' => $companyId]
            );
        }
        return (float) ($row['v'] ?? 0);
    }
}
