<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Billing;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class PaymentOrchestratorService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways = new PaymentGatewayManager(),
        private readonly InvoiceService $invoices = new InvoiceService(),
        private readonly RccAuditService $audit = new RccAuditService()
    ) {
    }

    /** @param array<string, mixed> $options */
    public function initiatePayment(int $tenantId, int $invoiceId, string $gatewaySlug, array $options, ?int $userId): array
    {
        $invoice = $this->invoices->find($tenantId, $invoiceId);
        if (!$invoice || $invoice['status'] === 'paid') {
            throw new \InvalidArgumentException('Invoice not payable');
        }
        $driver = $this->gateways->driver($gatewaySlug);
        if ($driver === null) {
            throw new \InvalidArgumentException('Unsupported gateway: ' . $gatewaySlug);
        }
        $credentials = $this->resolveCredentials($tenantId, $gatewaySlug);
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO rcc_payments (tenant_id, invoice_id, gateway, amount, currency, status, payer_email)
             VALUES (:tid, :iid, :gw, :amt, :cur, \'pending\', :email)'
        )->execute([
            'tid' => $tenantId,
            'iid' => $invoiceId,
            'gw' => $gatewaySlug,
            'amt' => $invoice['total_amount'],
            'cur' => $invoice['currency'],
            'email' => $options['payer_email'] ?? null,
        ]);
        $paymentId = (int) $pdo->lastInsertId();
        $charge = [
            'invoice_id' => $invoiceId,
            'amount' => (float) $invoice['total_amount'],
            'currency' => $invoice['currency'],
            'description' => 'Invoice ' . $invoice['invoice_no'],
            'payer_email' => $options['payer_email'] ?? null,
            'return_url' => $options['return_url'] ?? '',
            'cancel_url' => $options['cancel_url'] ?? ($options['return_url'] ?? ''),
        ];
        $result = $driver->createCharge($credentials, $charge);
        $pdo->prepare(
            'INSERT INTO rcc_payment_transactions (tenant_id, payment_id, gateway, transaction_type, status, amount, currency, external_ref, raw_response)
             VALUES (:tid, :pid, :gw, \'authorize\', :st, :amt, :cur, :ext, :raw)'
        )->execute([
            'tid' => $tenantId,
            'pid' => $paymentId,
            'gw' => $gatewaySlug,
            'st' => $result['ok'] ? 'pending' : 'failed',
            'amt' => $invoice['total_amount'],
            'cur' => $invoice['currency'],
            'ext' => $result['external_id'] ?? null,
            'raw' => json_encode($result['raw'] ?? $result, JSON_UNESCAPED_UNICODE),
        ]);
        if (!empty($result['external_id'])) {
            $pdo->prepare('UPDATE rcc_payments SET external_id = :ext WHERE id = :id')
                ->execute(['ext' => $result['external_id'], 'id' => $paymentId]);
        }
        if (!$result['ok']) {
            $pdo->prepare("UPDATE rcc_payments SET status = 'failed' WHERE id = :id")->execute(['id' => $paymentId]);
            throw new \RuntimeException($result['error'] ?? 'Payment initiation failed');
        }
        $this->audit->log($tenantId, 'billing.payment.initiated', $userId, 'payment', $paymentId, ['gateway' => $gatewaySlug]);
        EventBus::instance()->emit([
            'type' => EventType::BILLING_PAYMENT_INITIATED,
            'tenant_id' => $tenantId,
            'payload' => ['payment_id' => $paymentId, 'gateway' => $gatewaySlug],
        ]);
        return [
            'payment_id' => $paymentId,
            'redirect_url' => $result['redirect_url'] ?? null,
            'external_id' => $result['external_id'] ?? null,
        ];
    }

    public function confirmPayment(int $tenantId, int $paymentId, ?int $userId): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM rcc_payments WHERE tenant_id = :tid AND id = :id');
        $stmt->execute(['tid' => $tenantId, 'id' => $paymentId]);
        $payment = $stmt->fetch();
        if (!$payment || empty($payment['external_id'])) {
            throw new \InvalidArgumentException('Payment not found');
        }
        $driver = $this->gateways->driver((string) $payment['gateway']);
        if ($driver === null) {
            throw new \RuntimeException('Gateway unavailable');
        }
        $credentials = $this->resolveCredentials($tenantId, (string) $payment['gateway']);
        $verify = $driver->verifyCharge($credentials, (string) $payment['external_id']);
        $pdo->prepare(
            'INSERT INTO rcc_payment_transactions (tenant_id, payment_id, gateway, transaction_type, status, external_ref, raw_response)
             VALUES (:tid, :pid, :gw, \'capture\', :st, :ext, :raw)'
        )->execute([
            'tid' => $tenantId,
            'pid' => $paymentId,
            'gw' => $payment['gateway'],
            'st' => $verify['ok'] ? 'succeeded' : 'failed',
            'ext' => $payment['external_id'],
            'raw' => json_encode($verify['raw'] ?? $verify, JSON_UNESCAPED_UNICODE),
        ]);
        if ($verify['ok']) {
            $pdo->prepare("UPDATE rcc_payments SET status = 'succeeded' WHERE id = :id")->execute(['id' => $paymentId]);
            $this->invoices->markPaid($tenantId, (int) $payment['invoice_id'], (float) $payment['amount'], $userId);
            EventBus::instance()->emit([
                'type' => EventType::BILLING_PAYMENT_SUCCEEDED,
                'tenant_id' => $tenantId,
                'payload' => ['payment_id' => $paymentId],
            ]);
        } else {
            $pdo->prepare("UPDATE rcc_payments SET status = 'failed' WHERE id = :id")->execute(['id' => $paymentId]);
        }
        $this->audit->log($tenantId, 'billing.payment.confirmed', $userId, 'payment', $paymentId);
        return ['ok' => $verify['ok'], 'status' => $verify['status'] ?? ''];
    }

    /** @return list<array<string, mixed>> */
    public function listGateways(int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT gateway, display_name, is_enabled, is_sandbox, sort_order FROM rcc_payment_gateways
             WHERE tenant_id IS NULL OR tenant_id = :tid ORDER BY sort_order'
        );
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, mixed> */
    private function resolveCredentials(int $tenantId, string $gateway): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT is_sandbox, config_json FROM rcc_payment_gateways
             WHERE gateway = :gw AND (tenant_id IS NULL OR tenant_id = :tid) AND is_enabled = 1
             ORDER BY tenant_id DESC LIMIT 1'
        );
        $stmt->execute(['gw' => $gateway, 'tid' => $tenantId]);
        $row = $stmt->fetch();
        $config = [];
        if ($row && !empty($row['config_json'])) {
            $decoded = json_decode((string) $row['config_json'], true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }
        $config['is_sandbox'] = !empty($row['is_sandbox']);
        return $config;
    }
}
