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
        $companyId = $this->companyId();
        $service = new PosOfflineSyncService();
        $this->json([
            'ok' => true,
            'status' => $service->status($companyId > 0 ? $companyId : null),
        ]);
    }

    public function syncPush(): void
    {
        $this->bootstrapPos();
        $this->requireSessionCsrfOrAbort();
        $body = $this->jsonBody();
        $items = $body['items'] ?? $body;
        if (!is_array($items)) {
            $items = [];
        }
        if ($items !== [] && !array_is_list($items)) {
            $items = [$items];
        }
        $companyId = $this->companyId();
        $service = new PosOfflineSyncService();
        $this->json([
            'ok' => true,
            'result' => $service->pushQueue($items, [
                'company_id' => $companyId,
                'terminal_id' => (int) ($body['terminal_id'] ?? 0),
                'branch_id' => (int) ($body['branch_id'] ?? 0),
            ]),
        ]);
    }

    public function pricingPreview(): void
    {
        $this->bootstrapPos();
        $this->json(['ok' => true, 'totals' => (new PosPricingService())->calculate([])]);
    }
}
