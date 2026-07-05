<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\DTO\V2\Register\PosV2CompanyContext;

/** Company label lookup for bootstrap (read-only). */
interface PosV2CompanyPortInterface
{
    public function resolve(int $companyId): PosV2CompanyContext;
}
