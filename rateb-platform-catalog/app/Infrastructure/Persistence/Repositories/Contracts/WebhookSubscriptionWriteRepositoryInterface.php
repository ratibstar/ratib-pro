<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface WebhookSubscriptionWriteRepositoryInterface
{
    /**
     * @param list<string> $events
     */
    public function create(?int $erpCompanyId, string $url, string $secretEncrypted, array $events): string;

    /**
     * @param list<string> $events
     */
    public function update(string $uuid, ?int $erpCompanyId, string $url, string $secretEncrypted, array $events, bool $isActive): bool;

    public function delete(string $uuid): bool;
}
