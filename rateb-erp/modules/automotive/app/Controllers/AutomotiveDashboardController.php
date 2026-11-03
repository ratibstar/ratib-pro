<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Controllers;

/** Phase 1 — module shell. No catalog or stock behavior. */
final class AutomotiveDashboardController extends AutomotiveBaseController
{
    public function index(): void
    {
        $this->bootstrapAutomotive();
        $this->guardView('automotive');
        $this->automotiveView('dashboard/index', [
            'title' => __('automotive_dashboard'),
            'phase_note' => __('automotive_phase1_placeholder'),
        ]);
    }
}
