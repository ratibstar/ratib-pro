<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * CRM / Customer Tool Registry — READ/ANALYSIS for CRM domain (Unified ERP Agent).
 * Uses existing CRM tables + rateb_customers. No WRITE. No CrmAgent / CustomerAgent.
 */
final class CrmToolRegistry
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
            'list_crm_customers' => [
                'name' => 'list_crm_customers',
                'description' => 'List customers for the current company (tenant-scoped). Supports search and at-risk filters from live data.',
                'permission' => 'crm.view',
                'module' => 'crm',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                        'search' => ['type' => 'string', 'default' => ''],
                        'at_risk_only' => ['type' => 'boolean', 'default' => false],
                    ],
                    'required' => [],
                ],
            ],
            'get_crm_customer' => [
                'name' => 'get_crm_customer',
                'description' => 'Get one customer profile with CRM status fields (tenant-scoped).',
                'permission' => 'crm.view',
                'module' => 'crm',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_crm_leads' => [
                'name' => 'list_crm_leads',
                'description' => 'List CRM leads for the current company from live tenant data.',
                'permission' => 'crm.view',
                'module' => 'crm',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'workflow_status' => ['type' => 'string', 'default' => ''],
                        'customer_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'list_crm_opportunities' => [
                'name' => 'list_crm_opportunities',
                'description' => 'List CRM opportunities for the current company from live tenant data.',
                'permission' => 'crm.view',
                'module' => 'crm',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'workflow_status' => ['type' => 'string', 'default' => ''],
                        'customer_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'list_crm_followups' => [
                'name' => 'list_crm_followups',
                'description' => 'List open CRM activities and tasks that need follow-up (live tenant data).',
                'permission' => 'crm.view',
                'module' => 'crm',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'customer_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'analyze_crm' => [
                'name' => 'analyze_crm',
                'description' => 'CRM analysis from live data: customers, leads, opportunities, at-risk, open follow-ups. Never invent numbers.',
                'permission' => 'crm.view',
                'module' => 'crm',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_crm_operational_guidance' => [
                'name' => 'get_crm_operational_guidance',
                'description' => 'Operational guidance for CRM follow-up from live data. Never auto-writes.',
                'permission' => 'crm.view',
                'module' => 'crm',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 10],
                    ],
                    'required' => [],
                ],
            ],
            'get_crm_sales_links' => [
                'name' => 'get_crm_sales_links',
                'description' => 'Link customers to POS sales orders when customer_id relations exist.',
                'permission' => 'crm.view',
                'module' => 'crm',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'customer_id' => ['type' => 'integer', 'minimum' => 1],
                        'open_only' => ['type' => 'boolean', 'default' => false],
                    ],
                    'required' => [],
                ],
            ],
            'get_crm_inventory_links' => [
                'name' => 'get_crm_inventory_links',
                'description' => 'Link customers with sales demand to inventory shortfalls via existing POS line inventory_id relations.',
                'permission' => 'crm.view',
                'module' => 'crm',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'customer_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'get_crm_procurement_links' => [
                'name' => 'get_crm_procurement_links',
                'description' => 'Link customer sales stock shortfalls to purchase requests/orders when inventory_id relations exist.',
                'permission' => 'crm.view',
                'module' => 'crm',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'customer_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'get_crm_supplier_links' => [
                'name' => 'get_crm_supplier_links',
                'description' => 'Link customer-driven inventory shortfalls to suppliers via existing PO supplier_id relations only.',
                'permission' => 'crm.view',
                'module' => 'crm',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'customer_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'analyze_crm_commercial_intelligence' => [
                'name' => 'analyze_crm_commercial_intelligence',
                'description' => 'Full commercial chain Customer → Sales → Inventory → Procurement → Supplier from live relations only.',
                'permission' => 'crm.view',
                'module' => 'crm',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'customer_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'create_crm_lead' => [
                'name' => 'create_crm_lead',
                'description' => 'Create a CRM lead (WRITE — requires confirmation). Title required.',
                'permission' => 'crm.manage',
                'module' => 'crm',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190],
                        'contact_name' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                        'priority' => ['type' => 'string', 'default' => 'normal'],
                    ],
                    'required' => ['title'],
                ],
            ],
            'create_crm_followup' => [
                'name' => 'create_crm_followup',
                'description' => 'Create a CRM follow-up task (WRITE — requires confirmation). Subject required.',
                'permission' => 'crm.manage',
                'module' => 'crm',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'subject' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190],
                        'notes' => ['type' => 'string'],
                        'priority' => ['type' => 'string', 'default' => 'normal'],
                        'customer_id' => ['type' => 'integer', 'minimum' => 1],
                        'lead_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['subject'],
                ],
            ],
            'create_customer' => [
                'name' => 'create_customer',
                'description' => 'Create a customer record (WRITE — requires confirmation). Name required.',
                'permission' => 'crm.manage',
                'module' => 'crm',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190],
                        'email' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                    ],
                    'required' => ['name'],
                ],
            ],
        ];
    }

    /**
     * @return list<array{type: string, function: array{name: string, description: string, parameters: array}}>
     */
    public static function getOpenAiToolDefinitions(): array
    {
        $tools = [];
        foreach (self::getTools() as $toolName => $config) {
            $tools[] = [
                'type' => 'function',
                'function' => [
                    'name' => $toolName,
                    'description' => $config['description'],
                    'parameters' => $config['parameters'],
                ],
            ];
        }
        return $tools;
    }

    public static function isAllowed(string $toolName): bool
    {
        return isset(self::getTools()[$toolName]);
    }

    public static function getTool(string $toolName): ?array
    {
        return self::getTools()[$toolName] ?? null;
    }

    public static function validateArguments(string $toolName, array $arguments): array
    {
        $tool = self::getTool($toolName);
        if (!$tool) {
            throw new \InvalidArgumentException("Tool not in allowlist: {$toolName}");
        }

        $required = $tool['parameters']['required'] ?? [];
        $properties = $tool['parameters']['properties'] ?? [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $arguments)) {
                throw new \InvalidArgumentException("Missing required argument: {$field}");
            }
            $value = $arguments[$field];
            $prop = is_array($properties[$field] ?? null) ? $properties[$field] : [];
            $type = (string) ($prop['type'] ?? '');
            if ($type === 'integer' || $type === 'number') {
                if (!is_numeric($value) || (float) $value < (float) ($prop['minimum'] ?? 0)) {
                    throw new \InvalidArgumentException("Invalid required argument: {$field}");
                }
            } elseif ($type === 'string') {
                $str = trim((string) $value);
                if ($str === '') {
                    throw new \InvalidArgumentException("Invalid required argument: {$field}");
                }
                $arguments[$field] = $str;
            } elseif ($value === null) {
                throw new \InvalidArgumentException("Invalid required argument: {$field}");
            }
        }

        return $arguments;
    }

    public static function hasSufficientParameters(string $toolName, array $arguments): bool
    {
        try {
            self::validateArguments($toolName, $arguments);
        } catch (\Throwable $e) {
            return false;
        }
        if ($toolName === 'get_crm_customer') {
            return (int) ($arguments['id'] ?? 0) > 0;
        }
        return true;
    }
}
