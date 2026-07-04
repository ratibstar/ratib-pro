<?php
declare(strict_types=1);

namespace App\Accounting\Integrity;

/**
 * Advisory verdict for ledger mutation attempts — never throws to caller pipeline.
 */
final class AccountingLockVerdict
{
    public const ALLOWED = 'allowed';
    public const FLAGGED = 'flagged';
    public const SOFT_REJECT = 'soft_reject';
    public const HARD_REJECT = 'hard_reject';

    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $closureStatus = null,
        public readonly ?string $periodFrom = null,
        public readonly ?string $periodTo = null,
    ) {
    }

    public function isAllowed(): bool
    {
        return $this->status === self::ALLOWED || $this->status === self::FLAGGED;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::HARD_REJECT || $this->status === self::SOFT_REJECT;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'closure_status' => $this->closureStatus,
            'period_from' => $this->periodFrom,
            'period_to' => $this->periodTo,
            'allowed' => $this->isAllowed(),
        ];
    }
}
