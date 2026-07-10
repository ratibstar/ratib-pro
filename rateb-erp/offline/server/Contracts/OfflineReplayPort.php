<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Contracts;

interface OfflineReplayPort
{
    /**
     * @param array<string, mixed> $queueRow
     * @return array{status: string, error?: string}
     */
    public function replay(array $queueRow): array;
}
