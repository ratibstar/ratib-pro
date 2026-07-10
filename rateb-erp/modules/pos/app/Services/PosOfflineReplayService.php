<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Services;

/**
 * Dispatches offline queue actions to existing POS domain services.
 * No business logic duplication — thin replay adapter only (Phase 2B).
 */
final class PosOfflineReplayService
{
    /**
     * Actions that invoke domain services (others are acknowledged as synced).
     *
     * @return list<string>
     */
    public static function deferredActions(): array
    {
        return [
            'complete_sale',
            'checkout',
            'process_return',
            'process_exchange',
            'suspend',
            'resume_suspended',
            'shift_open',
            'shift_close',
            'drawer_event',
        ];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    public function replay(string $action, array $scope, array $inner): array
    {
        return match ($action) {
            'checkout', 'complete_sale' => $this->checkout($scope, $inner),
            'process_return' => $this->processReturn($scope, $inner),
            'process_exchange' => $this->processExchange($scope, $inner),
            'suspend' => $this->suspend($scope, $inner),
            'resume_suspended' => $this->resumeSuspended($scope, $inner),
            'shift_open' => $this->shiftOpen($scope, $inner),
            'shift_close' => $this->shiftClose($scope, $inner),
            'drawer_event' => $this->drawerEvent($scope, $inner),
            default => ['ok' => true, 'skipped' => true],
        };
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function checkout(array $scope, array $inner): array
    {
        $lines = is_array($inner['lines'] ?? null) ? $inner['lines'] : [];
        $payments = is_array($inner['payments'] ?? null) ? $inner['payments'] : [];
        $invoiceDiscount = is_array($inner['invoice_discount'] ?? null) ? $inner['invoice_discount'] : [];
        $customer = is_array($inner['customer'] ?? null) ? $inner['customer'] : null;
        $taxRate = (float) ($inner['tax_rate'] ?? 0.15);
        if ($lines === [] || $payments === []) {
            throw new \RuntimeException('empty_checkout_payload');
        }
        $scope['coupon_code'] = trim((string) ($inner['coupon_code'] ?? ''));
        $scope['points_redeem'] = (float) ($inner['points_redeem'] ?? 0);

        return (new PosCheckoutService())->complete(
            $lines,
            $payments,
            $invoiceDiscount,
            $scope,
            $customer,
            $taxRate,
            (bool) ($inner['gift_receipt'] ?? $scope['gift_receipt'] ?? false)
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function processReturn(array $scope, array $inner): array
    {
        $returnLines = is_array($inner['return_lines'] ?? null) ? $inner['return_lines'] : [];
        $refunds = is_array($inner['refunds'] ?? null) ? $inner['refunds'] : [];
        $originalOrderId = (int) ($inner['original_order_id'] ?? 0);
        $customer = is_array($inner['customer'] ?? null) ? $inner['customer'] : null;
        if ($originalOrderId < 1 || $returnLines === []) {
            throw new \RuntimeException('empty_return_payload');
        }

        return (new PosReturnService())->process($originalOrderId, $returnLines, $refunds, $scope, $customer);
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function processExchange(array $scope, array $inner): array
    {
        $returnLines = is_array($inner['return_lines'] ?? null) ? $inner['return_lines'] : [];
        $saleLines = is_array($inner['sale_lines'] ?? null) ? $inner['sale_lines'] : [];
        $payments = is_array($inner['payments'] ?? null) ? $inner['payments'] : [];
        $refunds = is_array($inner['refunds'] ?? null) ? $inner['refunds'] : [];
        $invoiceDiscount = is_array($inner['invoice_discount'] ?? null) ? $inner['invoice_discount'] : [];
        $customer = is_array($inner['customer'] ?? null) ? $inner['customer'] : null;
        $originalOrderId = (int) ($inner['original_order_id'] ?? 0);
        if ($originalOrderId < 1 || $returnLines === [] || $saleLines === []) {
            throw new \RuntimeException('empty_exchange_payload');
        }
        $scope['coupon_code'] = trim((string) ($inner['coupon_code'] ?? ''));
        $scope['points_redeem'] = (float) ($inner['points_redeem'] ?? 0);

        return (new PosExchangeService())->processExchange(
            $originalOrderId,
            $returnLines,
            $saleLines,
            $payments,
            $refunds,
            $scope,
            $customer,
            $invoiceDiscount
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function suspend(array $scope, array $inner): array
    {
        $lines = is_array($inner['lines'] ?? null) ? $inner['lines'] : [];
        $customer = is_array($inner['customer'] ?? null) ? $inner['customer'] : null;
        $notes = trim((string) ($inner['notes'] ?? ''));

        return (new PosSuspendService())->suspend($lines, $scope, $customer, $notes);
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function resumeSuspended(array $scope, array $inner): array
    {
        $orderId = (int) ($inner['order_id'] ?? $inner['suspended_order_id'] ?? 0);
        if ($orderId < 1) {
            throw new \RuntimeException('missing_suspended_order_id');
        }

        return (new PosSuspendService())->resume(
            $orderId,
            (int) ($scope['company_id'] ?? 0),
            (int) ($scope['branch_id'] ?? 0),
            (int) ($scope['user_id'] ?? 0)
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function shiftOpen(array $scope, array $inner): array
    {
        $terminalId = (int) ($inner['terminal_id'] ?? $scope['terminal_id'] ?? 0);
        $openingFloat = (float) ($inner['opening_float'] ?? 0);
        try {
            $shiftId = (new PosShiftService())->openShift(
                (int) ($scope['company_id'] ?? 0),
                $terminalId,
                (int) ($scope['user_id'] ?? 0),
                $openingFloat
            );
        } catch (\RuntimeException $e) {
            // Normalize i18n messages to a stable conflict code for multi-terminal sync.
            $msg = $e->getMessage();
            if (
                $msg === __('pos_shift_already_open')
                || str_contains(strtolower($msg), 'already has an open shift')
                || str_contains($msg, 'وردية مفتوحة')
            ) {
                throw new \RuntimeException('pos_shift_already_open');
            }
            throw $e;
        }

        return ['ok' => true, 'shift_id' => $shiftId];
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function shiftClose(array $scope, array $inner): array
    {
        $shiftId = (int) ($inner['shift_id'] ?? $scope['shift_id'] ?? 0);
        if ($shiftId < 1) {
            throw new \RuntimeException('missing_shift_id');
        }
        $closingFloat = (float) ($inner['closing_float'] ?? $inner['counted_balance'] ?? 0);
        $notes = trim((string) ($inner['notes'] ?? ''));

        return (new PosShiftService())->closeShift(
            $shiftId,
            (int) ($scope['company_id'] ?? 0),
            (int) ($scope['user_id'] ?? 0),
            $closingFloat,
            $notes
        );
    }

    /**
     * @param array<string, mixed> $scope
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function drawerEvent(array $scope, array $inner): array
    {
        $companyId = (int) ($scope['company_id'] ?? 0);
        $shiftId = (int) ($inner['shift_id'] ?? $scope['shift_id'] ?? 0);
        $drawerId = (int) ($inner['drawer_id'] ?? 0);
        $drawers = new PosCashDrawerService();
        if ($drawerId < 1) {
            $open = $drawers->findOpenByShift($shiftId, $companyId);
            $drawerId = (int) ($open['id'] ?? 0);
        }
        if ($drawerId < 1) {
            throw new \RuntimeException('pos_drawer_not_found');
        }
        $eventId = $drawers->recordManualEvent(
            $drawerId,
            $companyId,
            (int) ($scope['user_id'] ?? 0),
            trim((string) ($inner['event_type'] ?? '')),
            (float) ($inner['amount'] ?? 0),
            trim((string) ($inner['notes'] ?? ''))
        );

        return ['ok' => true, 'event_id' => $eventId, 'drawer_id' => $drawerId];
    }
}
