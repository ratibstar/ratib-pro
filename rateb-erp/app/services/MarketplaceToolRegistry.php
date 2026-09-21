<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Marketplace Tool Registry — READ tools for Unified ERP Agent.
 */
final class MarketplaceToolRegistry
{
    public static function getTools(): array
    {
        return [
            'list_marketplace_providers' => [
                'name' => 'list_marketplace_providers',
                'description' => 'List marketplace providers (tenant-scoped).',
                'permission' => 'marketplace.manage',
                'module' => 'marketplace',
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
            'list_marketplace_services' => [
                'name' => 'list_marketplace_services',
                'description' => 'List marketplace services (tenant-scoped).',
                'permission' => 'marketplace.manage',
                'module' => 'marketplace',
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
            'list_marketplace_orders' => [
                'name' => 'list_marketplace_orders',
                'description' => 'List marketplace orders (tenant-scoped).',
                'permission' => 'marketplace.manage',
                'module' => 'marketplace',
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
            'marketplace_summary' => [
                'name' => 'marketplace_summary',
                'description' => 'Marketplace summary from live providers/services/orders.',
                'permission' => 'marketplace.manage',
                'module' => 'marketplace',
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
