<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Procurement Tool Registry
 * Closed allowlist of exactly 8 approved tools for the Procurement Ops Agent.
 */
final class ProcurementToolRegistry
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
            'list_purchase_requests' => [
                'name' => 'list_purchase_requests',
                'description' => 'List purchase requests for the current company (tenant-scoped)',
                'permission' => 'procurement.manage',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                        'search' => ['type' => 'string', 'default' => ''],
                        'status' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'get_purchase_request' => [
                'name' => 'get_purchase_request',
                'description' => 'Get a single purchase request by ID (tenant-scoped)',
                'permission' => 'procurement.manage',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_purchase_orders' => [
                'name' => 'list_purchase_orders',
                'description' => 'List purchase orders for the current company (tenant-scoped)',
                'permission' => 'procurement.manage',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                        'search' => ['type' => 'string', 'default' => ''],
                        'status' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'search_suppliers' => [
                'name' => 'search_suppliers',
                'description' => 'Search suppliers for the current company (tenant-scoped)',
                'permission' => 'suppliers.manage',
                'module' => 'suppliers',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                        'search' => ['type' => 'string', 'default' => ''],
                        'status' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'list_pending_approvals' => [
                'name' => 'list_pending_approvals',
                'description' => 'List pending approvals for purchase_request and purchase_order entities only',
                'permission' => 'procurement.manage',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50],
                    ],
                    'required' => [],
                ],
            ],
            'get_approval_detail' => [
                'name' => 'get_approval_detail',
                'description' => 'Get approval workflow detail by instance ID',
                'permission' => 'procurement.manage',
                'module' => 'procurement',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'instance_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['instance_id'],
                ],
            ],
            'create_draft_purchase_request' => [
                'name' => 'create_draft_purchase_request',
                'description' => 'Create a draft purchase request',
                'permission' => 'procurement.manage',
                'module' => 'procurement',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190],
                        'department' => ['type' => 'string', 'maxLength' => 100],
                        'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent'], 'default' => 'medium'],
                        'expected_date' => ['type' => 'string', 'format' => 'date'],
                        'currency' => ['type' => 'string', 'default' => 'SAR'],
                        'total_estimated' => ['type' => 'number', 'minimum' => 0],
                        'notes' => ['type' => 'string'],
                        'line_items' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'item_name' => ['type' => 'string'],
                                    'quantity' => ['type' => 'number', 'minimum' => 0],
                                    'unit_price' => ['type' => 'number', 'minimum' => 0],
                                    'unit' => ['type' => 'string'],
                                    'supplier_id' => ['type' => 'integer'],
                                    'warehouse_id' => ['type' => 'integer'],
                                    'tax_rate' => ['type' => 'number', 'default' => 15],
                                ],
                                'required' => ['item_name', 'quantity', 'unit_price'],
                            ],
                        ],
                    ],
                    'required' => ['title'],
                ],
            ],
            'submit_purchase_request' => [
                'name' => 'submit_purchase_request',
                'description' => 'Submit a draft purchase request for approval workflow',
                'permission' => 'procurement.manage',
                'module' => 'procurement',
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

    /**
     * @return list<array{type: string, function: array{name: string, description: string, parameters: array}>>
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
        foreach ($required as $field) {
            if (!array_key_exists($field, $arguments)) {
                throw new \InvalidArgumentException("Missing required argument: {$field}");
            }
        }

        return $arguments;
    }
}