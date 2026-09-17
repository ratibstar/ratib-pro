<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Supplier Tool Registry — READ/ANALYSIS tools for Supplier domain (Unified ERP Agent).
 * Does not duplicate Procurement search_suppliers. No WRITE tools unless a safe path exists.
 */
final class SupplierToolRegistry
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
            'list_suppliers' => [
                'name' => 'list_suppliers',
                'description' => 'List suppliers for the current company (tenant-scoped). Supports search and status filters from live data.',
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
            'get_supplier' => [
                'name' => 'get_supplier',
                'description' => 'Get one supplier by ID with basic profile and status (tenant-scoped).',
                'permission' => 'suppliers.manage',
                'module' => 'suppliers',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'analyze_suppliers' => [
                'name' => 'analyze_suppliers',
                'description' => 'Supplier intelligence from live tenant data: counts by status, open POs, overdue expected dates, amounts, and follow-up cases. Never invent numbers.',
                'permission' => 'suppliers.manage',
                'module' => 'suppliers',
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
            'get_supplier_procurement_links' => [
                'name' => 'get_supplier_procurement_links',
                'description' => 'Link suppliers to purchase requests, purchase orders, approvals, amounts, and statuses from live tenant relations.',
                'permission' => 'suppliers.manage',
                'module' => 'suppliers',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 20],
                        'supplier_id' => ['type' => 'integer', 'minimum' => 1],
                        'open_only' => ['type' => 'boolean', 'default' => false],
                    ],
                    'required' => [],
                ],
            ],
            'get_supplier_inventory_links' => [
                'name' => 'get_supplier_inventory_links',
                'description' => 'Link suppliers to inventory items and quantities via existing purchase-order / PR line inventory_id relations when present. Never invent links.',
                'permission' => 'suppliers.manage',
                'module' => 'suppliers',
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
            'analyze_supplier_cross_domain' => [
                'name' => 'analyze_supplier_cross_domain',
                'description' => 'Cross-domain Supplier ↔ Procurement ↔ Inventory snapshot from live tenant data only (open ops, amounts, linked items, follow-up).',
                'permission' => 'suppliers.manage',
                'module' => 'suppliers',
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
        if ($toolName === 'get_supplier') {
            return (int) ($arguments['id'] ?? 0) > 0;
        }
        return true;
    }
}
