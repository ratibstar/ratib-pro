<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register\Providers;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2LocaleContext;

final class LocaleProvider
{
    public function provide(PosV2RequestContext $context): PosV2LocaleContext
    {
        $register = $context->register;

        return new PosV2LocaleContext(
            code: $register->locale,
            rtl: $register->rtl,
        );
    }
}
