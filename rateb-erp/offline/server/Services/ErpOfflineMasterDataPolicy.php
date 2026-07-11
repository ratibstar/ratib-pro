<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Offline\OfflineModule;

/**
 * Phase 13 — Master-data delta policy (read-only).
 */
final class ErpOfflineMasterDataPolicy
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @return array<string, mixed> */
    private function config(): array
    {
        if (self::$config === null) {
            $file = OfflineModule::rootPath() . '/config/master-data-entities.php';
            self::$config = is_file($file) ? require $file : [];
        }

        return self::$config;
    }

    public function isEnabled(): bool
    {
        return (new OfflineFeatureFlagService())->isMasterDataEnabled();
    }

    /**
     * Resolve canonical entity name or null if not in master-data allowlist.
     */
    public function resolveCanonical(string $entity): ?string
    {
        $entity = trim($entity);
        if ($entity === '') {
            return null;
        }
        $entities = is_array($this->config()['entities'] ?? null) ? $this->config()['entities'] : [];
        if (isset($entities[$entity])) {
            return $entity;
        }
        foreach ($entities as $canonical => $meta) {
            $aliases = is_array($meta['aliases'] ?? null) ? $meta['aliases'] : [];
            if (in_array($entity, $aliases, true)) {
                return (string) $canonical;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function allowedCanonicalNames(): array
    {
        $entities = is_array($this->config()['entities'] ?? null) ? $this->config()['entities'] : [];

        return array_keys($entities);
    }

    public function isLegacyTier1Entity(string $entity): bool
    {
        return in_array($entity, [
            'inventory_catalog', 'inventory', 'catalog',
            'employee_directory', 'employees', 'hr_employees',
            'supplier_directory', 'suppliers', 'procurement_suppliers',
            'recruitment_agency_directory', 'recruitment_agencies', 'agencies',
            'chart_of_accounts_directory', 'chart_of_accounts', 'accounts', 'coa',
            'accounting_currency_directory', 'accounting_currencies', 'currencies',
            'accounting_exchange_rate_directory', 'accounting_exchange_rates', 'exchange_rates',
            'accounting_tax_code_directory', 'accounting_tax_codes', 'tax_codes',
            'accounting_cost_center_directory', 'cost_centers', 'cost_center',
            'accounting_profit_center_directory', 'profit_centers', 'profit_center',
            'accounting_fiscal_period_directory', 'fiscal_periods', 'fiscal_period',
            'crm_lead_source_directory', 'crm_lead_sources', 'lead_sources',
            'crm_pipeline_stage_directory', 'crm_pipeline_stages', 'pipeline_stages',
            'crm_tag_directory', 'crm_tags',
            'crm_company_directory', 'crm_companies', 'crm_accounts',
            'project_tag_directory', 'project_tags',
            'project_role_directory', 'project_roles',
            'project_type_directory', 'project_types',
            'milestone_type_directory', 'milestone_types',
            'task_status_directory', 'project_task_statuses',
            'issue_type_directory', 'project_issue_types',
            'risk_level_directory', 'project_risk_levels',
            'asset_category_directory', 'asset_categories',
            'asset_manufacturer_directory', 'asset_manufacturers',
            'asset_location_directory', 'asset_locations',
            'asset_model_directory', 'asset_models',
            'maintenance_plan_directory', 'maintenance_plans',
            'asset_status_directory', 'asset_statuses',
            'maintenance_request_status_directory', 'maintenance_request_statuses',
            'approval_template_directory', 'approval_templates',
            'approval_chain_directory', 'approval_chains',
            'approval_stage_directory', 'approval_stages',
            'approval_rule_directory', 'approval_rules',
            'approval_delegation_directory', 'approval_delegations',
            'approval_status_directory', 'approval_statuses',
            'eproc_supplier_category_directory', 'eproc_supplier_categories',
            'eproc_rfq_template_directory', 'eproc_rfq_templates',
            'eproc_tag_directory', 'eproc_tags',
            'eproc_supplier_profile_status_directory', 'eproc_supplier_statuses',
            'eproc_tender_status_directory', 'eproc_tender_statuses',
            'eproc_contract_status_directory', 'eproc_contract_statuses',
            'mfg_product_directory', 'mfg_products',
            'mfg_work_center_directory', 'mfg_work_centers',
            'mfg_bom_status_directory', 'mfg_bom_statuses',
            'mfg_production_order_status_directory', 'mfg_production_order_statuses',
            'mfg_work_order_status_directory', 'mfg_work_order_statuses',
            'hrm_department_directory', 'hrm_departments',
            'hrm_position_directory', 'hrm_positions',
            'hrm_employee_profile_directory', 'hrm_employee_profiles',
            'hrm_employee_status_directory', 'hrm_employee_statuses',
            'hrm_training_status_directory', 'hrm_training_statuses',
            'hrm_performance_status_directory', 'hrm_performance_statuses',
            'payroll_structure_directory', 'payroll_structures',
            'payroll_cycle_directory', 'payroll_cycles',
            'payroll_batch_status_directory', 'payroll_batch_statuses',
            'quality_plan_directory', 'quality_plans',
            'quality_checklist_directory', 'quality_checklists',
            'quality_standard_directory', 'quality_standards',
            'quality_inspection_status_directory', 'quality_inspection_statuses',
            'documents_repository_directory', 'documents_repositories',
            'documents_category_directory', 'documents_categories',
            'documents_workflow_status_directory', 'documents_workflow_statuses',
            'bi_dashboard_directory', 'bi_dashboards',
            'bi_kpi_directory', 'bi_kpis',
            'bi_workflow_status_directory', 'bi_workflow_statuses',
        ], true);
    }
}
