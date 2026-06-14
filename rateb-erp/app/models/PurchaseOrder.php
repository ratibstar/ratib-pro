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
        'discount_amount', 'shipping_amount', 'notes', 'notes_history',
    ];

    public function generateOrderNo(): string
    {
        return $this->nextSequentialNo('PO-', 'order_no');
    }
}
