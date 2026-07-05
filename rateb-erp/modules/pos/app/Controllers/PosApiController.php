<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Pos\Services\PosContextService;
use Rateb\App\Pos\Services\PosHardwareManager;
use Rateb\App\Pos\Services\PosOfflineSyncService;
use Rateb\App\Pos\Services\PosPricingService;

final class PosApiController extends PosBaseController
{
    public function context(): void
    {
        $this->bootstrapPos();
        $this->json([
            'ok' => true,
            'context' => (new PosContextService())->snapshot(),
            'hardware' => (new PosHardwareManager())->deviceStatus(),
        ]);
    }

    public function syncStatus(): void
    {
        $this->bootstrapPos();
        $this->json(['ok' => true, 'status' => (new PosOfflineSyncService())->status()]);
    }

    public function syncPush(): void
    {
        $this->bootstrapPos();
        $this->json(['ok' => true, 'result' => (new PosOfflineSyncService())->pushQueue([])]);
    }

    public function pricingPreview(): void
    {
        $this->bootstrapPos();
        $this->json(['ok' => true, 'totals' => (new PosPricingService())->calculate([])]);
    }
}
