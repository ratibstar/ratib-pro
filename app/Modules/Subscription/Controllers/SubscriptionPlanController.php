<?php
/**
 * EN: Handles application behavior in `app/Modules/Subscription/Controllers/SubscriptionPlanController.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Subscription/Controllers/SubscriptionPlanController.php`.
 */

declare(strict_types=1);

namespace App\Modules\Subscription\Controllers;

use App\Modules\Subscription\Requests\CreatePlanRequest;
use App\Modules\Subscription\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class SubscriptionPlanController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $service
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $plans = $this->service->getPlans($request->query('currency_code'));

        return response()->json(['data' => $plans]);
    }

    public function store(CreatePlanRequest $request): JsonResponse
    {
        $plan = $this->service->createPlan($request->validated());

        return response()->json(['data' => $plan], 201);
    }
}
