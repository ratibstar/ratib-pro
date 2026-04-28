<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/Controllers/WebhookController.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/Controllers/WebhookController.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\Controllers;

use App\Modules\Payment\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {
    }

    public function tap(Request $request): JsonResponse
    {
        if ($request->method() !== 'POST') {
            return response()->json(['error' => 'Method Not Allowed'], 405);
        }

        $payload = $request->all();
        if (empty($payload)) {
            $raw = $request->getContent();
            $payload = json_decode($raw, true) ?? [];
        }

        if (! is_array($payload)) {
            return response()->json(['error' => 'Invalid JSON'], 400);
        }

        $this->paymentService->processWebhook($payload);

        return response()->json(['received' => true]);
    }
}
