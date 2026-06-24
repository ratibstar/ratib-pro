<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class Inventory extends Model
{
    protected string $table = 'rateb_inventory';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'warehouse_id', 'item_code', 'item_name', 'sku', 'category', 'category_id', 'barcode', 'qr_code',
        'quantity', 'unit', 'unit_cost', 'reorder_level', 'min_stock', 'max_stock',
        'production_date', 'expiry_date', 'status', 'document_path', 'notes', 'branch_id',
    ];

    /** @return array{0:string,1:array<string,mixed>} */
    protected function branchFilterClause(string $alias = ''): array
    {
        if (!$this->branchScoped || !function_exists('rateb_branch_filter_sql')) {
            return ['', []];
        }
        rateb_bootstrap_branch_context();
        if (\Rateb\App\Core\BranchContext::accessAll()) {
            return ['', []];
        }
        $ids = \Rateb\App\Core\BranchContext::allowedIds();
        if ($ids === []) {
            return [' AND 1=0', []];
        }
        $parts = [];
        $whParts = [];
        $params = [];
        foreach ($ids as $i => $id) {
            $key = 'ibf_' . $i;
            $wkey = 'ibw_' . $i;
            $parts[] = ':' . $key;
            $whParts[] = ':' . $wkey;
            $params[$key] = $id;
            $params[$wkey] = $id;
        }
        $prefix = $alias !== '' ? preg_replace('/[^a-z_]/', '', $alias) . '.' : '';
        $in = implode(',', $parts);
        $whIn = implode(',', $whParts);
        return [
            ' AND (' . $prefix . 'branch_id IN (' . $in . ') OR ' . $prefix . 'warehouse_id IN (SELECT id FROM rateb_warehouses WHERE branch_id IN (' . $whIn . ')))',
            $params,
        ];
    }

    public function totalValue(?int $filterCompanyId = null): float
    {
        $companyId = \Rateb\App\Core\TenantContext::companyId();
        if ($companyId === null) {
            if ($filterCompanyId !== null && $filterCompanyId > 0) {
                $companyId = $filterCompanyId;
            } elseif (\Rateb\App\Core\TenantContext::isSuperAdmin()) {
                $fromQuery = (int) ($_GET['company_id'] ?? $_POST['company_id'] ?? 0);
                if ($fromQuery > 0) {
                    $companyId = $fromQuery;
                }
            }
        }
        if ($companyId === null || $companyId < 1) {
            $row = $this->queryOne('SELECT COALESCE(SUM(quantity * unit_cost), 0) AS v FROM rateb_inventory');
        } else {
            $sql = 'SELECT COALESCE(SUM(quantity * unit_cost), 0) AS v FROM rateb_inventory WHERE company_id = :cid';
            $params = ['cid' => $companyId];
            [$branchSql, $branchParams] = $this->branchFilterClause();
            $sql .= $branchSql;
            $params = array_merge($params, $branchParams);
            $row = $this->queryOne($sql, $params);
        }
        return (float) ($row['v'] ?? 0);
    }
}
