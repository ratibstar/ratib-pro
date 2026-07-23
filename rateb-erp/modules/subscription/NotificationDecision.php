<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Immutable eligibility decision — never delivers a notification.
 */
final readonly class NotificationDecision
{
    /**
     * @param list<string> $channels Future channel hints only
     */
    public function __construct(
        private bool $shouldGenerate,
        private int $companyId,
        private ?int $subscriptionId,
        private ?string $notificationType,
        private ?int $triggerDay,
        private ?string $scheduledDate,
        private string $reason,
        private array $channels = [],
    ) {
    }

    public static function decline(
        int $companyId,
        string $reason,
        ?int $subscriptionId = null
    ): self {
        return new self(
            false,
            $companyId,
            $subscriptionId,
            null,
            null,
            null,
            $reason,
            []
        );
    }

    /**
     * @param list<string> $channels
     */
    public static function eligible(
        int $companyId,
        ?int $subscriptionId,
        string $notificationType,
        int $triggerDay,
        string $scheduledDate,
        string $reason,
        array $channels
    ): self {
        return new self(
            true,
            $companyId,
            $subscriptionId,
            $notificationType,
            $triggerDay,
            $scheduledDate,
            $reason,
            $channels
        );
    }

    public function shouldGenerate(): bool
    {
        return $this->shouldGenerate;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function subscriptionId(): ?int
    {
        return $this->subscriptionId;
    }

    public function notificationType(): ?string
    {
        return $this->notificationType;
    }

    public function triggerDay(): ?int
    {
        return $this->triggerDay;
    }

    public function scheduledDate(): ?string
    {
        return $this->scheduledDate;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /** @return list<string> */
    public function channels(): array
    {
        return $this->channels;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'should_generate' => $this->shouldGenerate,
            'company_id' => $this->companyId,
            'subscription_id' => $this->subscriptionId,
            'notification_type' => $this->notificationType,
            'trigger_day' => $this->triggerDay,
            'scheduled_date' => $this->scheduledDate,
            'reason' => $this->reason,
            'channels' => $this->channels,
        ];
    }
}
