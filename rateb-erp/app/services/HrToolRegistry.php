<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * HR Tool Registry — READ tools for Unified ERP Agent.
 */
final class HrToolRegistry
{
    public static function getTools(): array
    {
        return [
            'list_employees' => [
                'name' => 'list_employees',
                'description' => 'List employees (tenant-scoped). Optional search/status/department.',
                'permission' => 'hr.view',
                'module' => 'hr',
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
            'get_employee' => [
                'name' => 'get_employee',
                'description' => 'Get one employee by ID (tenant-scoped).',
                'permission' => 'hr.view',
                'module' => 'hr',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_leave_requests' => [
                'name' => 'list_leave_requests',
                'description' => 'List leave requests (tenant-scoped).',
                'permission' => 'hr.view',
                'module' => 'hr',
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
            'list_hr_departments' => [
                'name' => 'list_hr_departments',
                'description' => 'List HR departments (tenant-scoped).',
                'permission' => 'hr.view',
                'module' => 'hr',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                    ],
                    'required' => [],
                ],
            ],
            'hr_workforce_summary' => [
                'name' => 'hr_workforce_summary',
                'description' => 'HR workforce summary from live employee/leave tables. Never invent counts.',
                'permission' => 'hr.view',
                'module' => 'hr',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                    ],
                    'required' => [],
                ],
            ],
            'create_employee' => [
                'name' => 'create_employee',
                'description' => 'Create a new employee record (WRITE — requires user confirmation). Tenant-scoped. Name required.',
                'permission' => 'hr.manage',
                'module' => 'hr',
                'write' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 190],
                        'email' => ['type' => 'string', 'maxLength' => 190],
                        'phone' => ['type' => 'string', 'maxLength' => 50],
                        'job_title' => ['type' => 'string', 'maxLength' => 120],
                        'hire_date' => ['type' => 'string', 'format' => 'date'],
                        'salary_base' => ['type' => 'number', 'minimum' => 0],
                        'status' => ['type' => 'string', 'default' => 'active'],
                        'notes' => ['type' => 'string'],
                        'department_id' => ['type' => 'integer', 'minimum' => 1],
                        'branch_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['name'],
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
