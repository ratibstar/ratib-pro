<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Hosting\DTOs;

final class HostingUsageSnapshot
{
    private string $account;
    private float $bandwidthMb;
    private float $quotaMb;
    private float $diskUsedMb;

    public function __construct(string $account, float $bandwidthMb, float $quotaMb, float $diskUsedMb) {
        $this->account = $account;
        $this->bandwidthMb = $bandwidthMb;
        $this->quotaMb = $quotaMb;
        $this->diskUsedMb = $diskUsedMb;
    }


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

