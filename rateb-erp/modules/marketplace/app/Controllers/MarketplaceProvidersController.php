<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Controllers;

/** Phase 1 — Providers admin shell (list placeholder; CRUD later). */
final class MarketplaceProvidersController extends MarketplaceBaseController
{
    public function index(): void
    {
        $this->bootstrapMarketplace();
        $this->guardView('marketplace/providers');
        $this->marketplaceView('providers/index', [
            'title' => __('marketplace_providers'),
            'items' => [],
            'phase_note' => __('marketplace_phase1_placeholder'),
        ]);
    }
}
