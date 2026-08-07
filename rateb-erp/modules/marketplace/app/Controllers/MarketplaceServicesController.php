<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Controllers;

/** Phase 1 — Services admin shell (list placeholder; CRUD later). */
final class MarketplaceServicesController extends MarketplaceBaseController
{
    public function index(): void
    {
        $this->bootstrapMarketplace();
        $this->guardView('marketplace/services');
        $this->marketplaceView('services/index', [
            'title' => __('marketplace_services'),
            'items' => [],
            'phase_note' => __('marketplace_phase1_placeholder'),
        ]);
    }
}
