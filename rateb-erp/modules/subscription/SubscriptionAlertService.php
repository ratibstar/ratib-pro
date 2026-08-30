<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Builds in-app subscription alerts from notification history, with a
 * context fallback when history is missing but the tenant is inside the
 * expiry / grace / suspension window (so ops date changes show immediately).
 *
 * Must NOT call SubscriptionEngine, NotificationPolicy, or SubscriptionRepository.
 * May read SubscriptionRuntime (already bound in Phase 2) for live days/expiry/status.
 */
final class SubscriptionAlertService
{
    private const DISMISS_SESSION_KEY = 'rateb_subscription_alert_dismissed_id';
    private const PERSISTENT_DAYS_THRESHOLD = 3;
    private const CONTEXT_FALLBACK_HISTORY_ID = 0;

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
        // Platform Super Admin in «المنصة بدون شركة» — open console, no tenant expiry banner.
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()
            && function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host()) {
            $opsId = function_exists('rateb_resolve_ops_company_id') ? (int) rateb_resolve_ops_company_id() : 0;
            if ($opsId < 1) {
                return null;
            }
        }

        $context = SubscriptionRuntime::get();
        if ($context === null || $context->companyId() < 1) {
            return null;
        }

        // Open-ended subscription (no end date) — never show expiry/grace alerts.
        $expiry = $context->expirationDate();
        if ($context->hasRecord() && ($expiry === null || $expiry === '')
            && !$context->isSuspended()) {
            return null;
        }

        // Skip when far from any policy window (uses request context only).
        if ($context->hasRecord()
            && $context->daysRemaining() > 14
            && !$context->isSuspended()
            && !$context->isInGrace()) {
            return null;
        }

        $row = $this->history->findLatestActiveByCompanyId($context->companyId());
        if ($row !== null) {
            $fromHistory = $this->fromHistoryRow($row, $context);
            if ($fromHistory !== null) {
                return $fromHistory;
            }
        }

        // No usable history — derive from live SubscriptionContext (engine dates).
        return $this->fromContextFallback($context);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function fromHistoryRow(array $row, SubscriptionContext $context): ?SubscriptionAlertViewModel
    {
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

    private function fromContextFallback(SubscriptionContext $context): ?SubscriptionAlertViewModel
    {
        if (!$context->hasRecord() && !$context->isSuspended()) {
            return null;
        }

        $type = $this->typeFromContext($context);
        if ($type === null) {
            return null;
        }

        $daysRemaining = $context->daysRemaining();
        [$severity, $css] = $this->severityForType($type);

        // Context fallback stays visible (not session-dismissible) until dates move out of window.
        return new SubscriptionAlertViewModel(
            self::CONTEXT_FALLBACK_HISTORY_ID,
            $type,
            $severity,
            $css,
            $this->buildMessage($type, $daysRemaining, $context->graceDaysRemaining(), $context),
            $daysRemaining,
            $context->expirationDate(),
            $context->status(),
            null,
            false
        );
    }

    private function typeFromContext(SubscriptionContext $context): ?string
    {
        if ($context->isSuspended()) {
            return NotificationType::SUSPENSION;
        }
        $expiry = $context->expirationDate();
        if ($expiry === null || $expiry === '') {
            return null;
        }
        if ($context->isInGrace() || $context->daysRemaining() < 0) {
            return NotificationType::GRACE;
        }
        $days = $context->daysRemaining();
        if ($days > 14) {
            return null;
        }
        if ($days <= 3) {
            return NotificationType::FINAL_WARNING;
        }
        return NotificationType::REMINDER;
    }

    private function isDismissible(string $type, int $daysRemaining): bool
    {
        if ($type === NotificationType::GRACE || $type === NotificationType::SUSPENSION) {
            return false;
        }
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
                'rateb-sub-toast--warn',
            ],
            NotificationType::FINAL_WARNING => [
                SubscriptionAlertViewModel::SEVERITY_HIGH,
                'rateb-sub-toast--high',
            ],
            NotificationType::GRACE => [
                SubscriptionAlertViewModel::SEVERITY_CRITICAL_WARNING,
                'rateb-sub-toast--critical',
            ],
            NotificationType::SUSPENSION => [
                SubscriptionAlertViewModel::SEVERITY_CRITICAL,
                'rateb-sub-toast--critical',
            ],
            default => [
                SubscriptionAlertViewModel::SEVERITY_NORMAL,
                'rateb-sub-toast--warn',
            ],
        };
    }

    private function buildMessage(
        string $type,
        int $daysRemaining,
        int $graceDaysRemaining,
        ?SubscriptionContext $context = null
    ): string {
        $t = static function (string $key, array $replace = [], string $fallback = '') use (&$t): string {
            if (function_exists('__')) {
                $out = (string) __($key, $replace);
                if ($out !== '' && $out !== $key) {
                    return $out;
                }
            }
            $msg = $fallback !== '' ? $fallback : $key;
            foreach ($replace as $k => $v) {
                $msg = str_replace([':' . $k, '{' . $k . '}'], (string) $v, $msg);
            }
            return $msg;
        };

        if ($type === NotificationType::SUSPENSION) {
            return $t('subscription_alert_suspended', [], 'Your subscription is suspended.');
        }

        if ($type === NotificationType::GRACE || ($context !== null && $context->isInGrace())) {
            $remaining = $graceDaysRemaining;
            if ($remaining < 1 && $context !== null) {
                $remaining = $context->graceDaysRemaining();
            }
            $remaining = max(0, $remaining);
            return $t(
                'subscription_alert_grace',
                ['days' => $remaining],
                'Subscription expired. ' . $remaining . ' days remaining in grace period.'
            );
        }

        if ($daysRemaining < 0) {
            $remaining = max(0, $graceDaysRemaining);
            return $t(
                'subscription_alert_grace',
                ['days' => $remaining],
                'Subscription expired. ' . $remaining . ' days remaining in grace period.'
            );
        }

        if ($daysRemaining === 0) {
            return $t('subscription_alert_expires_today', [], 'Your subscription expires today.');
        }

        return $t(
            'subscription_alert_expires_in',
            ['days' => $daysRemaining],
            'Your subscription expires in ' . $daysRemaining . ' days'
        );
    }
}
