<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Contracts;

/** Port for company notifications (wired in later phases). */
interface CompanyNotifier
{
    public function notifyCompany(int $companyId, string $title, string $body, array $meta = []): void;
}
