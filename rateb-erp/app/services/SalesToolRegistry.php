<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Sales Tool Registry — READ/ANALYSIS for Sales domain (Unified ERP Agent).
 * Backed by existing POS sales tables + inventory/procurement relations.
 * No WRITE tools. No separate SalesAgent runtime.
 */
final class SalesToolRegistry
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
            'list_sales_orders' => [
                'name' => 'list_sales_orders',
                'description' => 'List POS sales orders for the current company (tenant-scoped). Supports status/order_type filters from live data.',
                'permission' => 'pos.view',
                'module' => 'pos',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                        'status' => ['type' => 'string', 'default' => ''],
                        'order_type' => ['type' => 'string', 'default' => ''],
                        'search' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'get_sales_order' => [
                'name' => 'get_sales_order',
                'description' => 'Get one POS sales order with lines and customer (tenant-scoped).',
                'permission' => 'pos.view',
                'module' => 'pos',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_sales_customers' => [
                'name' => 'list_sales_customers',
                'description' => 'List customers linked to POS sales orders for the current company (live tenant data).',
                'permission' => 'pos.view',
                'module' => 'pos',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'search' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'analyze_sales' => [
                'name' => 'analyze_sales',
                'description' => 'Sales analysis from live POS data: totals, open/draft/suspended, products demand, follow-up cases. Never invent numbers.',
                'permission' => 'pos.view',
                'module' => 'pos',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_sales_operational_guidance' => [
                'name' => 'get_sales_operational_guidance',
                'description' => 'Operational guidance for sales follow-up from live POS/inventory relations. Never auto-writes.',
                'permission' => 'pos.view',
                'module' => 'pos',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 10],
                    ],
                    'required' => [],
                ],
            ],
            'get_sales_inventory_links' => [
                'name' => 'get_sales_inventory_links',
                'description' => 'Link sales order lines to inventory quantities when inventory_id exists. Reports stock shortages vs demand.',
                'permission' => 'pos.view',
                'module' => 'pos',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'shortfall_only' => ['type' => 'boolean', 'default' => false],
                    ],
                    'required' => [],
                ],
            ],
            'get_sales_procurement_links' => [
                'name' => 'get_sales_procurement_links',
                'description' => 'Link sales-driven inventory demand to purchase requests/orders when inventory_id relations exist.',
                'permission' => 'pos.view',
                'module' => 'pos',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_sales_supplier_links' => [
                'name' => 'get_sales_supplier_links',
                'description' => 'Link sales inventory demand to suppliers via existing purchase-order line inventory relations only.',
                'permission' => 'pos.view',
                'module' => 'pos',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'analyze_sales_cross_domain' => [
                'name' => 'analyze_sales_cross_domain',
                'description' => 'Cross-domain Sales ↔ Inventory ↔ Procurement ↔ Supplier snapshot from live tenant relations only.',
                'permission' => 'pos.view',
                'module' => 'pos',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'create_sales_order' => [
                'name' => 'create_sales_order',
                'description' => 'Create a draft sales/POS order (WRITE — requires confirmation).',
                'permission' => 'pos.manage',
                'module' => 'pos',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => array (
  'customer_id' => 
  array (
    'type' => 'integer',
  ),
  'total' => 
  array (
    'type' => 'number',
  ),
  'order_type' => 
  array (
    'type' => 'string',
  ),
  'notes' => 
  array (
    'type' => 'string',
  ),
),
                    'required' => array (
),
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
        if ($toolName === 'get_sales_order') {
            return (int) ($arguments['id'] ?? 0) > 0;
        }
        return true;
    }
}
