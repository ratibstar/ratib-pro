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
        $this->posView('sync/index', [
            'title' => __('pos_sync'),
            'status' => (new PosOfflineSyncService())->status(),
            'csrf' => Csrf::token(),
        ]);
    }
}
