<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Support\PosBranchScope;

final class PosReturnsController extends PosBaseController
{
    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosPermission('pos.returns.manage', 'pos/returns');
        $companyId = $this->companyId();
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        [$branchSql, $branchParams] = PosBranchScope::readFilterSql('o');
        $stmt = $db->prepare(
            'SELECT o.*, orig.order_no AS original_order_no
             FROM rateb_pos_orders o
             LEFT JOIN rateb_pos_orders orig ON orig.id = o.original_order_id
             WHERE o.company_id = :cid AND o.order_type IN (\'return\', \'exchange\')' . $branchSql . '
             ORDER BY o.id DESC LIMIT 100'
        );
        $stmt->execute(array_merge(['cid' => $companyId], $branchParams));
        $this->posView('returns/index', [
            'title' => __('pos_returns'),
            'items' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
        ]);
    }
}
