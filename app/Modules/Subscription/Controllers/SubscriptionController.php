<?php
/**
 * EN: Handles application behavior in `app/Modules/Subscription/Controllers/SubscriptionController.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Subscription/Controllers/SubscriptionController.php`.
 */

declare(strict_types=1);

namespace App\Modules\Subscription\Controllers;

use App\Modules\Agency\Models\Customer;
use App\Modules\Subscription\Requests\SubscribeRequest;
use App\Modules\Subscription\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $subscriptions = $this->service->paginateSubscriptions(
            (int) $request->query('per_page', 15),
            $request->query('status')
        );

        return response()->json($subscriptions);
    }

    public function show(int $id): JsonResponse
    {
        $subscription = $this->service->findSubscription($id);

        if ($subscription === null) {
            return response()->json(['message' => 'Subscription not found.'], 404);
        }

        return response()->json(['data' => $subscription]);
    }

    public function subscribe(SubscribeRequest $request, Customer $customer): JsonResponse
    {
        $subscription = $this->service->subscribeCustomer(
            $customer,
            $request->validated('plan_id'),
            $request->validated('amount') ? (float) $request->validated('amount') : null
        );

        return response()->json(['data' => $subscription], 201);
    }

    public function activate(int $id): JsonResponse
    {
        $subscription = $this->service->findSubscription($id);

        if ($subscription === null) {
            return response()->json(['message' => 'Subscription not found.'], 404);
        }

        try {
            $subscription = $this->service->activateSubscription($subscription);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $subscription]);
    }

    public function cancel(int $id): JsonResponse
    {
        $subscription = $this->service->findSubscription($id);

        if ($subscription === null) {
            return response()->json(['message' => 'Subscription not found.'], 404);
        }

        try {
            $subscription = $this->service->cancelSubscription($subscription);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $subscription]);
    }

    public function byCustomer(int $customerId, Request $request): JsonResponse
    {
        $subscriptions = $this->service->getSubscriptionsByCustomer(
            $customerId,
            $request->query('status')
        );

        return response()->json(['data' => $subscriptions]);
    }
}
