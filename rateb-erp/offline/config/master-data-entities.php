<?php

declare(strict_types=1);

/**
 * Phase 13 — Master-data delta entity allowlist + field maps.
 *
 * @return array{
 *   entities: array<string, array{
 *     table: string,
 *     aliases: list<string>,
 *     cache_prefix: string,
 *     branch_scoped: bool,
 *     requires_updated_at: bool
 *   }>
 * }
 */
return [
    'entities' => [
        'customer_directory' => [
            'table' => 'rateb_customers',
            'aliases' => ['customers', 'customer'],
            'cache_prefix' => 'cus',
            'branch_scoped' => false,
            'requires_updated_at' => true,
        ],
        'branch_directory' => [
            'table' => 'rateb_branches',
            'aliases' => ['branches', 'branch'],
            'cache_prefix' => 'br',
            'branch_scoped' => false,
            'requires_updated_at' => true,
        ],
        'warehouse_directory' => [
            'table' => 'rateb_warehouses',
            'aliases' => ['warehouses', 'warehouse'],
            'cache_prefix' => 'wh',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
        /** Normalized under master_data (also available via Tier-1 flags). */
        'employee_directory' => [
            'table' => 'rateb_employees',
            'aliases' => ['employees', 'hr_employees'],
            'cache_prefix' => 'emp',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
        'supplier_directory' => [
            'table' => 'rateb_suppliers',
            'aliases' => ['suppliers', 'procurement_suppliers'],
            'cache_prefix' => 'sup',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
        /** Phase 15B — optional recruitment directories (read-only). */
        'recruitment_agency_directory' => [
            'table' => 'rateb_recruitment_agencies',
            'aliases' => ['recruitment_agencies', 'agencies'],
            'cache_prefix' => 'rag',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
        'recruitment_skill_directory' => [
            'table' => 'rateb_recruitment_skills',
            'aliases' => ['recruitment_skills', 'skills'],
            'cache_prefix' => 'rsk',
            'branch_scoped' => false,
            'requires_updated_at' => false,
        ],
        'recruitment_language_directory' => [
            'table' => 'rateb_recruitment_languages',
            'aliases' => ['recruitment_languages', 'languages'],
            'cache_prefix' => 'rlg',
            'branch_scoped' => false,
            'requires_updated_at' => false,
        ],
        /** Phase 16B — optional accounting directories (read-only; flag offline.accounting.masterdata). */
        'chart_of_accounts_directory' => [
            'table' => 'rateb_chart_of_accounts',
            'aliases' => ['chart_of_accounts', 'accounts', 'coa'],
            'cache_prefix' => 'coa',
            'branch_scoped' => false,
            'requires_updated_at' => true,
        ],
        'accounting_currency_directory' => [
            'table' => 'rateb_accounting_currencies',
            'aliases' => ['accounting_currencies', 'currencies'],
            'cache_prefix' => 'cur',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
        'accounting_exchange_rate_directory' => [
            'table' => 'rateb_accounting_exchange_rates',
            'aliases' => ['accounting_exchange_rates', 'exchange_rates'],
            'cache_prefix' => 'fx',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
        'accounting_tax_code_directory' => [
            'table' => 'rateb_accounting_tax_codes',
            'aliases' => ['accounting_tax_codes', 'tax_codes'],
            'cache_prefix' => 'tax',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
        'accounting_cost_center_directory' => [
            'table' => 'rateb_cost_centers',
            'aliases' => ['cost_centers', 'cost_center'],
            'cache_prefix' => 'cc',
            'branch_scoped' => false,
            'requires_updated_at' => true,
        ],
        'accounting_profit_center_directory' => [
            'table' => 'rateb_accounting_profit_centers',
            'aliases' => ['profit_centers', 'profit_center'],
            'cache_prefix' => 'pc',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
        'accounting_fiscal_period_directory' => [
            'table' => 'rateb_fiscal_periods',
            'aliases' => ['fiscal_periods', 'fiscal_period'],
            'cache_prefix' => 'fp',
            'branch_scoped' => false,
            'requires_updated_at' => true,
        ],
        /** Phase 17B — optional CRM directories (read-only; flag offline.crm.masterdata). */
        'crm_lead_source_directory' => [
            'table' => 'rateb_crm_lead_sources',
            'aliases' => ['crm_lead_sources', 'lead_sources'],
            'cache_prefix' => 'cls',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
        'crm_pipeline_stage_directory' => [
            'table' => 'rateb_crm_pipeline_stages',
            'aliases' => ['crm_pipeline_stages', 'pipeline_stages'],
            'cache_prefix' => 'cps',
            'branch_scoped' => false,
            'requires_updated_at' => true,
        ],
        'crm_tag_directory' => [
            'table' => 'rateb_crm_tags',
            'aliases' => ['crm_tags', 'tags'],
            'cache_prefix' => 'ctg',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
        'crm_company_directory' => [
            'table' => 'rateb_crm_companies',
            'aliases' => ['crm_companies', 'crm_accounts'],
            'cache_prefix' => 'cco',
            'branch_scoped' => true,
            'requires_updated_at' => true,
        ],
    ],
];
