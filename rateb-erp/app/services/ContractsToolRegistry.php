<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Contracts Tool Registry — READ tools for Unified ERP Agent.
 */
final class ContractsToolRegistry
{
    public static function getTools(): array
    {
        return [
            'list_contracts' => [
                'name' => 'list_contracts',
                'description' => 'List commercial contracts (tenant-scoped).',
                'permission' => 'contracts.manage',
                'module' => 'contracts',
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
            'get_contract' => [
                'name' => 'get_contract',
                'description' => 'Get one contract by ID.',
                'permission' => 'contracts.manage',
                'module' => 'contracts',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_expiring_contracts' => [
                'name' => 'list_expiring_contracts',
                'description' => 'List contracts expiring within N days (default 60).',
                'permission' => 'contracts.manage',
                'module' => 'contracts',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'default' => 60],
                    ],
                    'required' => [],
                ],
            ],
            'contracts_status_summary' => [
                'name' => 'contracts_status_summary',
                'description' => 'Contracts status summary from live data.',
                'permission' => 'contracts.manage',
                'module' => 'contracts',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                    ],
                    'required' => [],
                ],
            ],
            'create_contract' => [
                'name' => 'create_contract',
                'description' => 'Create a commercial contract draft (WRITE — requires confirmation). Title required.',
                'permission' => 'contracts.manage',
                'module' => 'contracts',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190],
                        'value' => ['type' => 'number', 'minimum' => 0, 'default' => 0],
                        'start_date' => ['type' => 'string'],
                        'end_date' => ['type' => 'string'],
                        'contract_type' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'default' => 'draft'],
                    ],
                    'required' => ['title'],
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
