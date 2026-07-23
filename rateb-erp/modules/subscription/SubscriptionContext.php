<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Immutable, read-only snapshot of tenant subscription state for one request.
 *
 * Built by SubscriptionEngine from repository data. Never mutates after creation.
 * Does not enforce access, redirect, notify, or change ERP behavior.
 */
final readonly class SubscriptionContext
{
    public function __construct(
        private int $companyId,
        private string $status,
        private int $daysRemaining,
        private bool $expired,
        private bool $inGrace,
        private bool $suspended,
        private bool $canAccessErp,
        private ?string $expirationDate,
        private bool $hasRecord,
        private ?int $recordId = null,
    ) {
    }

    /**
     * Safe absent snapshot when no engine row exists (or table not ready).
     * Preserves current ERP behavior: access is not denied by this module.
     */
    public static function absent(int $companyId): self
    {
        return new self(
            $companyId,
            SubscriptionStatus::ACTIVE,
            0,
            false,
            false,
            false,
            true,
            null,
            false,
            null
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromEngineRow(int $companyId, array $row, string $todayYmd): self
    {
        $status = strtoupper(trim((string) ($row['current_status'] ?? SubscriptionStatus::ACTIVE)));
        if (!SubscriptionStatus::isKnown($status)) {
            $status = SubscriptionStatus::ACTIVE;
        }

        $endRaw = trim((string) ($row['subscription_end'] ?? ''));
        $expirationDate = $endRaw !== '' ? substr($endRaw, 0, 10) : null;

        $daysRemaining = 0;
        $expired = false;
        if ($expirationDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expirationDate) === 1) {
            $endTs = strtotime($expirationDate . ' 00:00:00');
            $todayTs = strtotime($todayYmd . ' 00:00:00');
            if ($endTs !== false && $todayTs !== false) {
                $daysRemaining = (int) floor(($endTs - $todayTs) / 86400);
                $expired = $daysRemaining < 0;
            }
        }

        $suspendedAt = $row['suspended_at'] ?? null;
        $suspended = $status === SubscriptionStatus::SUSPENDED
            || ($suspendedAt !== null && trim((string) $suspendedAt) !== '');

        $inGrace = $status === SubscriptionStatus::GRACE;

        // Advisory only — Phase 2 never enforces. Absent suspension ⇒ accessible.
        $canAccessErp = !$suspended;

        $recordId = isset($row['id']) ? (int) $row['id'] : 0;
        if ($recordId < 1) {
            $recordId = null;
        }

        return new self(
            $companyId,
            $status,
            $daysRemaining,
            $expired,
            $inGrace,
            $suspended,
            $canAccessErp,
            $expirationDate,
            true,
            $recordId
        );
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    /** rateb_subscription_engine.id when present. */
    public function recordId(): ?int
    {
        return $this->recordId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function daysRemaining(): int
    {
        return $this->daysRemaining;
    }

    public function isExpired(): bool
    {
        return $this->expired;
    }

    public function isInGrace(): bool
    {
        return $this->inGrace;
    }

    public function isSuspended(): bool
    {
        return $this->suspended;
    }

    public function canAccessERP(): bool
    {
        return $this->canAccessErp;
    }

    /** Y-m-d subscription_end, or null when absent. */
    public function expirationDate(): ?string
    {
        return $this->expirationDate;
    }

    public function hasRecord(): bool
    {
        return $this->hasRecord;
    }
}
