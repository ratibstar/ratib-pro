<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/Controllers/PaymentController.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/Controllers/PaymentController.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\Controllers;

use App\Modules\Payment\DTOs\ChargeRequest;
use App\Modules\Payment\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {
    }

    public function createCharge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['required', 'string', 'size:3'],
            'customer_email' => ['required', 'email'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:500'],
            'success_redirect_url' => ['required', 'url'],
            'metadata_key' => ['nullable', 'string', 'max:50'],
            'metadata_value' => ['nullable', 'string', 'max:255'],
        ]);

        $chargeRequest = new ChargeRequest(
            amount: (float) $validated['amount'],
            currencyCode: $validated['currency_code'],
            customerEmail: $validated['customer_email'],
            customerName: $validated['customer_name'],
            customerPhone: $validated['customer_phone'] ?? null,
            description: $validated['description'],
            successRedirectUrl: $validated['success_redirect_url'],
            metadataKey: $validated['metadata_key'] ?? null,
            metadataValue: $validated['metadata_value'] ?? null,
        );

        $response = $this->paymentService->createCharge($chargeRequest);

        if (! $response->success) {
            return response()->json(['message' => $response->errorMessage], 422);
        }

        return response()->json([
            'payment_url' => $response->paymentUrl,
            'charge_id' => $response->externalId,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $chargeId = $request->query('tap_id') ?? $request->query('charge_id') ?? '';
        $chargeId = preg_replace('/[^a-zA-Z0-9_]/', '', $chargeId);

        if ($chargeId === '') {
            return response()->json(['message' => 'Charge ID required.'], 400);
        }

        if ($request->has('customer_id') && $request->has('wallet_id') && $request->has('agency_id')) {
            $validated = $request->validate([
                'customer_id' => ['required', 'integer', 'exists:customers,id'],
                'wallet_id' => ['required', 'integer', 'exists:wallets,id'],
                'agency_id' => ['required', 'integer', 'exists:agencies,id'],
            ]);

            try {
                $transaction = $this->paymentService->verifyAndProcess(
                    $chargeId,
                    $validated['customer_id'],
                    $validated['wallet_id'],
                    $validated['agency_id'],
                );
            } catch (\InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        } else {
            $transaction = $this->paymentService->verifyAndProcessFromRedirect($chargeId);
            if ($transaction === null) {
                return response()->json(['message' => 'Verification failed or payment already processed.'], 422);
            }
        }

        return response()->json(['data' => $transaction]);
    }
}
