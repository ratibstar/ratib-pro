<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Domain\V2\ValueObjects;

use InvalidArgumentException;

/** Decimal money for cart calculations (no float arithmetic). */
final readonly class PosV2Money
{
    public function __construct(
        public string $amount,
        public string $currency,
    ) {
        if ($this->currency === '' || strlen($this->currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }

        $this->assertValidAmount($this->amount);
    }

    public static function zero(string $currency): self
    {
        return new self('0.00', strtoupper($currency));
    }

    public static function fromDecimalString(string $amount, string $currency): self
    {
        return new self(self::normalizeAmount($amount), strtoupper($currency));
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        if (function_exists('bcadd')) {
            return new self(bcadd($this->amount, $other->amount, 2), $this->currency);
        }

        return self::fromDecimalString(
            (string) round((float) $this->amount + (float) $other->amount, 2),
            $this->currency,
        );
    }

    public function multiply(string $quantity): self
    {
        if (function_exists('bcmul')) {
            return new self(bcmul($this->amount, $quantity, 2), $this->currency);
        }

        return self::fromDecimalString(
            (string) round((float) $this->amount * (float) $quantity, 2),
            $this->currency,
        );
    }

    private static function normalizeAmount(string $amount): string
    {
        $trimmed = trim($amount);
        if ($trimmed === '' || !is_numeric($trimmed)) {
            throw new InvalidArgumentException('Money amount must be numeric.');
        }

        if (function_exists('bcadd')) {
            return bcadd($trimmed, '0', 2);
        }

        return number_format((float) $trimmed, 2, '.', '');
    }

    private function assertValidAmount(string $amount): void
    {
        if (!preg_match('/^\d+\.\d{2}$/', $amount)) {
            throw new InvalidArgumentException('Money amount must have scale 2.');
        }
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch in money operation.');
        }
    }
}
