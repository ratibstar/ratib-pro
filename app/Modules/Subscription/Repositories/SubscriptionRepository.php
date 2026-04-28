<?php
/**
 * EN: Handles application behavior in `app/Modules/Subscription/Repositories/SubscriptionRepository.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Subscription/Repositories/SubscriptionRepository.php`.
 */

declare(strict_types=1);

namespace App\Modules\Subscription\Repositories;

use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class SubscriptionRepository
{
    public function findPlanById(int $id): ?SubscriptionPlan
    {
        return SubscriptionPlan::find($id);
    }

    public function findSubscriptionById(int $id): ?Subscription
    {
        return Subscription::with(['customer', 'plan'])->find($id);
    }

    public function getPlans(?string $currencyCode = null): Collection
    {
        $query = SubscriptionPlan::orderBy('amount');

        if ($currencyCode !== null) {
            $query->where('currency_code', $currencyCode);
        }

        return $query->get();
    }

    public function getSubscriptionsByCustomer(int $customerId, ?string $status = null): Collection
    {
        $query = Subscription::with('plan')
            ->where('customer_id', $customerId)
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function getActiveSubscriptionForCustomer(int $customerId): ?Subscription
    {
        return Subscription::with('plan')
            ->where('customer_id', $customerId)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->first();
    }

    public function createPlan(array $data): SubscriptionPlan
    {
        return SubscriptionPlan::create($data);
    }

    public function createSubscription(array $data): Subscription
    {
        return Subscription::create($data);
    }

    public function updateSubscription(Subscription $subscription, array $data): Subscription
    {
        $subscription->update($data);

        return $subscription->fresh();
    }

    public function paginateSubscriptions(int $perPage = 15, ?string $status = null): LengthAwarePaginator
    {
        $query = Subscription::with(['customer', 'plan'])->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }
}
