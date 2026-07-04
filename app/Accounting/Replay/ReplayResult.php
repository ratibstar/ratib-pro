<?php
declare(strict_types=1);

namespace App\Accounting\Replay;

final class ReplayResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly int $total,
        public readonly int $processed,
        public readonly int $skipped,
        public readonly int $failed,
        public readonly array $errors = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'processed' => $this->processed,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'errors' => $this->errors,
        ];
    }
}
