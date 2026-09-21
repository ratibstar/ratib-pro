<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Inventory Tool Registry — READ/ANALYSIS + confirmed WRITE create for Inventory domain.
 */
final class InventoryToolRegistry
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
            'list_inventory_items' => [
                'name' => 'list_inventory_items',
                'description' => 'List inventory items (tenant-scoped). Supports search, warehouse, low_stock, status. Returns quantities and reorder levels from live data.',
                'permission' => 'inventory.manage',
                'module' => 'inventory',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                        'search' => ['type' => 'string', 'default' => ''],
                        'warehouse_id' => ['type' => 'integer', 'minimum' => 1],
                        'low_stock' => ['type' => 'boolean', 'default' => false],
                        'status' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'get_inventory_item' => [
                'name' => 'get_inventory_item',
                'description' => 'Get one inventory item by ID with warehouse name (tenant-scoped).',
                'permission' => 'inventory.manage',
                'module' => 'inventory',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_warehouses' => [
                'name' => 'list_warehouses',
                'description' => 'List warehouses for the current company (tenant-scoped).',
                'permission' => 'inventory.manage',
                'module' => 'inventory',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'status' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'list_stock_movements' => [
                'name' => 'list_stock_movements',
                'description' => 'List recent stock movements from live tenant data. Optional inventory_id filter.',
                'permission' => 'inventory.manage',
                'module' => 'inventory',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'inventory_id' => ['type' => 'integer', 'minimum' => 1],
                        'movement_type' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'analyze_inventory' => [
                'name' => 'analyze_inventory',
                'description' => 'Inventory analysis from live tenant data: totals, low stock, available, expiring, reorder suggestions, and follow-up cases. Never invent numbers.',
                'permission' => 'inventory.manage',
                'module' => 'inventory',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'expiry_days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 180, 'default' => 30],
                    ],
                    'required' => [],
                ],
            ],
            'get_inventory_procurement_links' => [
                'name' => 'get_inventory_procurement_links',
                'description' => 'Link inventory items to purchase requests/orders/amounts from live tenant data when inventory_id relations exist. Reports follow-up cases.',
                'permission' => 'inventory.manage',
                'module' => 'inventory',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'inventory_id' => ['type' => 'integer', 'minimum' => 1],
                        'low_stock_only' => ['type' => 'boolean', 'default' => false],
                    ],
                    'required' => [],
                ],
            ],
            'create_inventory_item' => [
                'name' => 'create_inventory_item',
                'description' => 'Create a new inventory item (WRITE — requires user confirmation). Tenant-scoped. Uses default warehouse when warehouse_id omitted.',
                'permission' => 'inventory.manage',
                'module' => 'inventory',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'item_name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190],
                        'warehouse_id' => ['type' => 'integer', 'minimum' => 1],
                        'sku' => ['type' => 'string', 'maxLength' => 100],
                        'quantity' => ['type' => 'number', 'minimum' => 0, 'default' => 0],
                        'unit' => ['type' => 'string', 'default' => 'pcs'],
                        'unit_cost' => ['type' => 'number', 'minimum' => 0, 'default' => 0],
                        'reorder_level' => ['type' => 'number', 'minimum' => 0],
                        'status' => ['type' => 'string', 'default' => 'active'],
                        'notes' => ['type' => 'string'],
                    ],
                    'required' => ['item_name'],
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
        if ($toolName === 'get_inventory_item') {
            return (int) ($arguments['id'] ?? 0) > 0;
        }
        return true;
    }
}
