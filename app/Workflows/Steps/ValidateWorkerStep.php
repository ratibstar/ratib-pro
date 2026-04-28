<?php
declare(strict_types=1);

namespace App\Workflows\Steps;

use App\Core\Contracts\WorkflowStepInterface;
use InvalidArgumentException;

final class ValidateWorkerStep implements WorkflowStepInterface
{
    public function execute(array $context): array
    {
        if (empty($context['worker']['name']) || empty($context['worker']['passport_number'])) {
            throw new InvalidArgumentException('Invalid onboarding payload.');
        }

        return $context;
    }
}
