<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Adapters;

/**
 * Shared helper for adapters that hydrate options from Vault/env via caller (never embed secrets in repo).
 *
 * @phpstan-type OptionsMap array<string, mixed>
 */
abstract class AbstractJsonConfigurableAdapter
{
    /**
     * @param OptionsMap $options
     */
    public function __construct(protected readonly array $options = [])
    {}

    /**
     * @return OptionsMap
     */
    protected function options(): array
    {
        return $this->options;
    }
}
