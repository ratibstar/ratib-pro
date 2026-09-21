<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Projects Tool Registry — READ tools for Unified ERP Agent.
 */
final class ProjectsToolRegistry
{
    public static function getTools(): array
    {
        return [
            'list_projects' => [
                'name' => 'list_projects',
                'description' => 'List projects (tenant-scoped).',
                'permission' => 'projects.manage',
                'module' => 'projects',
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
            'get_project' => [
                'name' => 'get_project',
                'description' => 'Get one project by ID.',
                'permission' => 'projects.manage',
                'module' => 'projects',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['id'],
                ],
            ],
            'list_project_tasks' => [
                'name' => 'list_project_tasks',
                'description' => 'List project tasks (tenant-scoped). Optional project_id.',
                'permission' => 'projects.manage',
                'module' => 'projects',
                'write' => false,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50],
                        'project_id' => ['type' => 'integer', 'minimum' => 1],
                        'status' => ['type' => 'string', 'default' => ''],
                    ],
                    'required' => [],
                ],
            ],
            'projects_status_summary' => [
                'name' => 'projects_status_summary',
                'description' => 'Projects status summary from live data.',
                'permission' => 'projects.manage',
                'module' => 'projects',
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
