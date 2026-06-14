<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;
use Rateb\App\Core\TenantContext;

final class PurchaseOrder extends Model
{
    protected string $table = 'rateb_purchase_orders';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'order_no', 'barcode', 'qr_code', 'supplier_id', 'cost_center_id', 'warehouse_id',
        'purchase_request_id', 'quotation_id', 'status', 'order_date',
        'expected_date', 'subtotal', 'tax_amount', 'total_amount', 'currency',
        'discount_amount', 'shipping_amount', 'notes', 'notes_history',
    ];

    public function generateOrderNo(): string
    {
        $companyId = TenantContext::companyId() ?? 0;
        $count = (int) ($this->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_purchase_orders WHERE company_id = :cid',
            ['cid' => $companyId]
        )['c'] ?? 0);
        return 'PO-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
