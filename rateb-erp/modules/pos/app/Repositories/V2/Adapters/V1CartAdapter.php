<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartLineNotFoundException;
use Rateb\App\Pos\Domain\V2\Cart\Exceptions\PosV2CartOperationFailedException;
use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Services\Bridge\PosInventoryBridgeService;
use Rateb\App\Pos\Services\PosRegisterCartService;
use Rateb\App\Pos\Services\PosSessionService;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAssembler;

/** V1 session cart adapter (no V1 modifications, T09). */
final class V1CartAdapter implements PosV2CartPortInterface
{
    public function __construct(
        private readonly PosSessionService $session = new PosSessionService(),
        private readonly PosRegisterCartService $cart = new PosRegisterCartService(),
        private readonly PosInventoryBridgeService $inventory = new PosInventoryBridgeService(),
        private readonly PosV2CartAssembler $assembler = new PosV2CartAssembler(),
    ) {
    }

    public function load(PosV2CartScope $scope): CartResponse
    {
        $lines = $this->cart->normalizeLines($this->session->getCartLines());

        return $this->assembler->assemble($scope, $lines);
    }

    public function addLine(PosV2CartScope $scope, int $productId, string $qty): CartResponse
    {
        $product = $this->inventory->getProduct(
            $productId,
            $scope->companyId,
            $scope->warehouseId > 0 ? $scope->warehouseId : null,
            $scope->branchId > 0 ? $scope->branchId : null,
            $scope->sessionId > 0 ? $scope->sessionId : null,
        );

        if ($product === null) {
            throw new PosV2CartOperationFailedException(
                sprintf('Product %d was not found.', $productId),
                'PRODUCT_NOT_FOUND',
            );
        }

        $currentLines = $this->session->getCartLines();
        $result = $this->cart->addProduct(
            $currentLines,
            $product,
            (float) $qty,
            $scope->companyId,
            $scope->warehouseId > 0 ? $scope->warehouseId : null,
            $scope->branchId > 0 ? $scope->branchId : null,
            $scope->sessionId > 0 ? $scope->sessionId : null,
        );

        if (!($result['ok'] ?? false)) {
            throw new PosV2CartOperationFailedException(
                (string) ($result['error'] ?? 'Unable to add product to cart.'),
                'CART_OPERATION_FAILED',
            );
        }

        $lines = $result['lines'] ?? [];
        $this->persistLines($scope, $lines);

        return $this->assembler->assemble($scope, $lines);
    }

    public function updateLine(PosV2CartScope $scope, string $lineId, string $qty): CartResponse
    {
        $trimmedLineId = trim($lineId);
        if ($trimmedLineId === '') {
            throw new PosV2CartLineNotFoundException($lineId);
        }

        $lines = $this->session->getCartLines();
        $found = false;
        $newLines = [];

        foreach ($lines as $line) {
            if ((string) ($line['id'] ?? '') !== $trimmedLineId) {
                $newLines[] = $line;
                continue;
            }

            $found = true;
            $invId = (int) ($line['product_id'] ?? 0);
            $effectiveSerial = trim((string) ($line['serial_no'] ?? ''));
            $check = $this->inventory->validateCartLine(
                $invId,
                (float) $qty,
                $scope->companyId,
                $scope->warehouseId > 0 ? $scope->warehouseId : null,
                $scope->branchId > 0 ? $scope->branchId : null,
                $scope->sessionId > 0 ? $scope->sessionId : null,
                $newLines,
                $trimmedLineId,
                $effectiveSerial !== '' ? $effectiveSerial : null,
            );

            if (!($check['ok'] ?? false)) {
                throw new PosV2CartOperationFailedException(
                    (string) ($check['error'] ?? 'Unable to update cart line.'),
                    'CART_OPERATION_FAILED',
                );
            }

            $line['quantity'] = (float) $qty;
            $line['available_qty'] = (float) ($check['available'] ?? 0);
            $line['batch_preview'] = $check['batch_preview'] ?? [];
            $line['line_total'] = round((float) $qty * (float) ($line['unit_price'] ?? 0), 2);
            $newLines[] = $line;
        }

        if (!$found) {
            throw new PosV2CartLineNotFoundException($trimmedLineId);
        }

        $normalized = $this->cart->normalizeLines($newLines);
        $this->persistLines($scope, $normalized);

        return $this->assembler->assemble($scope, $normalized);
    }

    public function removeLine(PosV2CartScope $scope, string $lineId): CartResponse
    {
        $trimmedLineId = trim($lineId);
        if ($trimmedLineId === '') {
            throw new PosV2CartLineNotFoundException($lineId);
        }

        $lines = $this->session->getCartLines();
        $found = false;
        $remaining = [];

        foreach ($lines as $line) {
            if ((string) ($line['id'] ?? '') === $trimmedLineId) {
                $found = true;
                continue;
            }
            $remaining[] = $line;
        }

        if (!$found) {
            throw new PosV2CartLineNotFoundException($trimmedLineId);
        }

        $normalized = $this->cart->normalizeLines($remaining);
        $this->persistLines($scope, $normalized);

        return $this->assembler->assemble($scope, $normalized);
    }

    public function clear(PosV2CartScope $scope): CartResponse
    {
        $this->session->setCartLines($this->cart->clear());

        return $this->assembler->assemble($scope, []);
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     */
    private function persistLines(PosV2CartScope $scope, array $lines): void
    {
        $this->inventory->validateAndSyncCart(
            $lines,
            $scope->companyId,
            $scope->branchId,
            $scope->warehouseId > 0 ? $scope->warehouseId : null,
            $scope->sessionId > 0 ? $scope->sessionId : null,
        );
        $this->session->setCartLines($lines);
    }
}
