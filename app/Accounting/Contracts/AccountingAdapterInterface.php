<?php
declare(strict_types=1);

namespace App\Accounting\Contracts;

use App\Accounting\Core\AccountingResult;

interface AccountingAdapterInterface
{
    public function supports(string $sourceSystem): bool;

    /**
     * @param array<string, mixed> $event Normalized accounting event
     */
    public function post(array $event): AccountingResult;
}
