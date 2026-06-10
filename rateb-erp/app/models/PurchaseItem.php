<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class PurchaseItem extends Model
{
    protected string $table = 'rateb_purchase_items';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'purchase_order_id', 'item_name', 'sku', 'quantity', 'unit', 'unit_price', 'total_price',
    ];
}
