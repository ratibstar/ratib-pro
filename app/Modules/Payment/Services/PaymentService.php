<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/Services/PaymentService.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/Services/PaymentService.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\Services;

use App\Modules\Ledger\Services\LedgerService;
use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\DTOs\ChargeRequest;
use App\Modules\Payment\DTOs\ChargeResponse;
use App\Modules\Payment\DTOs\VerificationResult;
use App\Modules\Payment\DTOs\WebhookEvent;
use App\Modules\Payment\Events\PaymentVerified;
use App\Modules\Payment\Models\Transaction;
use App\Modules\Payment\Repositories\PaymentRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PaymentService
{
    private const REFERENCE_TYPE_PAYMENT = 'payment';

    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly PaymentRepository $repository,
        private readonly LedgerService $ledgerService
    ) {
    }

    public function createCharge(ChargeRequest $request): ChargeResponse
    {
        return $this->gateway->createCharge($request);
    }

    public function verifyAndProcessFromRedirect(string $externalId): ?Transaction
    {
        $result = $this->gateway->verifyCharge($externalId);
        if (! $result->verified) {
            return null;
        }

        $metadataValue = $result->metadataValue;
        if ($metadataValue === null || $metadataValue === '') {
            return null;
        }

        $context = $this->resolvePaymentContext($metadataValue);
        if ($context === null) {
            return null;
        }

        $existing = $this->repository->findTransactionByExternalReference($externalId);
        if ($existing !== null) {
            return $existing;
        }

        return $this->processVerifiedPayment(
            $externalId,
            (float) $result->amount,
            $result->currencyCode ?? 'USD',
            $context['customer_id'],
            $context['wallet_id'],
            $context['agency_id'],
            $result->description ?? 'Payment',
        );
    }

    public function verifyAndProcess(
        string $externalId,
        int $customerId,
        int $walletId,
        int $agencyId,
    ): Transaction {
        $existing = $this->repository->findTransactionByExternalReference($externalId);
        if ($existing !== null) {
            throw new InvalidArgumentException('Payment already processed for this charge.');
        }

        $result = $this->gateway->verifyCharge($externalId);
        if (! $result->verified) {
            throw new InvalidArgumentException($result->errorMessage ?? 'Payment verification failed.');
        }

        return $this->processVerifiedPayment(
            $externalId,
            (float) $result->amount,
            $result->currencyCode ?? 'USD',
            $customerId,
            $walletId,
            $agencyId,
            $result->description ?? 'Payment',
        );
    }

    public function processWebhook(array $payload): void
    {
        $event = $this->gateway->parseWebhookPayload($payload);
        if ($event === null) {
            return;
        }

        if ($event->eventType === WebhookEvent::TYPE_CHARGE_CAPTURED && $event->status === 'CAPTURED') {
            $this->handleChargeCaptured($event);
        }
    }

    private function handleChargeCaptured(WebhookEvent $event): void
    {
        $existing = $this->repository->findTransactionByExternalReference($event->externalId);
        if ($existing !== null) {
            return;
        }

        $metadataValue = $event->metadataValue;
        if ($metadataValue === null || $metadataValue === '') {
            return;
        }

        $context = $this->resolvePaymentContext($metadataValue);
        if ($context === null) {
            return;
        }

        try {
            $this->processVerifiedPayment(
                $event->externalId,
                (float) $event->amount,
                $event->currencyCode ?? 'USD',
                $context['customer_id'],
                $context['wallet_id'],
                $context['agency_id'],
                $event->description ?? 'Payment',
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function processVerifiedPayment(
        string $externalId,
        float $amount,
        string $currencyCode,
        int $customerId,
        int $walletId,
        int $agencyId,
        string $description,
    ): Transaction {
        return DB::transaction(function () use ($externalId, $amount, $currencyCode, $customerId, $walletId, $agencyId, $description): Transaction {
            $transaction = $this->repository->createTransaction([
                'customer_id' => $customerId,
                'wallet_id' => $walletId,
                'type' => Transaction::TYPE_SUBSCRIPTION_PAYMENT,
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'external_reference' => $externalId,
                'reference' => $description,
                'status' => Transaction::STATUS_VERIFIED,
            ]);

            $this->recordPaymentInLedger($agencyId, $amount, $currencyCode, $transaction->id, $description);

            PaymentVerified::dispatch($transaction);

            return $transaction;
        });
    }

    private function recordPaymentInLedger(
        int $agencyId,
        float $amount,
        string $currencyCode,
        int $transactionId,
        string $description,
    ): void {
        $debitCode = config('ledger.accounts.cash', '1000');
        $creditCode = config('ledger.accounts.subscription_revenue', '4200');

        $debitAccount = $this->ledgerService->findAccountByCode($agencyId, $debitCode);
        $creditAccount = $this->ledgerService->findAccountByCode($agencyId, $creditCode);

        if ($debitAccount !== null && $creditAccount !== null) {
            $this->ledgerService->recordEntryWithReference(
                $agencyId,
                $debitAccount->id,
                $creditAccount->id,
                $amount,
                $currencyCode,
                self::REFERENCE_TYPE_PAYMENT,
                $transactionId,
                $description
            );
        }
    }

    private function resolvePaymentContext(string $metadataValue): ?array
    {
        $subscription = \App\Modules\Subscription\Models\Subscription::with('customer')
            ->find((int) $metadataValue);

        if ($subscription === null) {
            $customer = \App\Modules\Agency\Models\Customer::find((int) $metadataValue);
            if ($customer === null) {
                return null;
            }
            $agencyId = $customer->agency_id;
            $customerId = $customer->id;
        } else {
            $customer = $subscription->customer;
            $agencyId = $customer->agency_id;
            $customerId = $customer->id;
        }

        $walletService = app(\App\Modules\Wallet\Services\WalletService::class);
        $walletModel = $walletService->getAgencyCommissionWallet($agencyId, config('currencies.default', 'SAR'));
        if ($walletModel === null) {
            $walletModel = app(\App\Modules\Wallet\Repositories\WalletRepository::class)
                ->findOrCreateAgencyCommissionWallet($agencyId, config('currencies.default', 'SAR'));
        }

        return [
            'customer_id' => $customerId,
            'wallet_id' => $walletModel->id,
            'agency_id' => $agencyId,
        ];
    }
}
