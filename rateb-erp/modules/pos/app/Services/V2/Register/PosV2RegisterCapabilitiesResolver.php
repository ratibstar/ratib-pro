<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register;

use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2RegisterCapabilities;

/** Derives register capabilities from resolved context (no external queries). */
final class PosV2RegisterCapabilitiesResolver
{
    public function resolve(PosV2RegisterContext $register): PosV2RegisterCapabilities
    {
        return new PosV2RegisterCapabilities(
            registerAccess: $this->hasPermission($register, 'pos.register'),
            shiftOpen: $this->hasPermission($register, 'pos.shift.open'),
            shiftClose: $this->hasPermission($register, 'pos.shift.close'),
            scanMode: $register->featureFlags->scanMode,
            offlineMode: $register->featureFlags->offline,
            cardTerminal: $register->featureFlags->cardTerminal,
            manageSettings: $this->hasPermission($register, 'pos.settings.manage'),
            manageTerminals: $this->hasPermission($register, 'pos.terminal.manage'),
            returns: $this->hasPermission($register, 'pos.returns.manage'),
            discounts: $this->hasPermission($register, 'pos.discount.manage'),
        );
    }

    private function hasPermission(PosV2RegisterContext $register, string $slug): bool
    {
        if (in_array('pos.*', $register->permissions, true)) {
            return true;
        }

        return in_array($slug, $register->permissions, true);
    }
}
