<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;
use Rateb\App\Core\TenantContext;

final class PurchaseRequest extends Model
{
    protected string $table = 'rateb_purchase_requests';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'request_no', 'title', 'department', 'priority', 'status',
        'requested_by', 'approved_by', 'total_estimated', 'notes', 'notes_history',
    ];

    public function generateRequestNo(): string
    {
        $companyId = TenantContext::companyId() ?? 0;
        $count = (int) ($this->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_purchase_requests WHERE company_id = :cid',
            ['cid' => $companyId]
        )['c'] ?? 0);
        return 'PR-' . str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    }
}
