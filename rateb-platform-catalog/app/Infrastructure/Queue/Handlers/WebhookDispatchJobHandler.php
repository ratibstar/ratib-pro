<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Application\Support\CorrelationIdContext;
use Rateb\PlatformCatalog\Application\Support\SecretCipher;
use Rateb\PlatformCatalog\Application\Support\WebhookHmacSigner;
use Rateb\PlatformCatalog\Infrastructure\Http\HttpClientInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IntegrationOutboxWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WebhookDeliveryWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class WebhookDispatchJobHandler implements JobHandlerInterface
{
    private const MAX_ATTEMPTS = 10;

    public function __construct(
        private readonly WebhookDeliveryWriteRepositoryInterface $deliveryRepository,
        private readonly IntegrationOutboxWriteRepositoryInterface $outboxRepository,
        private readonly HttpClientInterface $httpClient
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'webhook_dispatch';
    }

    public function handle(Job $job): void
    {
        $eventId = (string) ($job->payload['event_id'] ?? '');
        $url = (string) ($job->payload['url'] ?? '');
        $subscriptionId = (int) ($job->payload['subscription_id'] ?? 0);
        $payload = is_array($job->payload['payload'] ?? null) ? $job->payload['payload'] : [];
        $secretEncrypted = (string) ($job->payload['secret_encrypted'] ?? '');

        if ($eventId === '' || $url === '' || $subscriptionId <= 0) {
            throw new \InvalidArgumentException('Invalid webhook dispatch payload');
        }

        $body = json_encode([
            'event_id' => $eventId,
            'event_type' => (string) ($job->payload['event_type'] ?? ''),
            'payload' => $payload,
            'correlation_id' => CorrelationIdContext::get(),
        ], JSON_UNESCAPED_UNICODE) ?: '{}';

        $deliveryUuid = $this->deliveryRepository->create($subscriptionId, $eventId, json_decode($body, true) ?: []);
        $timestamp = time();
        $secret = SecretCipher::decrypt($secretEncrypted);
        $signature = WebhookHmacSigner::sign($secret, $timestamp, $body);

        $response = $this->postWebhook($url, $body, $timestamp, $signature, $eventId);
        $status = (int) ($response['status'] ?? 0);
        $responseBody = isset($response['body']) ? (string) $response['body'] : null;

        if ($status >= 200 && $status < 300) {
            $this->deliveryRepository->markDelivered($deliveryUuid, $status, $responseBody);
            $this->outboxRepository->markDelivered($eventId);

            return;
        }

        $attempts = (int) ($job->payload['attempts'] ?? 0) + 1;
        $this->deliveryRepository->markFailed($deliveryUuid, $status, $responseBody, $attempts);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->outboxRepository->markFailed($eventId, $attempts, new \DateTimeImmutable('+1 hour'));

            throw new \RuntimeException('Webhook delivery failed after max attempts');
        }

        $this->outboxRepository->markFailed(
            $eventId,
            $attempts,
            new \DateTimeImmutable('+' . min(3600, 60 * $attempts) . ' seconds')
        );
    }

    /**
     * @return array{status: int, body: string|null}
     */
    private function postWebhook(string $url, string $body, int $timestamp, string $signature, string $eventId): array
    {
        if (defined('RATEB_CATALOG_TESTING') && RATEB_CATALOG_TESTING) {
            return ['status' => 200, 'body' => 'ok'];
        }

        $headers = [
            'Content-Type: application/json',
            'X-Rateb-Signature: ' . $signature,
            'X-Rateb-Timestamp: ' . (string) $timestamp,
            'X-Correlation-Id: ' . (CorrelationIdContext::get() ?? $eventId),
        ];

        return $this->httpClient->postRaw($url, $body, $headers, 15, 10);
    }
}
