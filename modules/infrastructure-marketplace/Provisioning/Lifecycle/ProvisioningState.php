<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Provisioning\Lifecycle;

final class ProvisioningState
{
    public const PENDING = 'PENDING';
    public const QUEUED = 'QUEUED';
    public const RUNNING = 'RUNNING';
    public const RETRYING = 'RETRYING';
    public const WAITING_EXTERNAL = 'WAITING_EXTERNAL';
    public const COMPLETED = 'COMPLETED';
    public const FAILED = 'FAILED';
    public const DEAD_LETTER = 'DEAD_LETTER';
    public const RECONCILING = 'RECONCILING';
    public const CANCELLED = 'CANCELLED';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::QUEUED,
            self::RUNNING,
            self::RETRYING,
            self::WAITING_EXTERNAL,
            self::COMPLETED,
            self::FAILED,
            self::DEAD_LETTER,
            self::RECONCILING,
            self::CANCELLED,
        ];
    }
}

