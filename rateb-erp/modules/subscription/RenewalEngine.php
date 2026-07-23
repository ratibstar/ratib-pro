<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Manual renewal & reactivation (no payment gateway / no auto-charge).
 *
 * Only authorized billing/admin actors may call renew().
 */
final class RenewalEngine
{
    private SubscriptionEngineStore $subscriptions;
    private RenewalStore $renewals;
    private ?RenewalAuthorizer $authorizer;

    public function __construct(
        ?SubscriptionEngineStore $subscriptions = null,
        ?RenewalStore $renewals = null,
        ?RenewalAuthorizer $authorizer = null
    ) {
        $this->subscriptions = $subscriptions ?? new SubscriptionRepository();
        $this->renewals = $renewals ?? new RenewalRepository();
        $this->authorizer = $authorizer;
    }

    public function validateRenewal(RenewalRequest $request): RenewalResult
    {
        if ($request->companyId() < 1) {
            return RenewalResult::rejected(0, 'invalid_company', 'Invalid company_id');
        }

        if ($request->actorId() < 1) {
            return RenewalResult::rejected($request->companyId(), 'unauthorized', 'actor_id required');
        }

        if (!$this->isActorAuthorized($request->actorId())) {
            return RenewalResult::rejected($request->companyId(), 'unauthorized', 'Actor not authorized to renew');
        }

        $row = $this->subscriptions->findByCompanyId($request->companyId());
        if ($row === null) {
            return RenewalResult::rejected($request->companyId(), 'invalid_company', 'No subscription engine row for company');
        }

        $period = trim($request->renewalPeriod());
        if ($period === '') {
            return RenewalResult::rejected($request->companyId(), 'invalid_period', 'renewal_period required');
        }

        $newExpiry = $request->newExpiryDate();
        if ($newExpiry === '' || !$this->isValidDate($newExpiry)) {
            $calc = $this->calculateNewPeriod(
                (string) ($row['subscription_end'] ?? ''),
                $period,
                gmdate('Y-m-d')
            );
            if ($calc === null) {
                return RenewalResult::rejected($request->companyId(), 'invalid_expiry', 'Unable to calculate new_expiry_date');
            }
            $newExpiry = $calc;
        }

        if (!$this->isValidDate($newExpiry)) {
            return RenewalResult::rejected($request->companyId(), 'invalid_expiry', 'new_expiry_date must be Y-m-d');
        }

        $today = gmdate('Y-m-d');
        if ($newExpiry < $today) {
            return RenewalResult::rejected($request->companyId(), 'invalid_expiry', 'new_expiry_date must be today or later');
        }

        $prev = substr((string) ($row['subscription_end'] ?? ''), 0, 10);

        return RenewalResult::ok(
            $request->companyId(),
            $prev !== '' ? $prev : $today,
            $newExpiry,
            strtoupper((string) ($row['current_status'] ?? SubscriptionStatus::ACTIVE)),
            SubscriptionStatus::ACTIVE,
            ['validated' => true, 'period' => $period]
        );
    }

    public function renew(RenewalRequest $request): RenewalResult
    {
        $validation = $this->validateRenewal($request);
        if (!$validation->success()) {
            return $validation;
        }

        $row = $this->subscriptions->findByCompanyId($request->companyId());
        if ($row === null) {
            return RenewalResult::rejected($request->companyId(), 'invalid_company', 'No subscription engine row for company');
        }

        $period = trim($request->renewalPeriod());
        $newExpiry = $request->newExpiryDate();
        if ($newExpiry === '' || !$this->isValidDate($newExpiry)) {
            $newExpiry = (string) $validation->newExpiryDate();
        }

        $previous = substr((string) ($row['subscription_end'] ?? ''), 0, 10);
        $oldStatus = strtoupper((string) ($row['current_status'] ?? SubscriptionStatus::ACTIVE));
        $today = gmdate('Y-m-d');

        if (!$this->reactivate($request->companyId(), $newExpiry, $today)) {
            return RenewalResult::rejected($request->companyId(), 'persist_failed', 'Failed to update subscription engine row');
        }

        $historyId = $this->renewals->insertHistory(
            $request->companyId(),
            $previous !== '' ? $previous : null,
            $newExpiry,
            $period,
            $request->actorId(),
            $request->reference()
        );

        $this->renewals->insertLifecycleAudit(
            $request->companyId(),
            'RENEWED',
            $oldStatus,
            SubscriptionStatus::ACTIVE,
            $request->actorId()
        );

        // Refresh request-scoped context so SubscriptionContext returns ACTIVE immediately.
        if (class_exists(SubscriptionBootstrap::class, false)) {
            SubscriptionBootstrap::bindForCompany($request->companyId());
        }
        if (class_exists(SubscriptionAlertRuntime::class, false)) {
            SubscriptionAlertRuntime::reset();
        }

        error_log(sprintf(
            'RATEB subscription renewed: company_id=%d actor_id=%d old_status=%s new_expiry=%s history_id=%d',
            $request->companyId(),
            $request->actorId(),
            $oldStatus,
            $newExpiry,
            $historyId
        ));

        return RenewalResult::ok(
            $request->companyId(),
            $previous !== '' ? $previous : $today,
            $newExpiry,
            $oldStatus,
            SubscriptionStatus::ACTIVE,
            ['history_id' => $historyId, 'period' => $period]
        );
    }

    /**
     * Clear suspension / grace and set ACTIVE with new expiry.
     */
    public function reactivate(int $companyId, string $newExpiryYmd, ?string $todayYmd = null): bool
    {
        $today = $todayYmd ?? gmdate('Y-m-d');
        return $this->renewals->reactivateEngineRow($companyId, $newExpiryYmd, $today);
    }

    /**
     * Compute new expiry from current end (or today) + period token.
     * Period examples: 30d, 90d, 1m, 12m, 1y, or plain integer days.
     */
    public function calculateNewPeriod(
        string $currentExpiryYmd,
        string $renewalPeriod,
        ?string $todayYmd = null
    ): ?string {
        $today = $todayYmd ?? gmdate('Y-m-d');
        $base = $this->isValidDate($currentExpiryYmd) ? $currentExpiryYmd : $today;
        if ($base < $today) {
            $base = $today;
        }

        $period = strtolower(trim($renewalPeriod));
        if ($period === '') {
            return null;
        }

        $days = 0;
        if (preg_match('/^(\d+)\s*d$/', $period, $m) === 1) {
            $days = (int) $m[1];
        } elseif (preg_match('/^(\d+)\s*m$/', $period, $m) === 1) {
            $ts = strtotime($base . ' 00:00:00');
            if ($ts === false) {
                return null;
            }
            return gmdate('Y-m-d', strtotime('+' . (int) $m[1] . ' months', $ts) ?: $ts);
        } elseif (preg_match('/^(\d+)\s*y$/', $period, $m) === 1) {
            $ts = strtotime($base . ' 00:00:00');
            if ($ts === false) {
                return null;
            }
            return gmdate('Y-m-d', strtotime('+' . (int) $m[1] . ' years', $ts) ?: $ts);
        } elseif (ctype_digit($period)) {
            $days = (int) $period;
        } else {
            return null;
        }

        if ($days < 1) {
            return null;
        }
        $ts = strtotime($base . ' 00:00:00');
        if ($ts === false) {
            return null;
        }
        return gmdate('Y-m-d', $ts + ($days * 86400));
    }

    private function isActorAuthorized(int $actorId): bool
    {
        if ($this->authorizer !== null) {
            return $this->authorizer->canRenew($actorId);
        }

        return DefaultRenewalAuthorizer::canRenew($actorId);
    }

    private function isValidDate(string $ymd): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd) === 1;
    }
}
