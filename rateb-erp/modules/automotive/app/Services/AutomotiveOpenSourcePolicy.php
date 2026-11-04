<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

/**
 * Offline open-data gate. It does not download files and does not insert catalog rows.
 * Import stays closed while any staged source has an unresolved redistribution term.
 */
final class AutomotiveOpenSourcePolicy
{
    /** @var list<string> */
    public const BLOCKED_UPSTREAM = ['il_mot', 'es_dgt', 'th_dlt'];

    public const VEHICLESDB_ATTRIBUTION = 'Vehicle data by VehiclesDB';

    /** @param array<string, mixed> $manifest */
    public static function assertStaged(array $manifest, string $attribution): void
    {
        $version = trim((string) ($manifest['version'] ?? ''));
        if ($version === '') {
            throw new \InvalidArgumentException('source_version_missing');
        }
        if (!str_contains($attribution, self::VEHICLESDB_ATTRIBUTION) || !str_contains($attribution, 'https://vehiclesdb.com')) {
            throw new \InvalidArgumentException('attribution_missing');
        }
        $sources = $manifest['sources'] ?? null;
        if (!is_array($sources) || $sources === []) {
            throw new \InvalidArgumentException('provenance_missing');
        }
        $seen = [];
        $names = [];
        foreach ($sources as $source) {
            if (!is_array($source)) {
                throw new \InvalidArgumentException('invalid_source');
            }
            $id = trim((string) ($source['id'] ?? ''));
            $license = trim((string) ($source['license'] ?? ''));
            $name = trim((string) ($source['name'] ?? ''));
            if ($id === '' || $license === '' || $name === '') {
                throw new \InvalidArgumentException('invalid_source');
            }
            if (isset($seen[$id])) {
                throw new \InvalidArgumentException('duplicate_source');
            }
            if (isset($names[$name]) && $names[$name] !== $id) {
                throw new \InvalidArgumentException('conflicting_source');
            }
            $seen[$id] = $license;
            $names[$name] = $id;
        }
        foreach (self::BLOCKED_UPSTREAM as $blocked) {
            if (isset($seen[$blocked])) {
                throw new \InvalidArgumentException('redistribution_unresolved');
            }
        }
    }
}
