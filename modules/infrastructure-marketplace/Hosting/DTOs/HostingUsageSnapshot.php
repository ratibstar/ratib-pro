<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Hosting\DTOs;

final class HostingUsageSnapshot
{
    public function __construct(
        private readonly string $account,
        private readonly float $bandwidthMb,
        private readonly float $quotaMb,
        private readonly float $diskUsedMb
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'account' => $this->account,
            'bandwidth_mb' => $this->bandwidthMb,
            'quota_mb' => $this->quotaMb,
            'disk_used_mb' => $this->diskUsedMb,
        ];
    }
}

