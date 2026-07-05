<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2;

use Rateb\App\Pos\Domain\V2\Enums\PosV2FeatureFlag;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2FeatureFlagContext;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2ResolvedFeatureFlags;
use Rateb\App\Pos\Repositories\V2\Contracts\FeatureFlagCacheInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\FeatureFlagRepositoryInterface;

/**
 * Resolves POS V2 feature flags for company / branch / terminal scope with caching.
 */
final class PosV2FeatureFlagService
{
    public function __construct(
        private readonly FeatureFlagRepositoryInterface $repository,
        private readonly PosV2FeatureFlagResolver $resolver,
        private readonly FeatureFlagCacheInterface $cache,
    ) {
    }

    public function isEnabled(PosV2FeatureFlagContext $context): bool
    {
        return $this->resolve($context)->enabled;
    }

    public function profile(PosV2FeatureFlagContext $context): string
    {
        return $this->resolve($context)->profile;
    }

    public function isScanMode(PosV2FeatureFlagContext $context): bool
    {
        return $this->resolve($context)->scanMode;
    }

    public function isOffline(PosV2FeatureFlagContext $context): bool
    {
        return $this->resolve($context)->offline;
    }

    public function isCardTerminal(PosV2FeatureFlagContext $context): bool
    {
        return $this->resolve($context)->cardTerminal;
    }

    public function resolve(PosV2FeatureFlagContext $context): PosV2ResolvedFeatureFlags
    {
        $cacheKey = $context->cacheKey();
        $cached = $this->cache->getResolved($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $layers = $this->repository->loadLayers($context);
        $resolved = $this->resolver->resolve($layers);
        $this->cache->setResolved($cacheKey, $resolved);

        return $resolved;
    }

    public function get(PosV2FeatureFlagContext $context, PosV2FeatureFlag $flag): bool|string
    {
        $resolved = $this->resolve($context);

        return match ($flag) {
            PosV2FeatureFlag::Enabled => $resolved->enabled,
            PosV2FeatureFlag::Profile => $resolved->profile,
            PosV2FeatureFlag::ScanMode => $resolved->scanMode,
            PosV2FeatureFlag::Offline => $resolved->offline,
            PosV2FeatureFlag::CardTerminal => $resolved->cardTerminal,
        };
    }

    public function forgetCache(PosV2FeatureFlagContext $context): void
    {
        $this->cache->forget($context->cacheKey());
        $this->cache->forget($context->cacheKey() . ':layers');
    }

    public function flushCache(): void
    {
        $this->cache->flush();
    }
}
