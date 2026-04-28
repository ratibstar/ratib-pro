<?php
declare(strict_types=1);

namespace App\Events;

final class WorkflowStepLifecycle
{
    /** @param array<string, mixed> $entry */
    public function __construct(
        public readonly string $mode,
        public readonly array $entry
    ) {
    }
}
