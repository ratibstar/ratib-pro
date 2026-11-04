<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

/**
 * Filters VehiclesDB records by each record's source ids.
 * A country code is not a license. Records are not written to a database.
 */
final class AutomotiveVehiclesDbFilter
{
    /** @var list<string> */
    public const ALLOWED_LICENSES = [
        'CC-BY-4.0',
        'CC-BY-3.0-AU',
        'OGL-Canada-2.0',
        'DL-DE-BY-2.0',
        'CC0-1.0',
        'OGL-UK-3.0',
        'US-PD',
    ];

    /**
     * @param array<string, mixed> $record
     * @param array<string, string> $sourceLicenses source id => license id
     * @return array{eligible:bool,reason:string,licenses:list<string>,markets:list<string>}
     */
    public static function classify(array $record, array $sourceLicenses): array
    {
        $sources = $record['sources'] ?? null;
        if (!is_array($sources) || $sources === []) {
            return self::excluded('provenance_missing');
        }
        $availability = $record['availability'] ?? null;
        if (!is_array($availability) || $availability === []) {
            return self::excluded('provenance_missing');
        }
        $licenses = [];
        $markets = [];
        foreach ($sources as $sourceId) {
            $sourceId = trim((string) $sourceId);
            if ($sourceId === '' || !isset($sourceLicenses[$sourceId])) {
                return self::excluded('license_unknown');
            }
            $license = $sourceLicenses[$sourceId];
            if (!in_array($license, self::ALLOWED_LICENSES, true)) {
                return self::excluded('redistribution_unresolved');
            }
            $licenses[$license] = $license;
        }
        foreach ($availability as $row) {
            if (!is_array($row)) {
                return self::excluded('provenance_missing');
            }
            $sourceId = trim((string) ($row['source'] ?? ''));
            $country = strtolower(trim((string) ($row['country'] ?? '')));
            if ($sourceId === '' || $country === '' || !in_array($sourceId, $sources, true)) {
                return self::excluded('provenance_missing');
            }
            if (!isset($sourceLicenses[$sourceId]) || !in_array($sourceLicenses[$sourceId], self::ALLOWED_LICENSES, true)) {
                return self::excluded('redistribution_unresolved');
            }
            $markets[$country] = $country;
        }
        $licenses = array_values($licenses);
        sort($licenses);
        $markets = array_values($markets);
        sort($markets);
        return [
            'eligible' => true,
            'reason' => 'eligible',
            'licenses' => $licenses,
            'markets' => $markets,
        ];
    }

    public static function recordKey(array $record): string
    {
        $kind = strtolower(trim((string) ($record['kind'] ?? '')));
        $id = trim((string) ($record['id'] ?? ''));
        if ($kind === '' || $id === '') {
            return '';
        }
        return $kind . ':' . $id;
    }

    /** @return array{eligible:bool,reason:string,licenses:list<string>,markets:list<string>} */
    private static function excluded(string $reason): array
    {
        return ['eligible' => false, 'reason' => $reason, 'licenses' => [], 'markets' => []];
    }
}
