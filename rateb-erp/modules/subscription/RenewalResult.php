<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Immutable result of a renewal / reactivation attempt.
 */
final readonly class RenewalResult
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        private bool $success,
        private string $code,
        private string $message,
        private int $companyId,
        private ?string $previousExpiryDate = null,
        private ?string $newExpiryDate = null,
        private ?string $oldStatus = null,
        private ?string $newStatus = null,
        private array $meta = [],
    ) {
    }

    public static function rejected(int $companyId, string $code, string $message): self
    {
        return new self(false, $code, $message, $companyId);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function ok(
        int $companyId,
        string $previousExpiry,
        string $newExpiry,
        string $oldStatus,
        string $newStatus,
        array $meta = []
    ): self {
        return new self(
            true,
            'renewed',
            'Subscription renewed and reactivated',
            $companyId,
            $previousExpiry,
            $newExpiry,
            $oldStatus,
            $newStatus,
            $meta
        );
    }

    public function success(): bool
    {
        return $this->success;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function previousExpiryDate(): ?string
    {
        return $this->previousExpiryDate;
    }

    public function newExpiryDate(): ?string
    {
        return $this->newExpiryDate;
    }

    public function oldStatus(): ?string
    {
        return $this->oldStatus;
    }

    public function newStatus(): ?string
    {
        return $this->newStatus;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'code' => $this->code,
            'message' => $this->message,
            'company_id' => $this->companyId,
            'previous_expiry_date' => $this->previousExpiryDate,
            'new_expiry_date' => $this->newExpiryDate,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'meta' => $this->meta,
        ];
    }
}
