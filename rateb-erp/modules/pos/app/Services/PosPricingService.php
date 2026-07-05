<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

/**
 * POS sell pricing — line/invoice discounts and VAT (15% default).
 */
final class PosPricingService
{
    /**
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed> $invoiceDiscount
     * @return array<string, mixed>
     */
    public function calculate(
        array $lines,
        array $invoiceDiscount = [],
        float $taxRate = 0.15
    ): array {
        $taxRate = max(0, min(1, $taxRate));
        $lineResults = [];
        $subtotal = 0.0;
        $lineDiscountTotal = 0.0;

        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            $qty = max(0, (float) ($line['quantity'] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            $unit = max(0, round((float) ($line['unit_price'] ?? 0), 2));
            $gross = round($qty * $unit, 2);
            $lineDisc = $this->discountAmount($gross, $line);
            $net = max(0, round($gross - $lineDisc, 2));
            $subtotal += $gross;
            $lineDiscountTotal += $lineDisc;

            $lineResults[] = [
                'id' => (string) ($line['id'] ?? ''),
                'product_id' => (int) ($line['product_id'] ?? 0),
                'quantity' => $qty,
                'unit_price' => $unit,
                'gross' => $gross,
                'discount_amount' => $lineDisc,
                'net' => $net,
                'tax_amount' => round($net * $taxRate, 2),
                'line_total' => round($net + ($net * $taxRate), 2),
            ];
        }

        $netSubtotal = max(0, round($subtotal - $lineDiscountTotal, 2));
        $invoiceDisc = $this->invoiceDiscountAmount($netSubtotal, $invoiceDiscount);
        $taxable = max(0, round($netSubtotal - $invoiceDisc, 2));
        $tax = round($taxable * $taxRate, 2);
        $total = round($taxable + $tax, 2);

        return [
            'lines' => $lineResults,
            'subtotal' => round($subtotal, 2),
            'line_discount_total' => round($lineDiscountTotal, 2),
            'net_subtotal' => $netSubtotal,
            'invoice_discount' => round($invoiceDisc, 2),
            'discount_total' => round($lineDiscountTotal + $invoiceDisc, 2),
            'taxable' => $taxable,
            'tax_rate' => $taxRate,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    /** @param array<string, mixed> $line */
    private function discountAmount(float $gross, array $line): float
    {
        $amount = (float) ($line['discount_amount'] ?? 0);
        if ($amount > 0) {
            return min($gross, round($amount, 2));
        }
        $percent = (float) ($line['discount_percent'] ?? 0);
        if ($percent > 0) {
            return min($gross, round($gross * ($percent / 100), 2));
        }
        return 0.0;
    }

    /** @param array<string, mixed> $invoiceDiscount */
    private function invoiceDiscountAmount(float $netSubtotal, array $invoiceDiscount): float
    {
        if ($netSubtotal <= 0) {
            return 0.0;
        }
        $type = (string) ($invoiceDiscount['type'] ?? 'amount');
        $value = (float) ($invoiceDiscount['value'] ?? 0);
        if ($value <= 0) {
            return 0.0;
        }
        if ($type === 'percent') {
            return min($netSubtotal, round($netSubtotal * ($value / 100), 2));
        }
        return min($netSubtotal, round($value, 2));
    }
}
