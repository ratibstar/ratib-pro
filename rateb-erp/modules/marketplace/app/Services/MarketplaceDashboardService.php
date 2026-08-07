<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Services;

/**
 * Phase 1 — Dashboard placeholder stats (zeroed; no commerce queries yet).
 */
final class MarketplaceDashboardService
{
    /**
     * @return array<string, int|float>
     */
    public function placeholderStats(int $companyId): array
    {
        unset($companyId);

        return [
            'providers' => 0,
            'services' => 0,
            'orders' => 0,
            'reviews' => 0,
            'revenue' => 0.0,
        ];
    }
}
