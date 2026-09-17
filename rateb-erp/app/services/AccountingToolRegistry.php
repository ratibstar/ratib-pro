<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Accounting Tool Registry — READ/ANALYSIS + limited WRITE via existing AccountingService workflows.
 * No AccountingAgent. No invented financial operations.
 */
final class AccountingToolRegistry
{
    /**
     * @return array<string, array{
     *     name: string,
     *     description: string,
     *     permission: string,
     *     module: string,
     *     write: bool,
     *     parameters: array
     * }>
     */
    public static function getTools(): array
    {
        return [
            'list_chart_accounts' => [
                'name' => 'list_chart_accounts',
                'description' => 'List active chart of accounts for the current company (tenant-scoped).',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 100],
                        'account_type' => ['type' => 'string', 'default' => ''],
                        'search' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'get_account' => [
                'name' => 'get_account',
                'description' => 'Get one chart account with statement snapshot when available (tenant-scoped).',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_journal_entries' => [
                'name' => 'list_journal_entries',
                'description' => 'List journal entries for the current company from live ledger data.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'status' => ['type' => 'string', 'default' => ''],
                        'source_type' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'get_journal_entry' => [
                'name' => 'get_journal_entry',
                'description' => 'Get one journal entry with lines (tenant-scoped).',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_accounts_receivable' => [
                'name' => 'list_accounts_receivable',
                'description' => 'List accounts receivable / open invoices from live AccountingService data.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'overdue_only' => ['type' => 'boolean', 'default' => false],
                    ],
                    'required' => [],
                ],
            ],
            'list_accounts_payable' => [
                'name' => 'list_accounts_payable',
                'description' => 'List accounts payable / purchase payables from live AccountingService data.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'supplier_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'list_supplier_payments' => [
                'name' => 'list_supplier_payments',
                'description' => 'List supplier payments from live accounting data (tenant-scoped).',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                    ],
                    'required' => [],
                ],
            ],
            'get_financial_summary' => [
                'name' => 'get_financial_summary',
                'description' => 'Financial summary: invoices, payments, journals, accounts, procurement totals from live data.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'include_cfo' => ['type' => 'boolean', 'default' => true],
                    ],
                    'required' => [],
                ],
            ],
            'get_vat_report' => [
                'name' => 'get_vat_report',
                'description' => 'VAT report from existing AccountingService for the current company when tax data exists.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'from_date' => ['type' => 'string', 'default' => ''],
                        'to_date' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'analyze_accounting' => [
                'name' => 'analyze_accounting',
                'description' => 'Accounting analysis from live AR/AP/summary/CFO metrics. Never invent balances.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_accounting_operational_guidance' => [
                'name' => 'get_accounting_operational_guidance',
                'description' => 'Financial follow-up guidance from live overdue AR/AP and unpaid invoices. Never auto-writes.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 10],
                    ],
                    'required' => [],
                ],
            ],
            'get_accounting_sales_links' => [
                'name' => 'get_accounting_sales_links',
                'description' => 'Correlate sales invoices/payments with accounting journals when source relations exist.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_accounting_procurement_links' => [
                'name' => 'get_accounting_procurement_links',
                'description' => 'Correlate purchase orders/invoices with accounting payables and journals from live data.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_accounting_supplier_links' => [
                'name' => 'get_accounting_supplier_links',
                'description' => 'Supplier payable exposure and payments from live accounting relations.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'supplier_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'get_accounting_inventory_links' => [
                'name' => 'get_accounting_inventory_links',
                'description' => 'Inventory stock-movement journals posted to accounting when source_type relations exist.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_accounting_logistics_links' => [
                'name' => 'get_accounting_logistics_links',
                'description' => 'Operational cost context via logistics-linked sales/PO journals when live relations exist. No invented costs.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'analyze_financial_intelligence' => [
                'name' => 'analyze_financial_intelligence',
                'description' => 'Cross-domain financial intelligence: Sales/Procurement/Supplier/Inventory/Logistics ↔ Accounting from live evidence only.',
                'permission' => 'accounting.view',
                'module' => 'accounting',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 15],
                    ],
                    'required' => [],
                ],
            ],
            'submit_journal_for_approval' => [
                'name' => 'submit_journal_for_approval',
                'description' => 'Submit an existing manual draft journal for approval via AccountingService workflow. Requires confirmation. Never invents journal lines.',
                'permission' => 'accounting.manage',
                'module' => 'accounting',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
        ];
    }

    public static function isAllowed(string $toolName): bool
    {
        return isset(self::getTools()[$toolName]);
    }

    public static function getTool(string $toolName): ?array
    {
        return self::getTools()[$toolName] ?? null;
    }

    public static function hasSufficientParameters(string $toolName, array $arguments): bool
    {
        $tool = self::getTool($toolName);
        if ($tool === null) {
            return false;
        }
        foreach (($tool['parameters']['required'] ?? []) as $field) {
            if (!array_key_exists($field, $arguments)) {
                return false;
            }
            $value = $arguments[$field];
            $prop = is_array($tool['parameters']['properties'][$field] ?? null)
                ? $tool['parameters']['properties'][$field]
                : [];
            $type = (string) ($prop['type'] ?? '');
            if ($type === 'integer' || $type === 'number') {
                if (!is_numeric($value) || (int) $value < (int) ($prop['minimum'] ?? 1)) {
                    return false;
                }
            } elseif ($type === 'string' && trim((string) $value) === '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @return list<array{type:string,function:array{name:string,description:string,parameters:array}}>
     */
    public static function toOpenAiTools(): array
    {
        $out = [];
        foreach (self::getTools() as $name => $config) {
            $out[] = [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $config['description'],
                    'parameters' => $config['parameters'],
                ],
            ];
        }
        return $out;
    }
}
