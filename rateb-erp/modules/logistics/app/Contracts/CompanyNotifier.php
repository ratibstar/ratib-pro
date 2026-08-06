<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Contracts;

interface CompanyNotifier
{
    public function notifyCompany(
        int $companyId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $triggerType = null,
        ?string $entityType = null,
        ?int $entityId = null
    ): int;
}
