<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Discount;

use Rateb\App\Pos\Domain\V2\Discount\Exceptions\PosV2DiscountValidationException;
use Rateb\App\Pos\Domain\V2\Discount\PosV2DiscountType;
use Rateb\App\Pos\Domain\V2\ValueObjects\PosV2Money;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Discount\DiscountRequest;
use Rateb\App\Pos\DTO\V2\Discount\DiscountValidationResult;
use Rateb\App\Pos\DTO\V2\Register\PosV2PosDiscountSettingsContext;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosSettingsPortInterface;

/** Validates discount requests against business rules and settings (T11). */
final class DiscountValidator
{
    public function __construct(
        private readonly DiscountCalculator $calculator = new DiscountCalculator(),
        private readonly ?PosV2PosSettingsPortInterface $settings = null,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    public function validateLineApply(
        PosV2RequestContext $context,
        array $lines,
        string $lineId,
        DiscountRequest $request,
    ): DiscountValidationResult {
        if ($lines === []) {
            return DiscountValidationResult::fail('CART_EMPTY', 'Cart must contain at least one line.');
        }

        $line = $this->findLine($lines, $lineId);
        if ($line === null) {
            return DiscountValidationResult::fail('LINE_NOT_FOUND', sprintf('Cart line %s was not found.', $lineId));
        }

        $settings = $this->discountSettings($context);
        if ($settings !== null && $settings->allowLineDiscount === false) {
            return DiscountValidationResult::fail('LINE_DISCOUNT_DISABLED', 'Line discounts are disabled for this register.');
        }

        return $this->validateAmount(
            $context,
            $request,
            $this->calculator->lineGross($line, $context->register->currency),
            $settings,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    public function validateCartApply(
        PosV2RequestContext $context,
        array $lines,
        DiscountRequest $request,
    ): DiscountValidationResult {
        if ($lines === []) {
            return DiscountValidationResult::fail('CART_EMPTY', 'Cart must contain at least one line.');
        }

        $settings = $this->discountSettings($context);
        if ($settings !== null && $settings->allowCartDiscount === false) {
            return DiscountValidationResult::fail('CART_DISCOUNT_DISABLED', 'Cart discounts are disabled for this register.');
        }

        $currency = $context->register->currency;
        $netSubtotal = $this->calculator->netSubtotalAfterLineDiscounts($lines, $currency);
        if ((float) $netSubtotal->amount <= 0) {
            return DiscountValidationResult::fail('CART_SUBTOTAL_ZERO', 'Cart subtotal must be greater than zero.');
        }

        return $this->validateAmount($context, $request, $netSubtotal, $settings);
    }

    public function assertValid(DiscountValidationResult $result): void
    {
        if ($result->valid) {
            return;
        }

        throw new PosV2DiscountValidationException($result->errorCode, $result->message);
    }

    private function validateAmount(
        PosV2RequestContext $context,
        DiscountRequest $request,
        PosV2Money $maxBase,
        ?PosV2PosDiscountSettingsContext $settings,
    ): DiscountValidationResult {
        $currency = $context->register->currency;
        $value = (float) $request->value;
        $maxValue = (float) $maxBase->amount;

        if ($request->type === PosV2DiscountType::Percent) {
            if ($value > 100) {
                return DiscountValidationResult::fail(
                    'DISCOUNT_PERCENT_TOO_HIGH',
                    'Percent discount cannot exceed 100.',
                );
            }

            $maxPercent = $settings?->maxCashierPercent;
            if ($maxPercent !== null && $value > $maxPercent) {
                return DiscountValidationResult::fail(
                    'DISCOUNT_PERCENT_EXCEEDS_LIMIT',
                    sprintf('Percent discount cannot exceed %.2f for this cashier.', $maxPercent),
                );
            }

            $amount = min($maxValue, round($maxValue * ($value / 100), 2));
        } else {
            if ($value > $maxValue) {
                return DiscountValidationResult::fail(
                    'DISCOUNT_EXCEEDS_SUBTOTAL',
                    'Discount cannot exceed the applicable subtotal.',
                );
            }

            $amount = round($value, 2);
        }

        $computed = PosV2Money::fromDecimalString(number_format($amount, 2, '.', ''), $currency);

        if ($computed->isGreaterThan($maxBase)) {
            return DiscountValidationResult::fail(
                'DISCOUNT_EXCEEDS_SUBTOTAL',
                'Discount cannot exceed the applicable subtotal.',
            );
        }

        return DiscountValidationResult::ok();
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array<string, mixed>|null
     */
    private function findLine(array $lines, string $lineId): ?array
    {
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            if ((string) ($line['id'] ?? '') === $lineId) {
                return $line;
            }
        }

        return null;
    }

    private function discountSettings(PosV2RequestContext $context): ?PosV2PosDiscountSettingsContext
    {
        if ($this->settings === null) {
            return null;
        }

        $register = $context->register;
        $merged = $this->settings->loadMerged($register->companyId, $register->branchId);
        if (!$merged->found) {
            return null;
        }

        $v2 = $merged->v2 ?? [];
        $discounts = is_array($v2['discounts'] ?? null) ? $v2['discounts'] : null;
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

    private function optionalFloat(mixed $value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
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
