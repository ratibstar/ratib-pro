<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Payroll Tool Registry — READ tools for Unified ERP Agent.
 */
final class PayrollToolRegistry
{
    public static function getTools(): array
    {
        return [
            'list_payroll_cycles' => [
                'name' => 'list_payroll_cycles',
                'description' => 'List payroll cycles (tenant-scoped).',
                'permission' => 'hr.view',
                'module' => 'payroll',
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
            'list_payroll_batches' => [
                'name' => 'list_payroll_batches',
                'description' => 'List payroll batches (tenant-scoped).',
                'permission' => 'hr.view',
                'module' => 'payroll',
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
            'list_payslips' => [
                'name' => 'list_payslips',
                'description' => 'List payslips (tenant-scoped). Optional employee_id.',
                'permission' => 'hr.view',
                'module' => 'payroll',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'employee_id' => ['type' => 'integer', 'minimum' => 1],
                        'status' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'payroll_run_summary' => [
                'name' => 'payroll_run_summary',
                'description' => 'Payroll run summary from live cycles/batches/payslips.',
                'permission' => 'hr.view',
                'module' => 'payroll',
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
