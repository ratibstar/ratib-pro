<?php
/**
 * EN: Handles application behavior in `app/Modules/Subscription/Services/SubscriptionService.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Subscription/Services/SubscriptionService.php`.
 */

declare(strict_types=1);

namespace App\Modules\Subscription\Services;

use App\Modules\Agency\Models\Customer;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionPlan;
use App\Modules\Subscription\Repositories\SubscriptionRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class SubscriptionService
{
    public function __construct(
        private readonly SubscriptionRepository $repository
    ) {
    }

    public function createPlan(array $data): SubscriptionPlan
    {
        $this->validatePlanData($data);

        return $this->repository->createPlan([
            'name' => $data['name'],
            'interval' => $data['interval'],
            'amount' => $data['amount'],
            'currency_code' => $data['currency_code'],
        ]);
    }

    public function subscribeCustomer(Customer $customer, int $planId, ?float $amountOverride = null): Subscription
    {
        $plan = $this->repository->findPlanById($planId);
        if ($plan === null) {
            throw new InvalidArgumentException('Subscription plan not found.');
        }

        $activeSubscription = $this->repository->getActiveSubscriptionForCustomer($customer->id);
        if ($activeSubscription !== null) {
            throw new InvalidArgumentException('Customer already has an active subscription.');
        }

        $amount = $amountOverride ?? $plan->amount;
        $currencyCode = $customer->currency_code ?: $plan->currency_code;

        return $this->repository->createSubscription([
            'customer_id' => $customer->id,
            'subscription_plan_id' => $plan->id,
            'status' => Subscription::STATUS_PENDING,
            'started_at' => null,
            'ended_at' => null,
            'currency_code' => $currencyCode,
            'amount' => $amount,
        ]);
    }

    public function activateSubscription(Subscription $subscription): Subscription
    {
        if (! $subscription->isPending()) {
            throw new InvalidArgumentException('Only pending subscriptions can be activated.');
        }

        return $this->repository->updateSubscription($subscription, [
            'status' => Subscription::STATUS_ACTIVE,
            'started_at' => now(),
        ]);
    }

    public function cancelSubscription(Subscription $subscription): Subscription
    {
        if (! $subscription->isCancellable()) {
            throw new InvalidArgumentException('Subscription cannot be cancelled in its current status.');
        }

        return $this->repository->updateSubscription($subscription, [
            'status' => Subscription::STATUS_CANCELLED,
            'ended_at' => now(),
        ]);
    }

    public function findSubscription(int $id): ?Subscription
    {
        return $this->repository->findSubscriptionById($id);
    }

    public function getSubscriptionsByCustomer(int $customerId, ?string $status = null): Collection
    {
        return $this->repository->getSubscriptionsByCustomer($customerId, $status);
    }

    public function getPlans(?string $currencyCode = null): Collection
    {
        return $this->repository->getPlans($currencyCode);
    }

    public function paginateSubscriptions(int $perPage = 15, ?string $status = null): LengthAwarePaginator
    {
        return $this->repository->paginateSubscriptions($perPage, $status);
    }

    private function validatePlanData(array $data): void
    {
        if (empty($data['name'] ?? null)) {
            throw new InvalidArgumentException('Plan name is required.');
        }

        if (! in_array($data['interval'] ?? '', SubscriptionPlan::intervals(), true)) {
            throw new InvalidArgumentException('Invalid interval. Must be monthly or yearly.');
        }

        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Plan amount must be greater than zero.');
        }

        $currencyCode = $data['currency_code'] ?? '';
        if (strlen($currencyCode) !== 3) {
            throw new InvalidArgumentException('Currency code must be 3 characters (ISO 4217).');
        }

        $supported = array_keys(config('currencies.supported', []));
        if (! empty($supported) && ! in_array($currencyCode, $supported, true)) {
            throw new InvalidArgumentException("Currency '{$currencyCode}' is not supported.");
        }
    }
}
