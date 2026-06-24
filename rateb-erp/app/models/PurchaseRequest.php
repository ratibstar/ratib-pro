<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class PurchaseRequest extends Model
{
    protected string $table = 'rateb_purchase_requests';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'request_no', 'title', 'department', 'priority', 'status', 'expected_date',
        'requested_by', 'approved_by', 'total_estimated', 'currency', 'notes', 'notes_history', 'branch_id',
    ];

    public function generateRequestNo(): string
    {
        return $this->nextSequentialNo('PR-', 'request_no');
    }
}
