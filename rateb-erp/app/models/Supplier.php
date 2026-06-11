<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class Supplier extends Model
{
    protected string $table = 'rateb_suppliers';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'name', 'code', 'email', 'phone', 'address', 'rating', 'status', 'notes',
    ];
}
