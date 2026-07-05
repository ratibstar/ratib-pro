<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2MergedPosSettings;
use Rateb\App\Pos\Models\PosSetting;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosSettingsCacheInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosSettingsPortInterface;

/** Reads and merges company + branch rateb_pos_settings.settings_json. */
final class PosSettingsRepository implements PosV2PosSettingsPortInterface
{
    public function __construct(
        private readonly PosSetting $posSetting,
        private readonly PosV2PosSettingsCacheInterface $cache,
    ) {
    }

    public function loadMerged(int $companyId, int $branchId): PosV2MergedPosSettings
    {
        $cacheKey = $companyId . ':' . $branchId;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $companyRoot = $this->loadScope($companyId, 0);
        $branchRoot = $branchId > 0 ? $this->loadScope($companyId, $branchId) : null;

        $mergedRoot = $this->mergeRoot($companyRoot, $branchRoot);
        $mergedV2 = $this->mergeV2(
            $this->extractV2($companyRoot),
            $this->extractV2($branchRoot),
        );

        $settings = new PosV2MergedPosSettings(
            companyId: $companyId,
            branchId: $branchId,
            found: $companyRoot !== null || $branchRoot !== null,
            root: $mergedRoot,
            v2: $mergedV2,
        );

        $this->cache->set($cacheKey, $settings);

        return $settings;
    }

    /** @return array<string, mixed>|null */
    private function loadScope(int $companyId, int $branchId): ?array
    {
        if ($companyId < 1) {
            return null;
        }

        if ($branchId > 0) {
            $row = $this->posSetting->queryOne(
                'SELECT settings_json FROM rateb_pos_settings
                 WHERE company_id = :cid AND branch_id = :bid
                 LIMIT 1',
                ['cid' => $companyId, 'bid' => $branchId],
            );
        } else {
            $row = $this->posSetting->queryOne(
                'SELECT settings_json FROM rateb_pos_settings
                 WHERE company_id = :cid AND (branch_id IS NULL OR branch_id = 0)
                 LIMIT 1',
                ['cid' => $companyId],
            );
        }

        if ($row === null) {
            return null;
        }

        return $this->decodeJson($row['settings_json'] ?? null);
    }

    /**
     * @param array<string, mixed>|null $base
     * @param array<string, mixed>|null $override
     *
     * @return array<string, mixed>
     */
    private function mergeRoot(?array $base, ?array $override): array
    {
        if ($base === null) {
            return $override ?? [];
        }

        if ($override === null) {
            return $base;
        }

        return array_replace_recursive($base, $override);
    }

    /**
     * @param array<string, mixed>|null $base
     * @param array<string, mixed>|null $override
     *
     * @return array<string, mixed>|null
     */
    private function mergeV2(?array $base, ?array $override): ?array
    {
        if ($base === null && $override === null) {
            return null;
        }

        if ($base === null) {
            return $override;
        }

        if ($override === null) {
            return $base;
        }

        return array_replace_recursive($base, $override);
    }

    /** @return array<string, mixed>|null */
    private function extractV2(?array $root): ?array
    {
        if ($root === null) {
            return null;
        }

        $v2 = $root['v2'] ?? null;

        return is_array($v2) ? $v2 : null;
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
