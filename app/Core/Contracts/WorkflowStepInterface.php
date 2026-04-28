<?php
declare(strict_types=1);

namespace App\Core\Contracts;

interface WorkflowStepInterface
{
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function execute(array $context): array;
}
