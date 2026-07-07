<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Contracts;

use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

interface JobHandlerInterface
{
    public function supports(string $jobType): bool;

    public function handle(Job $job): void;
}
