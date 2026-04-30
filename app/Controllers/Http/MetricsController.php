<?php
declare(strict_types=1);

namespace App\Controllers\Http;

use App\Services\MetricsService;

final class MetricsController
{
    public function __construct(private readonly MetricsService $metrics)
    {
    }

    /** @return array<string, mixed> */
    public function systemHealth(): array
    {
        return $this->metrics->getSystemHealth();
    }

    /** @return array<string, mixed> */
    public function workflowStats(): array
    {
        return $this->metrics->getWorkflowStats();
    }

    /** @return array<string, mixed> */
    public function failureRates(): array
    {
        return $this->metrics->getFailureRates();
    }
}
