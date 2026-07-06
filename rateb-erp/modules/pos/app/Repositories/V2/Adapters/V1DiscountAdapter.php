<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartLineNotFoundException;
use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\Domain\V2\Discount\PosV2DiscountType;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Discount\DiscountRequest;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2DiscountPortInterface;
use Rateb\App\Pos\Services\PosSessionService;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartDiscountPreserver;

/** V1 session discount adapter (no V1 modifications, T11). */
final class V1DiscountAdapter implements PosV2DiscountPortInterface
{
    public function __construct(
        private readonly PosSessionService $session = new PosSessionService(),
        private readonly PosV2CartPortInterface $cartPort,
        private readonly PosV2CartDiscountPreserver $discountPreserver = new PosV2CartDiscountPreserver(),
    ) {
    }

    public function applyLineDiscount(PosV2CartScope $scope, string $lineId, DiscountRequest $request): CartResponse
    {
        $lines = $this->readLines();
        $found = false;

        foreach ($lines as &$line) {
            if ((string) ($line['id'] ?? '') !== $lineId) {
                continue;
            }

            $found = true;
            unset($line['discount_amount'], $line['discount_percent']);

            if ($request->type === PosV2DiscountType::Percent) {
                $line['discount_percent'] = (float) $request->value;
            } else {
                $line['discount_amount'] = round((float) $request->value, 2);
            }
        }
        unset($line);

        if (!$found) {
            throw new PosV2CartLineNotFoundException($lineId);
        }

        $normalized = $this->discountPreserver->normalizePreservingDiscounts($lines);
        $this->session->setCartLines($normalized);

        return $this->cartPort->load($scope);
    }

    public function removeLineDiscount(PosV2CartScope $scope, string $lineId): CartResponse
    {
        $lines = $this->readLines();
        $found = false;

        foreach ($lines as &$line) {
            if ((string) ($line['id'] ?? '') !== $lineId) {
                continue;
            }

            $found = true;
            unset($line['discount_amount'], $line['discount_percent']);
        }
        unset($line);

        if (!$found) {
            throw new PosV2CartLineNotFoundException($lineId);
        }

        $normalized = $this->discountPreserver->normalizePreservingDiscounts($lines);
        $this->session->setCartLines($normalized);

        return $this->cartPort->load($scope);
    }

    public function applyCartDiscount(PosV2CartScope $scope, DiscountRequest $request): CartResponse
    {
        $type = $request->type === PosV2DiscountType::Percent ? 'percent' : 'amount';
        $this->session->patch([
            'invoice_discount' => [
                'type' => $type,
                'value' => round((float) $request->value, 2),
            ],
        ]);

        return $this->cartPort->load($scope);
    }

    public function removeCartDiscount(PosV2CartScope $scope): CartResponse
    {
        $this->session->patch(['invoice_discount' => null]);

        return $this->cartPort->load($scope);
    }

    public function readLines(): array
    {
        return $this->session->getCartLines();
    }

    public function readCartDiscount(): array
    {
        $raw = $this->session->current()['invoice_discount'] ?? null;

        return is_array($raw) ? $raw : [];
    }
}
