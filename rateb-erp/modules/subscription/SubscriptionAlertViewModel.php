<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Read-only view model for in-app subscription alert display.
 */
final readonly class SubscriptionAlertViewModel
{
    public const SEVERITY_NORMAL = 'normal';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL_WARNING = 'critical_warning';
    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(
        private int $historyId,
        private string $notificationType,
        private string $severity,
        private string $cssClass,
        private string $message,
        private int $daysRemaining,
        private ?string $expirationDate,
        private string $subscriptionStatus,
        private ?string $createdAt,
        private bool $dismissible,
    ) {
    }

    public function historyId(): int
    {
        return $this->historyId;
    }

    public function notificationType(): string
    {
        return $this->notificationType;
    }

    public function severity(): string
    {
        return $this->severity;
    }

    /** Bootstrap-compatible alert class (e.g. alert-warning). */
    public function cssClass(): string
    {
        return $this->cssClass;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function daysRemaining(): int
    {
        return $this->daysRemaining;
    }

    public function expirationDate(): ?string
    {
        return $this->expirationDate;
    }

    public function subscriptionStatus(): string
    {
        return $this->subscriptionStatus;
    }

    public function createdAt(): ?string
    {
        return $this->createdAt;
    }

    public function isDismissible(): bool
    {
        return $this->dismissible;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'history_id' => $this->historyId,
            'notification_type' => $this->notificationType,
            'severity' => $this->severity,
            'css_class' => $this->cssClass,
            'message' => $this->message,
            'days_remaining' => $this->daysRemaining,
            'expiration_date' => $this->expirationDate,
            'subscription_status' => $this->subscriptionStatus,
            'created_at' => $this->createdAt,
            'dismissible' => $this->dismissible,
        ];
    }
}
