<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class BranchTransfer extends Model
{
    protected string $table = 'rateb_branch_transfers';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected string $branchColumn = 'source_branch_id';
    protected array $fillable = [
        'company_id', 'transfer_no', 'transfer_type', 'source_branch_id', 'dest_branch_id',
        'source_entity_type', 'source_entity_id', 'quantity', 'amount', 'status', 'notes',
        'payload_json', 'created_by', 'approved_by', 'completed_at',
    ];

    public function generateTransferNo(): string
    {
        return $this->nextSequentialNo('IBT-', 'transfer_no');
    }
}
