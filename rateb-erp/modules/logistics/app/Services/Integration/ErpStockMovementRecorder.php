<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services\Integration;

use Rateb\App\Logistics\Contracts\StockMovementRecorder;
use Rateb\App\Services\StockMovementService;

final class ErpStockMovementRecorder implements StockMovementRecorder
{
    public function __construct(private StockMovementService $stock = new StockMovementService())
    {
    }

    /** @param array<string, mixed> $data */
    public function record(array $data): int
    {
        return $this->stock->record($data);
    }
}
