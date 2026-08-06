<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Contracts;

/** Port over StockMovementService::record — no Core edits. */
interface StockMovementRecorder
{
    /** @param array<string, mixed> $data */
    public function record(array $data): int;
}
