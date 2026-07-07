<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations\MigrationRunner;

/**
 * Backward-compatible facade for migration execution.
 */
final class MigrationService
{
    public function __construct(
        private readonly MigrationRunner $runner = new MigrationRunner()
    ) {
    }

    /** @return list<string> */
    public function runAll(): array
    {
        return $this->runner->runAll();
    }

    /** @return list<string> */
    public function rollbackLast(): array
    {
        return $this->runner->rollbackLast();
    }
}
