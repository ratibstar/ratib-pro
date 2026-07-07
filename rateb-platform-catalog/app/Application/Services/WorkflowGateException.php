<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use RuntimeException;

final class WorkflowGateException extends RuntimeException
{
    /**
     * @param list<string> $failedRules
     * @param list<string> $warnings
     */
    public function __construct(
        string $message,
        private readonly array $failedRules = [],
        private readonly array $warnings = [],
        private readonly bool $blocking = true
    ) {
        parent::__construct($message, 422);
    }

    /**
     * @return list<string>
     */
    public function failedRules(): array
    {
        return $this->failedRules;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function isBlocking(): bool
    {
        return $this->blocking;
    }
}
