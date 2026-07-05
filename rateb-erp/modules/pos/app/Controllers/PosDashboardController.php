<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Pos\Services\PosContextService;
use Rateb\App\Pos\Services\PosHardwareManager;

final class PosDashboardController extends PosBaseController
{
    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos');
        $ctx = new PosContextService();
        $this->posView('dashboard/index', [
            'title' => __('pos_dashboard'),
            'context' => $ctx->snapshot(),
            'hardware' => (new PosHardwareManager())->deviceStatus(),
            'csrf' => Csrf::token(),
        ]);
    }
}
