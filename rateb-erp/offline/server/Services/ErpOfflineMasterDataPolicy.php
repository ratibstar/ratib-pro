<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Offline\OfflineModule;

/**
 * Phase 13 — Master-data delta policy (read-only).
 */
final class ErpOfflineMasterDataPolicy
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /** @return array<string, mixed> */
    private function config(): array
    {
        if (self::$config === null) {
            $file = OfflineModule::rootPath() . '/config/master-data-entities.php';
            self::$config = is_file($file) ? require $file : [];
        }

        return self::$config;
    }

    public function isEnabled(): bool
    {
        return (new OfflineFeatureFlagService())->isMasterDataEnabled();
    }

    /**
     * Resolve canonical entity name or null if not in master-data allowlist.
     */
    public function resolveCanonical(string $entity): ?string
    {
        $entity = trim($entity);
        if ($entity === '') {
            return null;
        }
        $entities = is_array($this->config()['entities'] ?? null) ? $this->config()['entities'] : [];
        if (isset($entities[$entity])) {
            return $entity;
        }
        foreach ($entities as $canonical => $meta) {
            $aliases = is_array($meta['aliases'] ?? null) ? $meta['aliases'] : [];
            if (in_array($entity, $aliases, true)) {
                return (string) $canonical;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function allowedCanonicalNames(): array
    {
        $entities = is_array($this->config()['entities'] ?? null) ? $this->config()['entities'] : [];

        return array_keys($entities);
    }

    public function isLegacyTier1Entity(string $entity): bool
    {
        return in_array($entity, [
            'inventory_catalog', 'inventory', 'catalog',
            'employee_directory', 'employees', 'hr_employees',
            'supplier_directory', 'suppliers', 'procurement_suppliers',
            'recruitment_agency_directory', 'recruitment_agencies', 'agencies',
        ], true);
    }
}
