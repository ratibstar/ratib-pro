<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Payment;

use Rateb\App\Pos\DTO\V2\Cart\PosV2CartTotalsDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

final readonly class CompleteSaleResponse
{
    public function __construct(
        public int $orderId,
        public string $orderNo,
        public PosV2CartTotalsDto $totals,
        public array $receipt,
        public PosV2MoneyDto $changeDue,
        public bool $idempotent = false,
    ) {
    }

    /** @param array<string, mixed> $v1Result @param array<string, mixed> $pricing */
    public static function fromV1Result(
        array $v1Result,
        PosV2MoneyDto $changeDue,
        string $currency,
    ): self {
        $pricing = is_array($v1Result['pricing'] ?? null) ? $v1Result['pricing'] : [];
        $subtotal = (float) ($pricing['subtotal'] ?? 0);
        $discount = (float) ($pricing['invoice_discount'] ?? $pricing['discount'] ?? 0);
        $tax = (float) ($pricing['tax'] ?? 0);
        $total = (float) ($pricing['total'] ?? 0);

        $totals = new PosV2CartTotalsDto(
            subtotal: new PosV2MoneyDto(number_format($subtotal, 2, '.', ''), $currency),
            discount: new PosV2MoneyDto(number_format($discount, 2, '.', ''), $currency),
            tax: new PosV2MoneyDto(number_format($tax, 2, '.', ''), $currency),
            total: new PosV2MoneyDto(number_format($total, 2, '.', ''), $currency),
        );

        $receipt = is_array($v1Result['receipt'] ?? null) ? $v1Result['receipt'] : [];

        return new self(
            orderId: (int) ($v1Result['order_id'] ?? 0),
            orderNo: (string) ($v1Result['order_no'] ?? ''),
            totals: $totals,
            receipt: $receipt,
            changeDue: $changeDue,
            idempotent: !empty($v1Result['idempotent']),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'order_no' => $this->orderNo,
            'totals' => $this->totals->toArray(),
            'receipt' => [
                'preview_url' => null,
                'print_job_id' => null,
                'payload' => $this->receipt,
            ],
            'change_due' => $this->changeDue->toArray(),
            'idempotent' => $this->idempotent,
        ];
    }
}
