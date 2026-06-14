<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class PurchaseItem extends Model
{
    protected string $table = 'rateb_purchase_items';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'purchase_order_id', 'inventory_id', 'item_name', 'description', 'sku', 'quantity', 'delivered_qty',
        'invoiced_qty', 'unit', 'unit_price', 'tax_name', 'tax_rate', 'excluding_tax', 'total_price',
    ];
}
