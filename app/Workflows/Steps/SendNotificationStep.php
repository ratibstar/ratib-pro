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
        $workerId = (int) ($context['worker']['id'] ?? $context['worker_id'] ?? 0);
        if ($workerId > 0) {
            try {
                $this->notificationService->sendWorkerNotification(
                    $workerId,
                    'Worker onboarding workflow completed.',
                    (string) ($context['notify_to'] ?? 'operations@gov.local')
                );
            } catch (\Throwable $e) {
                error_log('SendNotificationStep: ' . $e->getMessage());
            }
        }
        return $context;
    }
}
