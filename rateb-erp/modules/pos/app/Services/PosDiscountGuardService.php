<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

/** Enforces pos.discount.manage before manual cashier discounts. */
final class PosDiscountGuardService
{
    /**
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $invoiceDiscount
     */
    public function assertManualDiscountAllowed(array $lines, array $invoiceDiscount): void
    {
        if ($this->canManageDiscount()) {
            return;
        }
        if ($this->hasManualInvoiceDiscount($invoiceDiscount)) {
            throw new \RuntimeException(__('pos_discount_permission_denied'));
        }
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            if ($this->hasManualLineDiscount($line)) {
                throw new \RuntimeException(__('pos_discount_permission_denied'));
            }
        }
    }

    private function canManageDiscount(): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }
        return function_exists('rateb_can') && rateb_can('pos.discount.manage');
    }

    /** @param array<string, mixed> $invoiceDiscount */
    private function hasManualInvoiceDiscount(array $invoiceDiscount): bool
    {
        $value = (float) ($invoiceDiscount['value'] ?? 0);
        return $value > 0.0001;
    }

    /** @param array<string, mixed> $line */
    private function hasManualLineDiscount(array $line): bool
    {
        if ((float) ($line['discount_amount'] ?? 0) > 0.0001) {
            return true;
        }
        return (float) ($line['discount_percent'] ?? 0) > 0.0001;
    }
}
