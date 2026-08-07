<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Contracts;

/**
 * Port for posting marketplace financial events (Phase 4+).
 * Implementations must call AccountingService from outside Core edits.
 */
interface AccountingPoster
{
    /**
     * @param array<string, mixed> $payload
     */
    public function postMarketplaceInvoice(array $payload): void;
}
