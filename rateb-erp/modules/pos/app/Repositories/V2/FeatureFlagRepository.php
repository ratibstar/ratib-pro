<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagContext;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagLayers;
use Rateb\App\Pos\Models\PosSetting;
use Rateb\App\Pos\Models\PosTerminal;
use Rateb\App\Pos\Repositories\V2\Contracts\FeatureFlagCacheInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\FeatureFlagRepositoryInterface;

/**
 * Reads rateb_pos_settings.settings_json and rateb_pos_terminals.device_meta with caching.
 */
final class FeatureFlagRepository implements FeatureFlagRepositoryInterface
{
    public function __construct(
        private readonly PosSetting $posSetting,
        private readonly PosTerminal $posTerminal,
        private readonly FeatureFlagCacheInterface $cache,
    ) {
    }

    public function loadLayers(PosV2FeatureFlagContext $context): PosV2FeatureFlagLayers
    {
        $cacheKey = $context->cacheKey() . ':layers';
        $cached = $this->cache->getLayers($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $layers = new PosV2FeatureFlagLayers(
            terminalV2: $this->loadTerminalV2($context),
            branchV2: $this->loadBranchV2($context),
            companyV2: $this->loadCompanyV2($context),
        );

        $this->cache->setLayers($cacheKey, $layers);

        return $layers;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadTerminalV2(PosV2FeatureFlagContext $context): ?array
    {
        if ($context->terminalId < 1) {
            return null;
        }

        $row = $this->posTerminal->queryOne(
            'SELECT device_meta FROM rateb_pos_terminals
             WHERE id = :tid AND company_id = :cid
             LIMIT 1',
            [
                'tid' => $context->terminalId,
                'cid' => $context->companyId,
            ]
        );

        if ($row === null) {
            return null;
        }

        return $this->extractV2Block($row['device_meta'] ?? null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadBranchV2(PosV2FeatureFlagContext $context): ?array
    {
        if ($context->branchId < 1) {
            return null;
        }

        $row = $this->posSetting->queryOne(
            'SELECT settings_json FROM rateb_pos_settings
             WHERE company_id = :cid AND branch_id = :bid
             LIMIT 1',
            [
                'cid' => $context->companyId,
                'bid' => $context->branchId,
            ]
        );

        if ($row === null) {
            return null;
        }

        return $this->extractV2Block($row['settings_json'] ?? null);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadCompanyV2(PosV2FeatureFlagContext $context): ?array
    {
        $row = $this->posSetting->queryOne(
            'SELECT settings_json FROM rateb_pos_settings
             WHERE company_id = :cid AND (branch_id IS NULL OR branch_id = 0)
             LIMIT 1',
            ['cid' => $context->companyId]
        );

        if ($row === null) {
            return null;
        }

        return $this->extractV2Block($row['settings_json'] ?? null);
    }

    /**
     * @param mixed $raw JSON string or decoded array from DB
     *
     * @return array<string, mixed>|null
     */
    private function extractV2Block(mixed $raw): ?array
    {
        $decoded = $this->decodeJson($raw);
        if ($decoded === null) {
            return null;
        }

        $v2 = $decoded['v2'] ?? null;

        return is_array($v2) ? $v2 : null;
    }

    /**
     * @return array<string, mixed>|null
     */
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
