<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\TenantContext;

final class TenantFkValidator
{
    /** @var array<string, string> */
    private const FIELD_TABLE = [
        'supplier_id' => 'rateb_suppliers',
        'warehouse_id' => 'rateb_warehouses',
        'source_warehouse_id' => 'rateb_warehouses',
        'destination_warehouse_id' => 'rateb_warehouses',
        'category_id' => 'rateb_product_categories',
        'inventory_id' => 'rateb_inventory',
        'rfq_id' => 'rateb_rfq',
        'purchase_order_id' => 'rateb_purchase_orders',
        'employee_id' => 'rateb_employees',
        'department_id' => 'rateb_hr_departments',
        'inventory_id' => 'rateb_inventory',
        'account_id' => 'rateb_chart_of_accounts',
        'leave_type_id' => 'rateb_leave_types',
        'period_id' => 'rateb_payroll_periods',
    ];

    /** @param array<int, string> $fields */
    public static function validate(array $data, array $fields): void
    {
        $companyId = TenantContext::companyId();
        if (($companyId === null || $companyId < 1) && TenantContext::isSuperAdmin()
            && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }
        if ($companyId === null || $companyId < 1) {
            if (TenantContext::isSuperAdmin()) {
                return;
            }
            return;
        }

        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                continue;
            }
            $id = (int) $data[$field];
            if ($id < 1) {
                continue;
            }
            $table = self::FIELD_TABLE[$field] ?? '';
            if ($table === '') {
                continue;
            }
            if (!TenantGuard::belongsToCompany($table, $id, $companyId)) {
                $msg = function_exists('__') ? __('db_fk_violation') : 'Invalid reference for ' . $field . '.';
                throw new \RuntimeException($msg);
            }
        }
    }
}
