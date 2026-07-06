<?php

declare(strict_types=1);

namespace Rateb\App\Pos\DTO\V2\Payment;

use Rateb\App\Pos\Domain\V2\Payment\Exceptions\PosV2PaymentValidationException;
use Rateb\App\Pos\Domain\V2\Payment\PosV2PaymentMethod;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;

final readonly class CompleteSaleRequest
{
    /**
     * @param list<array{method: string, amount: PosV2MoneyDto, reference?: string|null}> $payments
     */
    public function __construct(
        public int $sessionId,
        public array $payments,
        public ?array $sendReceipt = null,
        public bool $giftReceipt = false,
        public float $taxRate = 0.15,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $sessionId = isset($payload['session_id']) ? (int) $payload['session_id'] : 0;
        $payments = array_key_exists('payments', $payload)
            ? self::parsePayments($payload['payments'])
            : [];

        $sendReceipt = isset($payload['send_receipt']) && is_array($payload['send_receipt'])
            ? $payload['send_receipt']
            : null;

        return new self(
            sessionId: $sessionId,
            payments: $payments,
            sendReceipt: $sendReceipt,
            giftReceipt: !empty($payload['gift_receipt']),
            taxRate: isset($payload['tax_rate']) ? (float) $payload['tax_rate'] : 0.15,
        );
    }

    /**
     * @param mixed $raw
     * @return list<array{method: string, amount: PosV2MoneyDto, reference?: string|null}>
     */
    private static function parsePayments(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        if ($raw === []) {
            return [];
        }

        $parsed = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $method = PosV2PaymentMethod::fromString((string) ($row['method'] ?? ''));
            if ($method === null) {
                throw new PosV2PaymentValidationException(
                    'PAYMENT_METHOD_INVALID',
                    'Each payment must include a valid method.',
                );
            }
            $amountRaw = $row['amount'] ?? null;
            $amountStr = is_array($amountRaw)
                ? trim((string) ($amountRaw['amount'] ?? ''))
                : trim((string) $amountRaw);
            if ($amountStr === '' || !is_numeric($amountStr) || (float) $amountStr <= 0) {
                throw new PosV2PaymentValidationException(
                    'AMOUNT_INVALID',
                    'Each payment must include a positive amount.',
                );
            }
            $currency = is_array($amountRaw)
                ? strtoupper(trim((string) ($amountRaw['currency'] ?? 'SAR')))
                : 'SAR';
            $reference = isset($row['reference']) ? trim((string) $row['reference']) : null;
            $parsed[] = [
                'method' => $method->value,
                'amount' => new PosV2MoneyDto(number_format((float) $amountStr, 2, '.', ''), $currency),
                'reference' => $reference !== '' ? $reference : null,
            ];
        }

        return $parsed;
    }
}
