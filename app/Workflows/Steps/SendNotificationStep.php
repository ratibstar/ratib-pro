<?php
declare(strict_types=1);

namespace App\Workflows\Steps;

use App\Core\Contracts\WorkflowStepInterface;
use App\Services\NotificationService;

final class SendNotificationStep implements WorkflowStepInterface
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function execute(array $context): array
    {
        $this->notificationService->sendWorkerNotification(
            (int) $context['worker']['id'],
            'Worker onboarding workflow completed.',
            (string) ($context['notify_to'] ?? 'operations@gov.local')
        );
        return $context;
    }
}
