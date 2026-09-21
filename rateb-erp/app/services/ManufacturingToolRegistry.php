<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Manufacturing Tool Registry — READ tools for Unified ERP Agent.
 */
final class ManufacturingToolRegistry
{
    public static function getTools(): array
    {
        return [
            'list_production_orders' => [
                'name' => 'list_production_orders',
                'description' => 'List manufacturing production orders (tenant-scoped).',
                'permission' => 'manufacturing.manage',
                'module' => 'manufacturing',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'search' => ['type' => 'string', 'default' => ''],
                        'status' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'get_production_order' => [
                'name' => 'get_production_order',
                'description' => 'Get one production order by ID.',
                'permission' => 'manufacturing.manage',
                'module' => 'manufacturing',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_mfg_work_orders' => [
                'name' => 'list_mfg_work_orders',
                'description' => 'List manufacturing work orders (tenant-scoped).',
                'permission' => 'manufacturing.manage',
                'module' => 'manufacturing',
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
            'manufacturing_status_summary' => [
                'name' => 'manufacturing_status_summary',
                'description' => 'Manufacturing production-order status summary.',
                'permission' => 'manufacturing.manage',
                'module' => 'manufacturing',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

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

    public static function hasSufficientParameters(string $toolName, array $arguments): bool
    {
        try {
            self::validateArguments($toolName, $arguments);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
