<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Policies\WebhookPolicy;
use Rateb\PlatformCatalog\Application\Support\SecretCipher;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WebhookSubscriptionReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WebhookSubscriptionWriteRepositoryInterface;
use Rateb\PlatformCatalog\Support\Uuid;

final class WebhookService
{
    public function __construct(
        private readonly WebhookSubscriptionReadRepositoryInterface $readRepository,
        private readonly WebhookSubscriptionWriteRepositoryInterface $writeRepository,
        private readonly WebhookPolicy $policy
    ) {
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(int $limit = 50, int $offset = 0): array
    {
        $this->policy->view();
        $items = array_map([$this, 'sanitizeSubscription'], $this->readRepository->list($limit, $offset));

        return ['items' => $items, 'meta' => ['count' => count($items), 'limit' => $limit, 'offset' => $offset]];
    }

    /**
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function getByUuid(string $uuid): array
    {
        $this->policy->view();
        $item = $this->readRepository->findByUuid($uuid);

        return [
            'item' => $item !== null ? $this->sanitizeSubscription($item) : null,
            'meta' => [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>, meta: array<string, mixed>}
     */
    public function create(array $payload): array
    {
        $this->policy->manage();
        $events = $this->normalizeEvents($payload['events'] ?? []);
        $secret = (string) ($payload['secret'] ?? Uuid::v4());
        $url = trim((string) ($payload['url'] ?? ''));
        if ($url === '' || !str_starts_with($url, 'https://')) {
            throw new \InvalidArgumentException('HTTPS url is required');
        }

        $uuid = $this->writeRepository->create(
            isset($payload['erp_company_id']) ? (int) $payload['erp_company_id'] : null,
            $url,
            SecretCipher::encrypt($secret),
            $events
        );

        $item = $this->getByUuid($uuid)['item'];
        if ($item === null) {
            throw new \RuntimeException('Webhook subscription not found after create', 500);
        }

        return ['item' => $item, 'meta' => []];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{item: array<string, mixed>|null, meta: array<string, mixed>}
     */
    public function update(string $uuid, array $payload): array
    {
        $this->policy->manage();
        $existing = $this->readRepository->findByUuid($uuid);
        if ($existing === null) {
            throw new \RuntimeException('Webhook subscription not found', 404);
        }

        $events = $this->normalizeEvents($payload['events'] ?? $existing['events'] ?? []);
        $url = trim((string) ($payload['url'] ?? $existing['url'] ?? ''));
        if ($url === '' || !str_starts_with($url, 'https://')) {
            throw new \InvalidArgumentException('HTTPS url is required');
        }

        $secretEncrypted = $existing['secret_encrypted'] ?? '';
        if (isset($payload['secret']) && (string) $payload['secret'] !== '') {
            $secretEncrypted = SecretCipher::encrypt((string) $payload['secret']);
        }

        $this->writeRepository->update(
            $uuid,
            array_key_exists('erp_company_id', $payload) ? (int) $payload['erp_company_id'] : ($existing['erp_company_id'] ?? null),
            $url,
            (string) $secretEncrypted,
            $events,
            (bool) ($payload['is_active'] ?? ($existing['is_active'] ?? true))
        );

        return $this->getByUuid($uuid);
    }

    public function delete(string $uuid): bool
    {
        $this->policy->manage();

        return $this->writeRepository->delete($uuid);
    }

    /**
     * @param mixed $events
     * @return list<string>
     */
    private function normalizeEvents(mixed $events): array
    {
        if (!is_array($events) || $events === []) {
            throw new \InvalidArgumentException('events array is required');
        }

        return array_values(array_filter(array_map(static fn ($e): string => trim((string) $e), $events)));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function sanitizeSubscription(array $row): array
    {
        unset($row['secret_encrypted']);

        return $row;
    }
}
