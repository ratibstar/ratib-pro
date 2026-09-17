<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Logistics Tool Registry — READ/ANALYSIS for Logistics domain (Unified ERP Agent).
 * Uses existing logistics tables only. No WRITE. No LogisticsAgent.
 */
final class LogisticsToolRegistry
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
            'list_logistics_shipments' => [
                'name' => 'list_logistics_shipments',
                'description' => 'List logistics shipments for the current company (tenant-scoped). Supports status and delayed filters from live data.',
                'permission' => 'logistics.view',
                'module' => 'logistics',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                        'status' => ['type' => 'string', 'default' => ''],
                        'delayed_only' => ['type' => 'boolean', 'default' => false],
                        'customer_id' => ['type' => 'integer', 'minimum' => 1],
                        'order_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'get_logistics_shipment' => [
                'name' => 'get_logistics_shipment',
                'description' => 'Get one logistics shipment with related delivery order / trip fields when present (tenant-scoped).',
                'permission' => 'logistics.view',
                'module' => 'logistics',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_logistics_delivery_orders' => [
                'name' => 'list_logistics_delivery_orders',
                'description' => 'List logistics delivery orders for the current company from live tenant data.',
                'permission' => 'logistics.view',
                'module' => 'logistics',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'status' => ['type' => 'string', 'default' => ''],
                        'incomplete_only' => ['type' => 'boolean', 'default' => false],
                        'customer_id' => ['type' => 'integer', 'minimum' => 1],
                        'order_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'list_logistics_trips' => [
                'name' => 'list_logistics_trips',
                'description' => 'List logistics trips for the current company from live tenant data.',
                'permission' => 'logistics.view',
                'module' => 'logistics',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'status' => ['type' => 'string', 'default' => ''],
                        'open_only' => ['type' => 'boolean', 'default' => false],
                    ],
                    'required' => [],
                ],
            ],
            'analyze_logistics' => [
                'name' => 'analyze_logistics',
                'description' => 'Logistics analysis from live data: shipments, deliveries, trips, delayed and incomplete operations. Never invent numbers.',
                'permission' => 'logistics.view',
                'module' => 'logistics',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_logistics_operational_guidance' => [
                'name' => 'get_logistics_operational_guidance',
                'description' => 'Operational guidance for logistics follow-up from live data. Never auto-writes.',
                'permission' => 'logistics.view',
                'module' => 'logistics',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 10],
                    ],
                    'required' => [],
                ],
            ],
            'get_logistics_crm_links' => [
                'name' => 'get_logistics_crm_links',
                'description' => 'Link logistics shipments/delivery orders to customers when customer_id relations exist.',
                'permission' => 'logistics.view',
                'module' => 'logistics',
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
            'get_logistics_sales_links' => [
                'name' => 'get_logistics_sales_links',
                'description' => 'Link logistics shipments/delivery orders to POS sales orders when order_id relations exist.',
                'permission' => 'logistics.view',
                'module' => 'logistics',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'order_id' => ['type' => 'integer', 'minimum' => 1],
                        'open_only' => ['type' => 'boolean', 'default' => false],
                    ],
                    'required' => [],
                ],
            ],
            'get_logistics_inventory_links' => [
                'name' => 'get_logistics_inventory_links',
                'description' => 'Link logistics to inventory via stock movements (reference_type=logistics_shipment) and/or linked sales order lines.',
                'permission' => 'logistics.view',
                'module' => 'logistics',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'shipment_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => [],
                ],
            ],
            'get_logistics_procurement_links' => [
                'name' => 'get_logistics_procurement_links',
                'description' => 'Link logistics inventory demand to purchase requests/orders when inventory_id relations exist.',
                'permission' => 'logistics.view',
                'module' => 'logistics',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'get_logistics_supplier_links' => [
                'name' => 'get_logistics_supplier_links',
                'description' => 'Link logistics-related inventory demand to suppliers via existing PO supplier_id relations only.',
                'permission' => 'logistics.view',
                'module' => 'logistics',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                    ],
                    'required' => [],
                ],
            ],
            'analyze_logistics_end_to_end' => [
                'name' => 'analyze_logistics_end_to_end',
                'description' => 'End-to-end chain Customer → Sales → Inventory → Procurement → Supplier → Logistics from live relations only.',
                'permission' => 'logistics.view',
                'module' => 'logistics',
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
        if ($toolName === 'get_logistics_shipment') {
            return (int) ($arguments['id'] ?? 0) > 0;
        }
        return true;
    }
}
