<?php
declare(strict_types=1);

namespace Rateb\App\Payment;

use Rateb\App\Payment\DTOs\WebhookEvent;

final class PaymentWebhookService
{
    private const REPLAY_WINDOW_SECONDS = 300;

    private readonly PaymentGatewayRegistry $registry;

    public function __construct(
        private readonly PaymentService $payments = new PaymentService(),
        private readonly PaymentConfigService $config = new PaymentConfigService(),
        ?PaymentGatewayRegistry $registry = null,
        private readonly PaymentTransactionRepository $transactions = new PaymentTransactionRepository(),
        private readonly PaymentAuditService $audit = new PaymentAuditService(),
    ) {
        $this->registry = $registry ?? new PaymentGatewayRegistry($this->config);
    }

    /**
     * @param array<string, string> $headers
     * @return array{http: int, ok: bool, duplicate?: bool, error?: string}
     */
    public function handleMoyasar(string $rawBody, array $headers, ?string $clientIp = null): array
    {
        $gatewaySlug = 'moyasar';
        $payloadHash = hash('sha256', $rawBody);

        if ($this->config->mode($gatewaySlug) === 'production' && $this->config->webhookSecret($gatewaySlug) === '') {
            $this->audit->log('payment_webhook_rejected_missing_secret', null, ['hash' => $payloadHash, 'ip' => $clientIp]);

            return ['http' => 401, 'ok' => false, 'error' => 'missing_webhook_secret'];
        }

        $driver = $this->registry->driver($gatewaySlug);
        $event = $driver->verifyWebhook($rawBody, $headers);
        $signatureValid = $event !== null;

        if (!$signatureValid) {
            $this->audit->log('webhook_invalid_signature', null, ['hash' => $payloadHash, 'ip' => $clientIp]);

            return ['http' => 401, 'ok' => false, 'error' => 'invalid_signature'];
        }

        assert($event instanceof WebhookEvent);

        if ($this->isReplay($event)) {
            $this->audit->log('webhook_replay_rejected', null, ['event_id' => $event->eventId]);

            return ['http' => 401, 'ok' => false, 'error' => 'replay_detected'];
        }

        $existing = $this->transactions->findWebhookByEventId($gatewaySlug, $event->eventId);
        if ($existing !== null && ($existing['status'] ?? '') === 'processed') {
            return ['http' => 200, 'ok' => true, 'duplicate' => true];
        }

        $tx = $this->transactions->findByExternalId($gatewaySlug, $event->externalId);
        $webhookId = $this->transactions->createWebhook([
            'gateway_slug' => $gatewaySlug,
            'event_id' => $event->eventId,
            'transaction_id' => $tx !== null ? (int) $tx['id'] : null,
            'signature_valid' => true,
            'payload_hash' => $payloadHash,
            'status' => 'received',
            'payload_json' => $rawBody,
            'client_ip' => $clientIp,
        ]);

        if ($existing !== null) {
            $this->transactions->markWebhookProcessed($webhookId, 'ignored');

            return ['http' => 200, 'ok' => true, 'duplicate' => true];
        }

        $result = $this->payments->confirmPayment($gatewaySlug, $event->externalId, 'webhook');
        $status = ($result['ok'] ?? false) ? 'processed' : 'failed';
        $this->transactions->markWebhookProcessed($webhookId, $status);
        $this->audit->log('webhook_processed', $tx !== null ? (int) $tx['id'] : null, [
            'event_id' => $event->eventId,
            'external_id' => $event->externalId,
            'result' => $result,
        ]);

        return ['http' => 200, 'ok' => (bool) ($result['ok'] ?? false)];
    }

    private function isReplay(WebhookEvent $event): bool
    {
        $raw = $event->raw;
        $ts = null;
        if (isset($raw['created_at'])) {
            $ts = strtotime((string) $raw['created_at']);
        } elseif (isset($raw['timestamp'])) {
            $ts = is_numeric($raw['timestamp']) ? (int) $raw['timestamp'] : strtotime((string) $raw['timestamp']);
        }
        if ($ts === null || $ts === false) {
            return false;
        }

        return abs(time() - (int) $ts) > self::REPLAY_WINDOW_SECONDS;
    }
}
