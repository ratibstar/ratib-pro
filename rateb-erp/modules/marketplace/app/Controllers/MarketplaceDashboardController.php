<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Controllers;

use Rateb\App\Marketplace\Services\MarketplaceDashboardService;

/** Phase 1 — Admin shell dashboard placeholder (no commerce KPIs yet). */
final class MarketplaceDashboardController extends MarketplaceBaseController
{
    public function index(): void
    {
        $this->bootstrapMarketplace();
        $this->guardView('marketplace');
        $stats = (new MarketplaceDashboardService())->placeholderStats($this->companyId());
        $this->marketplaceView('dashboard/index', [
            'title' => __('marketplace_dashboard'),
            'stats' => $stats,
            'phase_note' => __('marketplace_phase1_placeholder'),
        ]);
    }
}
