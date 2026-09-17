<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Unified ERP Tool facade — expands only active domain tool registries.
 * Does not duplicate Procurement tools; delegates to domain registries.
 */
final class ErpToolRegistry
{
    /**
     * @return array<string, array>
     */
    public static function getTools(?string $domainId = null): array
    {
        if ($domainId !== null && $domainId !== '') {
            return ErpDomainRegistry::toolsForDomain($domainId);
        }
        return ErpDomainRegistry::allActiveTools();
    }

    public static function getTool(string $toolName, ?string $domainId = null): ?array
    {
        $tools = self::getTools($domainId);
        return $tools[$toolName] ?? null;
    }

    public static function isAllowed(string $toolName, ?string $domainId = null): bool
    {
        $tools = self::getTools($domainId);
        return isset($tools[$toolName]);
    }

    public static function domainForTool(string $toolName): ?string
    {
        return ErpDomainRegistry::domainForTool($toolName);
    }
}
