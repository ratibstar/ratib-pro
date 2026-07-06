<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services\V2\Checkout;

use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/** Maps V2 register context to V1 checkout scope arrays. */
final class CheckoutScopeMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(
        PosV2RequestContext $context,
        string $idempotencyKey = '',
        ?string $couponCode = null,
        float $pointsRedeem = 0.0,
        bool $giftReceipt = false,
    ): array {
        $register = $context->register;
        $shiftId = $register->shift?->id ?? 0;
        $terminalId = $register->terminal?->id ?? 0;

        return [
            'company_id' => $register->companyId,
            'user_id' => $register->cashier->userId,
            'branch_id' => $register->branchId,
            'warehouse_id' => $register->warehouseId > 0 ? $register->warehouseId : null,
            'session_id' => $register->sessionId > 0 ? $register->sessionId : null,
            'terminal_id' => $terminalId > 0 ? $terminalId : null,
            'shift_id' => $shiftId > 0 ? $shiftId : null,
            'coupon_code' => $couponCode ?? '',
            'points_redeem' => $pointsRedeem,
            'idempotency_key' => trim($idempotencyKey),
            'gift_receipt' => $giftReceipt,
        ];
    }
}
