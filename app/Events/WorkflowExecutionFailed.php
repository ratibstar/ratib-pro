<?php
declare(strict_types=1);

namespace App\Events;

final class WorkflowExecutionFailed
{
    /** @param array<int, array<string, mixed>> $eventChain */
    public function __construct(
        public readonly int $workflowId,
        public readonly string $workflowName,
        public readonly string $mode,
        public readonly string $actor,
        public readonly int $sequenceNumber,
        public readonly string $error,
        public readonly array $eventChain,
        public readonly string $timestamp
    ) {
    }
}
