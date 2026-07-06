<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2PermissionsContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2RegisterBootstrapMetadata;
use Rateb\App\Pos\DTO\V2\Register\PosV2RegisterBootstrapRegister;
use Rateb\App\Pos\DTO\V2\Register\RegisterBootstrapResponse;

/** Assembles RegisterBootstrapResponse from context and provider bundle. */
final class PosV2RegisterBootstrapAssembler
{
    public function __construct(
        private readonly RegisterBootstrapProvidersOrchestrator $providers,
    ) {
    }

    public function assemble(PosV2RequestContext $context): RegisterBootstrapResponse
    {
        $register = $context->register;
        $bundle = $this->providers->provide($context);

        return new RegisterBootstrapResponse(
            register: new PosV2RegisterBootstrapRegister(
                sessionId: $register->sessionId,
                warehouseId: $register->warehouseId,
                registerReady: $register->registerReady,
                rtl: $register->rtl,
            ),
            terminal: $register->terminal,
            branch: $register->branch,
            warehouse: $bundle->warehouse,
            company: $bundle->company,
            shift: $register->shift,
            cashier: $register->cashier,
            permissions: new PosV2PermissionsContext(slugs: $register->permissions),
            featureFlags: $register->featureFlags,
            locale: $bundle->locale,
            timezone: $register->timezone,
            currency: $bundle->currency,
            profile: $register->profile(),
            posSettings: $bundle->posSettings,
            receiptSettings: $bundle->receiptSettings,
            taxSettings: $bundle->taxSettings,
            profiles: $bundle->profiles,
            capabilities: $bundle->capabilities,
            catalog: $bundle->catalog,
            cart: $bundle->cart,
            customer: $bundle->customer,
            metadata: new PosV2RegisterBootstrapMetadata(
                version: '2',
                channel: $context->channel,
                httpMethod: $context->httpMethod,
                requestPath: $context->requestPath,
            ),
        );
    }
}
