<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Services\Integration;

use Rateb\App\Marketplace\Contracts\AccountingPoster;

/** Phase 1 — no-op accounting adapter (real wiring in Phase 4). */
final class NullAccountingPoster implements AccountingPoster
{
    public function postMarketplaceInvoice(array $payload): void
    {
        unset($payload);
    }
}
