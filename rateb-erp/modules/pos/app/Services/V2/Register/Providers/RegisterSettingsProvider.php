<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Register\Providers;

use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2MergedPosSettings;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2PosDiscountSettingsContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2PosSettingsContext;
use Rateb\App\Pos\DTO\V2\Register\PosV2PosUiSettingsContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosSettingsPortInterface;

final class RegisterSettingsProvider
{
    public function __construct(
        private readonly PosV2PosSettingsPortInterface $settings,
    ) {
    }

    public function provide(PosV2RequestContext $context): ?PosV2PosSettingsContext
    {
        $register = $context->register;
        $merged = $this->settings->loadMerged($register->companyId, $register->branchId);

        if (!$merged->found) {
            return null;
        }

        return $this->mapSettings($merged);
    }

    private function mapSettings(PosV2MergedPosSettings $merged): PosV2PosSettingsContext
    {
        $root = $merged->root;
        $v2 = $merged->v2 ?? [];

        return new PosV2PosSettingsContext(
            schemaVersion: $this->optionalInt($root['schema_version'] ?? null),
            enabled: $this->optionalBool($v2['enabled'] ?? null),
            profile: $this->optionalString($v2['profile'] ?? null),
            ui: $this->mapUi(is_array($v2['ui'] ?? null) ? $v2['ui'] : null),
            discounts: $this->mapDiscounts(is_array($v2['discounts'] ?? null) ? $v2['discounts'] : null),
        );
    }

    /** @param array<string, mixed>|null $ui */
    private function mapUi(?array $ui): ?PosV2PosUiSettingsContext
    {
        if ($ui === null || $ui === []) {
            return null;
        }

        return new PosV2PosUiSettingsContext(
            localeDefault: $this->optionalString($ui['locale_default'] ?? null),
            rtl: $this->optionalBool($ui['rtl'] ?? null),
            catalogColumns: $this->optionalInt($ui['catalog_columns'] ?? null),
            showProductImages: $this->optionalBool($ui['show_product_images'] ?? null),
            chargeButtonLabel: $this->optionalString($ui['charge_button_label'] ?? null),
            idleTimeoutMinutes: $this->optionalInt($ui['idle_timeout_minutes'] ?? null),
        );
    }

    /** @param array<string, mixed>|null $discounts */
    private function mapDiscounts(?array $discounts): ?PosV2PosDiscountSettingsContext
    {
        if ($discounts === null || $discounts === []) {
            return null;
        }

        return new PosV2PosDiscountSettingsContext(
            maxCashierPercent: $this->optionalFloat($discounts['max_cashier_percent'] ?? null),
            maxSupervisorPercent: $this->optionalFloat($discounts['max_supervisor_percent'] ?? null),
            requireReasonAbovePercent: $this->optionalFloat($discounts['require_reason_above_percent'] ?? null),
            allowLineDiscount: $this->optionalBool($discounts['allow_line_discount'] ?? null),
            allowCartDiscount: $this->optionalBool($discounts['allow_cart_discount'] ?? null),
        );
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }

    private function optionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function optionalFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function optionalBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }
}
