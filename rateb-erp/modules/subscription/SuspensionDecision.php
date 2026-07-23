<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Immutable suspension eligibility decision (shadow mode — never enforces).
 */
final readonly class SuspensionDecision
{
    public function __construct(
        private int $companyId,
        private bool $eligible,
        private string $reason,
        private ?string $effectiveDate,
        private string $currentStatus,
    ) {
    }

    public static function notEligible(
        int $companyId,
        string $reason,
        string $currentStatus,
        ?string $effectiveDate = null
    ): self {
        return new self($companyId, false, $reason, $effectiveDate, $currentStatus);
    }

    public static function makeEligible(
        int $companyId,
        string $reason,
        string $effectiveDate,
        string $currentStatus
    ): self {
        return new self($companyId, true, $reason, $effectiveDate, $currentStatus);
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function isEligible(): bool
    {
        return $this->eligible;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function effectiveDate(): ?string
    {
        return $this->effectiveDate;
    }

    public function currentStatus(): string
    {
        return $this->currentStatus;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'eligible' => $this->eligible,
            'reason' => $this->reason,
            'effective_date' => $this->effectiveDate,
            'current_status' => $this->currentStatus,
        ];
    }
}
