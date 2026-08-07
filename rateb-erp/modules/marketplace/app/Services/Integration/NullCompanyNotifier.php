<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Services\Integration;

use Rateb\App\Marketplace\Contracts\CompanyNotifier;

/** Phase 1 — no-op notifier (real NotificationService adapter later). */
final class NullCompanyNotifier implements CompanyNotifier
{
    public function notifyCompany(int $companyId, string $title, string $body, array $meta = []): void
    {
        unset($companyId, $title, $body, $meta);
    }
}
