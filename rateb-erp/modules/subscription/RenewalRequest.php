<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Immutable renewal input (server-side only — never trust raw client payloads).
 */
final readonly class RenewalRequest
{
    public function __construct(
        private int $companyId,
        private string $newExpiryDate,
        private string $renewalPeriod,
        private int $actorId,
        private ?string $reference = null,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        $companyId = (int) ($input['company_id'] ?? 0);
        $expiry = substr(trim((string) ($input['new_expiry_date'] ?? '')), 0, 10);
        $period = trim((string) ($input['renewal_period'] ?? ''));
        $actorId = (int) ($input['actor_id'] ?? 0);
        $ref = isset($input['reference']) ? trim((string) $input['reference']) : null;
        if ($ref === '') {
            $ref = null;
        }

        return new self($companyId, $expiry, $period, $actorId, $ref);
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function newExpiryDate(): string
    {
        return $this->newExpiryDate;
    }

    public function renewalPeriod(): string
    {
        return $this->renewalPeriod;
    }

    public function actorId(): int
    {
        return $this->actorId;
    }

    public function reference(): ?string
    {
        return $this->reference;
    }
}
