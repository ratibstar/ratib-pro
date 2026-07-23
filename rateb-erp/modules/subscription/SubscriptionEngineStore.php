<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Read port for rateb_subscription_engine (no writes in Phase 4).
 */
interface SubscriptionEngineStore
{
    /** @return array<string, mixed>|null */
    public function findByCompanyId(int $companyId): ?array;

    /** @return list<array<string, mixed>> */
    public function listEngineRowsAfterId(int $afterId, int $limit): array;
}
