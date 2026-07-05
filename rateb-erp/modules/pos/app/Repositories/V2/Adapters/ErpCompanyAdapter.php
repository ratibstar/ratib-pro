<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Models\Company;
use Rateb\App\Pos\DTO\V2\Register\PosV2CompanyContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CompanyPortInterface;

/** Wraps ERP Company model — read-only label lookup. */
final class ErpCompanyAdapter implements PosV2CompanyPortInterface
{
    public function __construct(
        private readonly Company $companies = new Company(),
    ) {
    }

    public function resolve(int $companyId): PosV2CompanyContext
    {
        if ($companyId < 1) {
            return new PosV2CompanyContext(id: 0, name: null);
        }

        $row = $this->companies->find($companyId);
        if (!is_array($row)) {
            return new PosV2CompanyContext(id: $companyId, name: null);
        }

        $name = trim((string) ($row['name'] ?? ''));

        return new PosV2CompanyContext(
            id: $companyId,
            name: $name !== '' ? $name : null,
        );
    }
}
