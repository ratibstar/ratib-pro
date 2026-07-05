<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register\Providers;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2TaxSettingsContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosSettingsPortInterface;

final class TaxSettingsProvider
{
    public function __construct(
        private readonly PosV2PosSettingsPortInterface $settings,
    ) {
    }

    public function provide(PosV2RequestContext $context): ?PosV2TaxSettingsContext
    {
        $register = $context->register;
        $merged = $this->settings->loadMerged($register->companyId, $register->branchId);

        if (!$merged->found) {
            return null;
        }

        $rate = $this->resolveRate($merged->root, $merged->v2);
        $pricesIncludeTax = $this->resolvePricesIncludeTax($merged->root, $merged->v2);

        if ($rate === null && $pricesIncludeTax === null) {
            return null;
        }

        return new PosV2TaxSettingsContext(
            rate: $rate,
            pricesIncludeTax: $pricesIncludeTax,
        );
    }

    /** @param array<string, mixed> $root @param array<string, mixed>|null $v2 */
    private function resolveRate(array $root, ?array $v2): ?float
    {
        foreach ([
            $root['tax_rate'] ?? null,
            is_array($root['tax'] ?? null) ? ($root['tax']['rate'] ?? null) : null,
            is_array($v2['tax'] ?? null) ? ($v2['tax']['rate'] ?? null) : null,
        ] as $candidate) {
            $rate = $this->optionalFloat($candidate);
            if ($rate !== null) {
                return $rate;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $root @param array<string, mixed>|null $v2 */
    private function resolvePricesIncludeTax(array $root, ?array $v2): ?bool
    {
        foreach ([
            is_array($root['tax'] ?? null) ? ($root['tax']['prices_include_tax'] ?? null) : null,
            is_array($v2['tax'] ?? null) ? ($v2['tax']['prices_include_tax'] ?? null) : null,
        ] as $candidate) {
            if ($candidate !== null) {
                return (bool) $candidate;
            }
        }

        return null;
    }

    private function optionalFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
