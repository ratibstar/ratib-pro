<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register;

use Rateb\App\Pos\Domain\V2\Exceptions\PosV2RegisterBootstrapValidationException;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/** Validates resolved register bootstrap context (no external queries). */
final class PosV2RegisterBootstrapValidator
{
    public function validate(PosV2RequestContext $context): void
    {
        $register = $context->register;

        if ($register->companyId < 1) {
            throw new PosV2RegisterBootstrapValidationException(
                'COMPANY_REQUIRED',
                'POS V2 register bootstrap requires a valid company scope.',
            );
        }

        if ($register->branchId < 1 || $register->branch === null) {
            throw new PosV2RegisterBootstrapValidationException(
                'BRANCH_REQUIRED',
                'POS V2 register bootstrap requires a valid branch scope.',
            );
        }

        if ($register->terminal === null || $register->terminal->id < 1) {
            throw new PosV2RegisterBootstrapValidationException(
                'TERMINAL_REQUIRED',
                'POS V2 register bootstrap requires a bound terminal.',
            );
        }

        if ($register->cashier->userId < 1) {
            throw new PosV2RegisterBootstrapValidationException(
                'CASHIER_REQUIRED',
                'POS V2 register bootstrap requires an authenticated cashier.',
            );
        }

        if (!$register->featureFlags->enabled) {
            throw new PosV2RegisterBootstrapValidationException(
                'POS_V2_DISABLED',
                'POS V2 is not enabled for this scope.',
            );
        }

        if ($register->profile() === '') {
            throw new PosV2RegisterBootstrapValidationException(
                'PROFILE_REQUIRED',
                'POS V2 register bootstrap requires a resolved profile.',
            );
        }

        if ($register->warehouseId < 1) {
            throw new PosV2RegisterBootstrapValidationException(
                'WAREHOUSE_REQUIRED',
                'POS V2 register bootstrap requires a valid warehouse scope.',
            );
        }

        if ($register->locale === '' || $register->timezone === '' || $register->currency === '') {
            throw new PosV2RegisterBootstrapValidationException(
                'LOCALE_REQUIRED',
                'POS V2 register bootstrap requires locale, timezone, and currency.',
            );
        }
    }
}
