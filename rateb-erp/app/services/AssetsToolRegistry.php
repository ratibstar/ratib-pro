<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Assets Tool Registry — READ tools for Unified ERP Agent.
 */
final class AssetsToolRegistry
{
    public static function getTools(): array
    {
        return [
            'list_assets' => [
                'name' => 'list_assets',
                'description' => 'List assets (tenant-scoped). Prefers EAM table when present.',
                'permission' => 'assets.manage',
                'module' => 'assets',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                    ],
                    'required' => [],
                ],
            ],
            'get_asset' => [
                'name' => 'get_asset',
                'description' => 'Get one asset by ID (EAM or legacy).',
                'permission' => 'assets.manage',
                'module' => 'assets',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_asset_work_orders' => [
                'name' => 'list_asset_work_orders',
                'description' => 'List EAM work orders when available.',
                'permission' => 'assets.manage',
                'module' => 'assets',
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
            'assets_summary' => [
                'name' => 'assets_summary',
                'description' => 'Assets status summary from live data.',
                'permission' => 'assets.manage',
                'module' => 'assets',
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
