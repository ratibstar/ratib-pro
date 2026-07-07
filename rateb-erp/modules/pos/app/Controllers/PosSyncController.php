<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Pos\Services\PosOfflineSyncService;

final class PosSyncController extends PosBaseController
{
    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/sync');
        $companyId = $this->companyId();
        $service = new PosOfflineSyncService();
        $this->posView('sync/index', [
            'title' => __('pos_sync'),
            'status' => $service->status($companyId > 0 ? $companyId : null),
            'items' => $service->recentQueue(50, $companyId > 0 ? $companyId : null),
            'csrf' => Csrf::token(),
        ]);
    }
}
