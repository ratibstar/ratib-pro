<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Builds in-app subscription alerts from notification history only.
 *
 * Must NOT call SubscriptionEngine, NotificationPolicy, or SubscriptionRepository.
 * May read SubscriptionRuntime (already bound in Phase 2) for live days/expiry/status.
 */
final class SubscriptionAlertService
{
    private const DISMISS_SESSION_KEY = 'rateb_subscription_alert_dismissed_id';
    private const PERSISTENT_DAYS_THRESHOLD = 3;

    private NotificationHistoryStore $history;

    public function __construct(?NotificationHistoryStore $history = null)
    {
        $this->history = $history ?? new NotificationHistoryRepository();
    }

    /**
     * Current alert for the active tenant (cached per request).
     */
    public function current(): ?SubscriptionAlertViewModel
    {
        if (SubscriptionAlertRuntime::isResolved()) {
            return SubscriptionAlertRuntime::get();
        }

        $alert = $this->resolve();
        SubscriptionAlertRuntime::set($alert);
        return $alert;
    }

    /**
     * Soft-dismiss for dismissible alerts (session only — not permanent DB dismiss).
     */
    public function dismissInSession(int $historyId): void
    {
        if ($historyId < 1) {
            return;
        }
        if (!class_exists(\Rateb\App\Core\SessionManager::class)) {
            return;
        }
        \Rateb\App\Core\SessionManager::set(self::DISMISS_SESSION_KEY, $historyId);
        SubscriptionAlertRuntime::reset();
    }

    public function handleDismissRequest(): void
    {
        if (!isset($_GET['dismiss_subscription_alert'])) {
            return;
        }
        $id = (int) $_GET['dismiss_subscription_alert'];
        $current = $this->current();
        if ($current === null || !$current->isDismissible() || $current->historyId() !== $id) {
            return;
        }
        $this->dismissInSession($id);
        SubscriptionAlertRuntime::set(null);
    }

    private function resolve(): ?SubscriptionAlertViewModel
    {
        $context = SubscriptionRuntime::get();
        if ($context === null || $context->companyId() < 1) {
            return null;
        }

        // Skip history query when far from any policy window (uses request context only).
        if ($context->hasRecord()
            && $context->daysRemaining() > 14
            && !$context->isSuspended()
            && !$context->isInGrace()) {
            return null;
        }

        $row = $this->history->findLatestActiveByCompanyId($context->companyId());
        if ($row === null) {
            return null;
        }

        $historyId = (int) ($row['id'] ?? 0);
        if ($historyId < 1) {
            return null;
        }

        $type = strtoupper(trim((string) ($row['notification_type'] ?? '')));
        if (!NotificationType::isKnown($type)) {
            return null;
        }

        $daysRemaining = $context->hasRecord()
            ? $context->daysRemaining()
            : (int) ($row['trigger_day'] ?? 0);

        $dismissible = $this->isDismissible($type, $daysRemaining);
        if ($dismissible && $this->isDismissedInSession($historyId)) {
            return null;
        }

        [$severity, $css] = $this->severityForType($type);
        $status = $context->hasRecord() ? $context->status() : $type;
        $expiration = $context->hasRecord() ? $context->expirationDate() : null;
        $graceDays = $context->hasRecord() ? $context->graceDaysRemaining() : 0;
        $createdAt = isset($row['created_at']) ? (string) $row['created_at'] : null;
        if ($createdAt === '') {
            $createdAt = isset($row['generated_at']) ? (string) $row['generated_at'] : null;
        }

        return new SubscriptionAlertViewModel(
            $historyId,
            $type,
            $severity,
            $css,
            $this->buildMessage($type, $daysRemaining, $graceDays, $context),
            $daysRemaining,
            $expiration,
            $status,
            $createdAt,
            $dismissible
        );
    }

    private function isDismissible(string $type, int $daysRemaining): bool
    {
        if ($type === NotificationType::GRACE || $type === NotificationType::SUSPENSION) {
            return false;
        }
        // Before 3 days of expiry → dismissible; at/under 3 days → persistent.
        return $daysRemaining > self::PERSISTENT_DAYS_THRESHOLD;
    }

    private function isDismissedInSession(int $historyId): bool
    {
        if (!class_exists(\Rateb\App\Core\SessionManager::class)) {
            return false;
        }
        return (int) \Rateb\App\Core\SessionManager::get(self::DISMISS_SESSION_KEY, 0) === $historyId;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function severityForType(string $type): array
    {
        return match ($type) {
            NotificationType::REMINDER => [
                SubscriptionAlertViewModel::SEVERITY_NORMAL,
                'alert-warning',
            ],
            NotificationType::FINAL_WARNING => [
                SubscriptionAlertViewModel::SEVERITY_HIGH,
                'alert-warning rateb-sub-alert--high',
            ],
            NotificationType::GRACE => [
                SubscriptionAlertViewModel::SEVERITY_CRITICAL_WARNING,
                'alert-danger',
            ],
            NotificationType::SUSPENSION => [
                SubscriptionAlertViewModel::SEVERITY_CRITICAL,
                'alert-danger rateb-sub-alert--critical',
            ],
            default => [
                SubscriptionAlertViewModel::SEVERITY_NORMAL,
                'alert-warning',
            ],
        };
    }

    private function buildMessage(
        string $type,
        int $daysRemaining,
        int $graceDaysRemaining,
        ?SubscriptionContext $context = null
    ): string {
        if ($type === NotificationType::SUSPENSION) {
            return 'Your subscription is suspended.';
        }

        if ($type === NotificationType::GRACE || ($context !== null && $context->isInGrace())) {
            $remaining = $graceDaysRemaining;
            if ($remaining < 1 && $context !== null) {
                $remaining = $context->graceDaysRemaining();
            }
            return 'Subscription expired. ' . max(0, $remaining) . ' days remaining in grace period.';
        }

        if ($daysRemaining < 0) {
            return 'Subscription expired. ' . max(0, $graceDaysRemaining) . ' days remaining in grace period.';
        }

        if ($daysRemaining === 0) {
            return 'Your subscription expires today.';
        }

        return 'Your subscription expires in ' . $daysRemaining . ' days';
    }
}
