<?php
declare(strict_types=1);

namespace App\Workflows\Steps;

use App\Core\Contracts\WorkflowStepInterface;

final class AssignEmployerStep implements WorkflowStepInterface
{
    public function execute(array $context): array
    {
        $context['worker']['employer_id'] = (int) ($context['worker']['employer_id'] ?? 0);
        return $context;
    }
}
