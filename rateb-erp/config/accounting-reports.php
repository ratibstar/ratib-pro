<?php
declare(strict_types=1);

/**
 * Accounting reports catalog — grouped for the reports hub.
 * Each item `entity` maps to entity-permissions.php for access control.
 */
return [
    [
        'id' => 'financial_statements',
        'label' => 'accounting_reports_financial',
        'items' => [
            ['entity' => 'trial-balance', 'route' => 'accounting/trial-balance', 'label' => 'trial_balance', 'desc' => 'accounting_report_trial_balance_desc', 'icon' => 'fa-scale-balanced', 'export' => 'accounting/export/trial-balance'],
            ['entity' => 'profit-loss', 'route' => 'accounting/profit-loss', 'label' => 'profit_loss', 'desc' => 'accounting_report_profit_loss_desc', 'icon' => 'fa-chart-line', 'export' => 'accounting/export/profit-loss'],
            ['entity' => 'balance-sheet', 'route' => 'accounting/balance-sheet', 'label' => 'balance_sheet', 'desc' => 'accounting_report_balance_sheet_desc', 'icon' => 'fa-landmark', 'export' => 'accounting/export/balance-sheet'],
            ['entity' => 'cost-of-sales', 'route' => 'accounting/cost-of-sales', 'label' => 'cost_of_sales', 'desc' => 'accounting_report_cost_of_sales_desc', 'icon' => 'fa-boxes-stacked'],
        ],
    ],
    [
        'id' => 'tax_compliance',
        'label' => 'accounting_reports_tax',
        'items' => [
            ['entity' => 'vat-report', 'route' => 'accounting/vat-report', 'label' => 'vat_report', 'desc' => 'accounting_report_vat_desc', 'icon' => 'fa-percent', 'export' => 'accounting/export/vat-report'],
            ['entity' => 'zatca-settings', 'route' => 'accounting/zatca-settings', 'label' => 'zatca_settings', 'desc' => 'accounting_report_zatca_desc', 'icon' => 'fa-qrcode'],
        ],
    ],
    [
        'id' => 'analysis',
        'label' => 'accounting_reports_analysis',
        'items' => [
            ['entity' => 'cost-center-report', 'route' => 'accounting/cost-center-report', 'label' => 'cost_center_report', 'desc' => 'accounting_report_cost_center_desc', 'icon' => 'fa-sitemap'],
            ['entity' => 'budget-report', 'route' => 'accounting/budget-report', 'label' => 'budget_report', 'desc' => 'accounting_report_budget_desc', 'icon' => 'fa-coins'],
            ['entity' => 'cfo-dashboard', 'route' => 'accounting/cfo-dashboard', 'label' => 'cfo_dashboard', 'desc' => 'accounting_report_cfo_desc', 'icon' => 'fa-gauge-high'],
        ],
    ],
    [
        'id' => 'receivables_payables',
        'label' => 'accounting_reports_ar_ap',
        'items' => [
            ['entity' => 'accounts-receivable', 'route' => 'accounting/accounts-receivable', 'label' => 'accounts_receivable', 'desc' => 'accounting_report_ar_desc', 'icon' => 'fa-hand-holding-dollar'],
            ['entity' => 'accounts-payable', 'route' => 'accounting/accounts-payable', 'label' => 'accounts_payable', 'desc' => 'accounting_report_ap_desc', 'icon' => 'fa-file-invoice-dollar'],
            ['entity' => 'bank-reconciliation', 'route' => 'accounting/bank-reconciliation', 'label' => 'bank_reconciliation', 'desc' => 'accounting_report_bank_recon_desc', 'icon' => 'fa-building-columns'],
        ],
    ],
    [
        'id' => 'registers',
        'label' => 'accounting_reports_registers',
        'items' => [
            ['entity' => 'journal-register', 'route' => 'accounting/journal-register', 'label' => 'journal_register', 'desc' => 'accounting_report_journal_register_desc', 'icon' => 'fa-book-open', 'export' => 'accounting/export/journals'],
        ],
    ],
];
