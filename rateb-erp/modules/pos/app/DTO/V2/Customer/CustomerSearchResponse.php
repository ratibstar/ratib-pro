<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Customer;

/** Customer search result envelope (T10). */
final readonly class CustomerSearchResponse
{
    /**
     * @param list<PosV2CustomerSummaryDto> $customers
     */
    public function __construct(
        public array $customers,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'customers' => array_map(
                static fn (PosV2CustomerSummaryDto $customer): array => $customer->toArray(),
                $this->customers,
            ),
        ];
    }
}
