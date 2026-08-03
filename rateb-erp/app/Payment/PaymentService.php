<?php
declare(strict_types=1);

namespace Rateb\App\Payment;

use Rateb\App\Core\Database;
use Rateb\App\Models\Invoice;
use Rateb\App\Models\Payment;
use PDO;
use Rateb\App\Payment\DTOs\PaymentRequest;
use Rateb\App\Payment\DTOs\RefundRequest;
use Rateb\App\Payment\DTOs\WebhookEvent;
use Rateb\App\Payment\Exceptions\PaymentException;
use Rateb\App\Services\AccountingService;
use Rateb\App\Services\BillingAutomationService;
use Rateb\App\Services\NotificationService;

/** Single public payment API — business modules must use this class only. */
final class PaymentService
{
    private readonly PaymentGatewayRegistry $registry;

    public function __construct(
        private readonly PaymentConfigService $config = new PaymentConfigService(),
        ?PaymentGatewayRegistry $registry = null,
        private readonly PaymentTransactionRepository $transactions = new PaymentTransactionRepository(),
        private readonly PaymentAuditService $audit = new PaymentAuditService(),
    ) {
        $this->registry = $registry ?? new PaymentGatewayRegistry($this->config);
    }

    /**
     * @param array<string, mixed>|null $portalUser For ownership validation via invoice match
     * @return array{ok: bool, redirect_url?: string, transaction_id?: int, error?: string}
     */
    public function initiate(int $invoiceId, string $gatewaySlug = 'moyasar', ?array $portalUser = null, ?int $companyId = null): array
    {
        $invoice = (new Invoice())->find($invoiceId);
        if (!$invoice) {
            return ['ok' => false, 'error' => 'invoice_not_found'];
        }
        $cid = (int) ($companyId ?? ($invoice['company_id'] ?? 0));
        if ($cid < 1 || (int) ($invoice['company_id'] ?? 0) !== $cid) {
            return ['ok' => false, 'error' => 'tenant_mismatch'];
        }
        if (!$this->config->isEnabled($gatewaySlug, $cid)) {
            return ['ok' => false, 'error' => 'gateway_disabled'];
        }
        if (!$this->isInvoicePayable($invoice)) {
            return ['ok' => false, 'error' => 'invoice_not_payable'];
        }

        $amount = $this->resolvePayableAmount($invoiceId, $invoice);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'invoice_not_payable'];
        }

        $pending = $this->transactions->findPendingForInvoice($invoiceId, $cid);
        if ($pending !== null && !empty($pending['redirect_url']) && !empty($pending['external_id'])) {
            if (abs((float) ($pending['amount'] ?? 0) - $amount) < 0.01) {
                return [
                    'ok' => true,
                    'redirect_url' => (string) $pending['redirect_url'],
                    'transaction_id' => (int) $pending['id'],
                ];
            }
        }

        $currency = (string) ($invoice['currency'] ?? 'SAR');
        $idempotencyKey = bin2hex(random_bytes(16));
        $callbackToken = bin2hex(random_bytes(16));
        $callbackUrl = $this->config->callbackUrl($cid) . '?token=' . urlencode($callbackToken);

        $request = new PaymentRequest(
            invoiceId: $invoiceId,
            companyId: $cid,
            amount: $amount,
            currency: $currency,
            description: 'Invoice ' . (string) ($invoice['invoice_no'] ?? $invoiceId),
            callbackUrl: $callbackUrl,
            idempotencyKey: $idempotencyKey,
        );

        $gateway = $this->registry->driver($gatewaySlug, $cid);
        $response = $gateway->createPayment($request);

        $txId = $this->transactions->create([
            'company_id' => $cid,
            'invoice_id' => $invoiceId,
            'gateway_slug' => $gatewaySlug,
            'external_id' => $response->externalId,
            'idempotency_key' => $idempotencyKey,
            'amount' => $amount,
            'currency' => $currency,
            'status' => $response->ok ? 'pending' : 'failed',
            'redirect_url' => $response->redirectUrl,
            'callback_token' => $callbackToken,
            'raw_request_json' => json_encode(['invoice_id' => $invoiceId, 'amount' => $amount], JSON_UNESCAPED_UNICODE),
            'raw_response_json' => json_encode($response->raw, JSON_UNESCAPED_UNICODE),
        ]);

        $this->audit->log('initiated', $txId, [
            'invoice_id' => $invoiceId,
            'gateway' => $gatewaySlug,
            'external_id' => $response->externalId,
        ]);

        if (!$response->ok || $response->redirectUrl === null) {
            $this->transactions->update($txId, [
                'error_code' => $response->errorCode,
                'error_message' => $response->errorMessage,
            ]);

            return ['ok' => false, 'error' => $response->errorMessage ?? 'gateway_error'];
        }

        return [
            'ok' => true,
            'redirect_url' => $response->redirectUrl,
            'transaction_id' => $txId,
        ];
    }

    /**
     * @return array{ok: bool, already_completed?: bool, error?: string}
     */
    public function confirmPayment(string $gatewaySlug, string $externalId, string $source = 'webhook'): array
    {
        $tx = $this->transactions->findByExternalId($gatewaySlug, $externalId);
        if ($tx === null) {
            return ['ok' => false, 'error' => 'transaction_not_found'];
        }

        $gateway = $this->registry->driver($gatewaySlug, (int) $tx['company_id']);
        $status = $gateway->getPayment($externalId);

        if ($status->paid) {
            return $this->finalizeSuccess((int) $tx['id'], $externalId, $status->amount, $status->currency, $source);
        }
        if ($status->isCancelled()) {
            return $this->finalizeFailure((int) $tx['id'], 'cancelled', $source);
        }
        if ($status->isFailed()) {
            return $this->finalizeFailure((int) $tx['id'], 'failed', $source);
        }

        return ['ok' => true, 'error' => 'payment_pending'];
    }

    /**
     * @return array{ok: bool, already_completed?: bool, invoice_id?: int, error?: string}
     */
    public function confirmByCallbackToken(string $token): array
    {
        $tx = $this->transactions->findByCallbackToken($token);
        if ($tx === null) {
            return ['ok' => false, 'error' => 'invalid_token'];
        }
        $externalId = (string) ($tx['external_id'] ?? '');
        if ($externalId === '') {
            return ['ok' => false, 'error' => 'missing_external_id'];
        }

        return $this->confirmPayment((string) $tx['gateway_slug'], $externalId, 'callback');
    }

    /**
     * @return array{ok: bool, already_completed?: bool, invoice_id?: int}
     */
    public function finalizeSuccess(
        int $transactionId,
        string $externalId,
        ?float $verifiedAmount = null,
        ?string $verifiedCurrency = null,
        string $source = 'system',
    ): array {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $tx = $this->transactions->findByIdForUpdate($transactionId);
            if ($tx === null) {
                $db->rollBack();

                return ['ok' => false, 'error' => 'transaction_not_found'];
            }
            if ((string) ($tx['status'] ?? '') === 'completed') {
                $db->commit();

                return [
                    'ok' => true,
                    'already_completed' => true,
                    'invoice_id' => (int) ($tx['invoice_id'] ?? 0),
                ];
            }

            $invoiceId = (int) ($tx['invoice_id'] ?? 0);
            $companyId = (int) ($tx['company_id'] ?? 0);
            $invoice = (new Invoice())->find($invoiceId);
            if (!$invoice) {
                $db->rollBack();

                return ['ok' => false, 'error' => 'invoice_not_found'];
            }

            $invoiceCurrency = strtoupper(trim((string) ($invoice['currency'] ?? 'SAR')));
            $txCurrency = strtoupper(trim((string) ($verifiedCurrency ?? $tx['currency'] ?? 'SAR')));
            if ($invoiceCurrency !== $txCurrency) {
                $this->transactions->update($transactionId, [
                    'status' => 'failed',
                    'error_code' => 'currency_mismatch',
                    'error_message' => 'Payment currency does not match invoice currency',
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
                $db->commit();
                $this->audit->log('payment_currency_mismatch', $transactionId, [
                    'invoice_currency' => $invoiceCurrency,
                    'transaction_currency' => $txCurrency,
                ]);

                return ['ok' => false, 'error' => 'currency_mismatch'];
            }

            $expectedAmount = (float) ($tx['amount'] ?? 0);
            $amount = $verifiedAmount ?? $expectedAmount;
            if (abs($amount - $expectedAmount) > 0.01) {
                $this->transactions->update($transactionId, [
                    'status' => 'failed',
                    'error_code' => 'amount_mismatch',
                    'error_message' => 'Verified amount does not match invoice',
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
                $db->commit();
                $this->audit->log('amount_mismatch', $transactionId, ['expected' => $expectedAmount, 'verified' => $amount]);

                return ['ok' => false, 'error' => 'amount_mismatch'];
            }

            $currency = $verifiedCurrency ?? (string) ($tx['currency'] ?? 'SAR');
            $paymentId = (new Payment())->create([
                'company_id' => $companyId,
                'subscription_id' => $invoice['subscription_id'] ?? null,
                'invoice_id' => $invoiceId,
                'amount' => $expectedAmount,
                'currency' => $currency,
                'method' => (string) ($tx['gateway_slug'] ?? 'moyasar'),
                'reference_no' => substr($externalId, 0, 120),
                'status' => 'completed',
                'paid_at' => date('Y-m-d H:i:s'),
            ]);

            (new AccountingService())->postPayment((new Payment())->find($paymentId) ?? [
                'id' => $paymentId,
                'company_id' => $companyId,
                'amount' => $expectedAmount,
                'reference_no' => $externalId,
                'paid_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            (new BillingAutomationService())->recalculatePaymentStatus($invoiceId);

            $this->notifyPaymentReceived($companyId, $invoiceId, $invoice, $expectedAmount, $currency);

            $this->transactions->update($transactionId, [
                'status' => 'completed',
                'rateb_payment_id' => $paymentId,
                'external_id' => $externalId,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);

            $db->commit();
            $this->audit->log('finalized_success', $transactionId, [
                'source' => $source,
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
            ]);

            return ['ok' => true, 'invoice_id' => $invoiceId, 'payment_id' => $paymentId];
        } catch (\Throwable $e) {
            $db->rollBack();
            $this->audit->log('finalize_error', $transactionId, ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'finalize_failed'];
        }
    }

    /** @return array{ok: bool} */
    public function finalizeFailure(int $transactionId, string $reason, string $source = 'system'): array
    {
        $status = $reason === 'cancelled' ? 'cancelled' : 'failed';
        $this->transactions->update($transactionId, [
            'status' => $status,
            'error_code' => $reason,
            'error_message' => 'Payment ' . $reason,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
        $this->audit->log('finalized_failure', $transactionId, ['source' => $source, 'reason' => $reason]);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public function refund(int $transactionId, ?float $amount = null): array
    {
        $tx = $this->transactions->findById($transactionId);
        if ($tx === null) {
            return ['ok' => false, 'error' => 'transaction_not_found'];
        }
        $externalId = (string) ($tx['external_id'] ?? '');
        if ($externalId === '') {
            return ['ok' => false, 'error' => 'missing_external_id'];
        }
        $refundAmount = $amount ?? (float) ($tx['amount'] ?? 0);
        $gateway = $this->registry->driver((string) $tx['gateway_slug'], (int) $tx['company_id']);
        $result = $gateway->refundPayment(new RefundRequest(
            $externalId,
            $refundAmount,
            (string) ($tx['currency'] ?? 'SAR'),
        ));
        if (!$result->ok) {
            return ['ok' => false, 'error' => $result->errorMessage ?? 'refund_failed'];
        }
        $newStatus = $refundAmount >= (float) ($tx['amount'] ?? 0) ? 'refunded' : 'partially_refunded';
        $this->transactions->update($transactionId, ['status' => $newStatus]);
        $this->audit->log('refunded', $transactionId, ['amount' => $refundAmount]);

        return ['ok' => true];
    }

    /** @return array{ok: bool, error?: string} */
    public function cancel(int $transactionId): array
    {
        $tx = $this->transactions->findById($transactionId);
        if ($tx === null) {
            return ['ok' => false, 'error' => 'transaction_not_found'];
        }
        $externalId = (string) ($tx['external_id'] ?? '');
        if ($externalId === '') {
            return ['ok' => false, 'error' => 'missing_external_id'];
        }
        $gateway = $this->registry->driver((string) $tx['gateway_slug'], (int) $tx['company_id']);
        $result = $gateway->cancelPayment($externalId);
        if (!$result->ok) {
            return ['ok' => false, 'error' => $result->errorMessage ?? 'cancel_failed'];
        }
        $this->transactions->update($transactionId, ['status' => 'cancelled', 'completed_at' => date('Y-m-d H:i:s')]);
        $this->audit->log('cancelled', $transactionId, []);

        return ['ok' => true];
    }

    /** @return array{ok: bool, healthy?: bool, error?: string} */
    public function healthCheck(string $gatewaySlug = 'moyasar', ?int $companyId = null): array
    {
        if (!$this->config->isEnabled($gatewaySlug, $companyId)) {
            $this->config->updateHealth('failed', $companyId);

            return ['ok' => false, 'healthy' => false, 'error' => 'gateway_disabled'];
        }
        $secret = $this->config->secretKey($gatewaySlug, $companyId);
        if ($secret === '') {
            $this->config->updateHealth('failed', $companyId);

            return ['ok' => false, 'healthy' => false, 'error' => 'not_configured'];
        }
        $this->config->updateHealth('healthy', $companyId);

        return ['ok' => true, 'healthy' => true];
    }

    public function isGatewayEnabled(?int $companyId = null): bool
    {
        return $this->config->isEnabled('moyasar', $companyId);
    }

    /** @param array<string, mixed> $invoice */
    private function isInvoicePayable(array $invoice): bool
    {
        $paymentStatus = (string) ($invoice['payment_status'] ?? '');
        if ($paymentStatus === 'paid') {
            return false;
        }
        $status = (string) ($invoice['status'] ?? '');
        if ($status === 'cancelled') {
            return false;
        }
        if ($status === 'draft') {
            return false;
        }

        return $this->resolvePayableAmount((int) ($invoice['id'] ?? 0), $invoice) > 0;
    }

    /** @param array<string, mixed> $invoice */
    private function resolvePayableAmount(int $invoiceId, array $invoice): float
    {
        $total = (float) ($invoice['total_amount'] ?? 0);
        if ($total <= 0) {
            return 0.0;
        }
        $paymentStatus = (string) ($invoice['payment_status'] ?? '');
        if ($paymentStatus !== 'partial') {
            return round($total, 2);
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS paid FROM rateb_payments
             WHERE status = 'completed' AND invoice_id = :iid"
        );
        $stmt->execute(['iid' => $invoiceId]);
        $paid = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['paid'] ?? 0);

        return max(0.0, round($total - $paid, 2));
    }

    /** @param array<string, mixed> $invoice */
    private function notifyPaymentReceived(int $companyId, int $invoiceId, array $invoice, float $amount, string $currency): void
    {
        $invoiceNo = (string) ($invoice['invoice_no'] ?? (string) $invoiceId);
        (new NotificationService())->notifyCompany(
            $companyId,
            function_exists('__') ? __('payment_received_notification', ['no' => $invoiceNo]) : 'Payment received for ' . $invoiceNo,
            function_exists('__') ? __('payment_received_message', [
                'no' => $invoiceNo,
                'amount' => number_format($amount, 2),
                'currency' => $currency,
            ]) : 'Payment of ' . number_format($amount, 2) . ' ' . $currency . ' received.',
            'success',
            'payment_received',
            'invoice',
            $invoiceId,
        );
    }
}
