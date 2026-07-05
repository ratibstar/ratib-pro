<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Pos\Services\Bridge\PosZatcaBridgeService;

/** Receipt payload for print/display (no accounting). */
final class PosReceiptService
{
    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $lines
     * @param array<int, array<string, mixed>> $payments
     * @param array<string, mixed> $pricing
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function build(
        array $order,
        array $lines,
        array $payments,
        array $pricing,
        array $context = []
    ): array {
        $companyId = (int) ($order['company_id'] ?? 0);
        $receipt = [
            'order_id' => (int) ($order['id'] ?? 0),
            'order_no' => (string) ($order['order_no'] ?? ''),
            'completed_at' => (string) ($order['completed_at'] ?? date('c')),
            'branch' => $context['branch'] ?? null,
            'terminal' => $context['terminal'] ?? null,
            'shift' => $context['shift'] ?? null,
            'customer' => $context['customer'] ?? null,
            'lines' => array_map(static function (array $line): array {
                return [
                    'description' => (string) ($line['description'] ?? ''),
                    'quantity' => (float) ($line['quantity'] ?? 0),
                    'unit_price' => (float) ($line['unit_price'] ?? 0),
                    'discount_amount' => (float) ($line['discount_amount'] ?? 0),
                    'tax_amount' => (float) ($line['tax_amount'] ?? 0),
                    'line_total' => (float) ($line['line_total'] ?? 0),
                    'serial_no' => (string) ($line['serial_no'] ?? ''),
                    'batch_id' => (int) ($line['batch_id'] ?? 0),
                ];
            }, $lines),
            'totals' => [
                'subtotal' => (float) ($pricing['subtotal'] ?? $order['subtotal'] ?? 0),
                'discount_total' => (float) ($pricing['discount_total'] ?? $order['discount_total'] ?? 0),
                'tax' => (float) ($pricing['tax'] ?? $order['tax'] ?? 0),
                'total' => (float) ($pricing['total'] ?? $order['total'] ?? 0),
                'tax_rate' => (float) ($pricing['tax_rate'] ?? 0.15),
            ],
            'payments' => array_map(static function (array $p): array {
                return [
                    'method' => (string) ($p['payment_method'] ?? $p['method'] ?? ''),
                    'amount' => (float) ($p['amount'] ?? 0),
                    'reference_no' => (string) ($p['reference_no'] ?? ''),
                ];
            }, $payments),
        ];

        $receipt['tax_qr'] = (new PosZatcaBridgeService())->receiptQrBase64($companyId, $receipt);

        if (!empty($context['gift_receipt'])) {
            return $this->maskGiftPrices($receipt);
        }

        return $receipt;
    }

    /**
     * @param array<string, mixed> $order
     * @param array<int, array<string, mixed>> $lines
     * @param array<int, array<string, mixed>> $refunds
     * @param array<string, mixed> $pricing
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function buildReturn(
        array $order,
        array $lines,
        array $refunds,
        array $pricing,
        array $context = []
    ): array {
        $base = $this->build($order, $lines, [], $pricing, $context);
        $base['receipt_type'] = 'return';
        $base['original_order_no'] = (string) ($order['original_order_no'] ?? '');
        $base['refunds'] = array_map(static function (array $r): array {
            return [
                'method' => (string) ($r['refund_method'] ?? $r['method'] ?? ''),
                'amount' => (float) ($r['amount'] ?? 0),
                'reference_no' => (string) ($r['reference_no'] ?? ''),
            ];
        }, $refunds);
        unset($base['payments']);
        return $base;
    }

    /**
     * @param array<string, mixed> $meta
     * @param array<int, array<string, mixed>> $payments
     * @param array<int, array<string, mixed>> $refunds
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function buildExchange(
        array $meta,
        array $returnPricing,
        array $salePricing,
        array $payments,
        array $refunds,
        array $context = []
    ): array {
        return [
            'receipt_type' => 'exchange',
            'order_no' => (string) ($meta['order_no'] ?? ''),
            'original_order_no' => (string) ($meta['original_order_no'] ?? ''),
            'completed_at' => (string) ($meta['completed_at'] ?? date('c')),
            'return_totals' => $returnPricing['totals'] ?? $returnPricing,
            'sale_totals' => $salePricing['totals'] ?? $salePricing,
            'net_total' => (float) ($context['net_total'] ?? 0),
            'payments' => $payments,
            'refunds' => $refunds,
            'customer' => $context['customer'] ?? null,
        ];
    }

    /** @param array<string, mixed> $receipt @return array<string, mixed> */
    public function maskGiftPrices(array $receipt): array
    {
        $receipt['receipt_type'] = 'gift';
        $receipt['totals'] = [
            'subtotal' => null,
            'discount_total' => null,
            'tax' => null,
            'total' => null,
        ];
        $receipt['lines'] = array_map(static function (array $line): array {
            $line['unit_price'] = null;
            $line['discount_amount'] = null;
            $line['tax_amount'] = null;
            $line['line_total'] = null;
            return $line;
        }, $receipt['lines'] ?? []);
        unset($receipt['payments'], $receipt['tax_qr']);
        return $receipt;
    }
}
