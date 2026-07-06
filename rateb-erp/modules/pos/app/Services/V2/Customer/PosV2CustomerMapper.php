<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Customer;

use Rateb\App\Pos\DTO\V2\Customer\PosV2CustomerSummaryDto;

/** Maps V1 PosCustomerBridgeService rows to V2 DTOs (T10). */
final class PosV2CustomerMapper
{
    /** @param array<string, mixed> $row */
    public function fromV1Customer(array $row): ?PosV2CustomerSummaryDto
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            return null;
        }

        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        return new PosV2CustomerSummaryDto(
            id: $id,
            name: $name,
            phone: (string) ($row['phone'] ?? ''),
        );
    }
}
