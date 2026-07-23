<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Immutable, read-only snapshot of tenant subscription state for one request.
 *
 * Phase 6: grace window derived via GracePeriodEngine (calculation only).
 * Does not enforce access, redirect, or suspend.
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
        private int $gracePeriodDays = 0,
        private ?string $graceStartedAt = null,
        private ?string $graceEndAt = null,
        private int $graceDaysRemaining = 0,
    ) {
    }

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
            null,
            0,
            null,
            null,
            0
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromEngineRow(int $companyId, array $row, string $todayYmd): self
    {
        $storedStatus = strtoupper(trim((string) ($row['current_status'] ?? SubscriptionStatus::ACTIVE)));
        if (!SubscriptionStatus::isKnown($storedStatus)) {
            $storedStatus = SubscriptionStatus::ACTIVE;
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

        $rowGraceDays = max(0, (int) ($row['grace_period_days'] ?? 0));
        $graceEngine = new GracePeriodEngine();
        $gracePeriodDays = $graceEngine->policy()->resolveGraceDays($rowGraceDays);

        $storedGraceStart = self::nullableDate($row['grace_started_at'] ?? null);
        $storedGraceEnd = self::nullableDate($row['grace_end_at'] ?? null);

        $graceStartedAt = null;
        $graceEndAt = null;
        $graceDaysRemaining = 0;
        $inGrace = false;
        $status = $storedStatus;

        if ($expirationDate !== null) {
            $graceStartedAt = $storedGraceStart
                ?? $graceEngine->calculateGraceStart($expirationDate, $gracePeriodDays);
            $graceEndAt = $storedGraceEnd
                ?? $graceEngine->calculateGraceEnd($expirationDate, $gracePeriodDays);

            if ($storedStatus !== SubscriptionStatus::SUSPENDED) {
                $status = $graceEngine->resolveLifecycleStatus(
                    $expirationDate,
                    $todayYmd,
                    $gracePeriodDays,
                    $storedStatus
                );
            }

            $inGrace = $graceEngine->isInGracePeriod(
                $expirationDate,
                $todayYmd,
                $gracePeriodDays,
                $graceStartedAt,
                $graceEndAt
            );
            $graceDaysRemaining = $graceEngine->daysRemaining(
                $expirationDate,
                $todayYmd,
                $gracePeriodDays,
                $graceEndAt
            );
            if (!$inGrace) {
                $graceDaysRemaining = max(0, $graceDaysRemaining);
                if ($graceEngine->hasGraceExpired(
                    $expirationDate,
                    $todayYmd,
                    $gracePeriodDays,
                    $graceEndAt
                )) {
                    $graceDaysRemaining = 0;
                }
                if ($todayYmd <= $expirationDate) {
                    $graceDaysRemaining = 0;
                }
            } else {
                $graceDaysRemaining = max(0, $graceDaysRemaining);
            }
        }

        $suspendedAt = $row['suspended_at'] ?? null;
        $suspended = $status === SubscriptionStatus::SUSPENDED
            || ($suspendedAt !== null && trim((string) $suspendedAt) !== '');

        // Grace + suspension-pending: still fully accessible (no enforcement).
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
            $recordId,
            $gracePeriodDays,
            $graceStartedAt,
            $graceEndAt,
            $graceDaysRemaining
        );
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

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

    public function graceDaysRemaining(): int
    {
        return $this->graceDaysRemaining;
    }

    public function graceEndDate(): ?string
    {
        return $this->graceEndAt;
    }

    public function graceStartedAt(): ?string
    {
        return $this->graceStartedAt;
    }

    public function isSuspended(): bool
    {
        return $this->suspended;
    }

    public function isSuspensionPending(): bool
    {
        return $this->status === SubscriptionStatus::SUSPENSION_PENDING;
    }

    public function canAccessERP(): bool
    {
        return $this->canAccessErp;
    }

    public function expirationDate(): ?string
    {
        return $this->expirationDate;
    }

    public function hasRecord(): bool
    {
        return $this->hasRecord;
    }

    public function gracePeriodDays(): int
    {
        return $this->gracePeriodDays;
    }

    private static function nullableDate(mixed $raw): ?string
    {
        $v = trim((string) ($raw ?? ''));
        if ($v === '') {
            return null;
        }
        $ymd = substr($v, 0, 10);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) === 1 ? $ymd : null;
    }
}
