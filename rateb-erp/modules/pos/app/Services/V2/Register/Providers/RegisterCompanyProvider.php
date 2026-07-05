<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register\Providers;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2CompanyContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CompanyPortInterface;

final class RegisterCompanyProvider
{
    public function __construct(
        private readonly PosV2CompanyPortInterface $companies,
    ) {
    }

    public function provide(PosV2RequestContext $context): PosV2CompanyContext
    {
        return $this->companies->resolve($context->register->companyId);
    }
}
