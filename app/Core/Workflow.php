<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Contracts\WorkflowStepInterface;

abstract class Workflow
{
    /** @return WorkflowStepInterface[] */
    abstract public function steps(): array;

    public function maxRetries(): int
    {
        return 1;
    }
}
