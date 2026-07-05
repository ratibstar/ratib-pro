<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register\Providers;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2CurrencyContext;

final class CurrencyProvider
{
    public function provide(PosV2RequestContext $context): PosV2CurrencyContext
    {
        return new PosV2CurrencyContext(code: $context->register->currency);
    }
}
