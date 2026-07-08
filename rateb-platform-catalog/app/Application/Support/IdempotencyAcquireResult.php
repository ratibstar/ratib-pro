<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

final class IdempotencyAcquireResult
{
    public const PROCESS = 'process';
    public const REPLAY = 'replay';
    public const HASH_CONFLICT = 'hash_conflict';
    public const IN_PROGRESS = 'in_progress';

    /**
     * @param array<string, mixed>|null $record
     */
    public function __construct(
        public readonly string $action,
        public readonly ?array $record = null
    ) {
    }
}
