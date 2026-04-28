<?php
declare(strict_types=1);

namespace App\Services;

use App\Domain\Contracts\NotificationRepositoryInterface;

final class NotificationService
{
    public function __construct(private readonly NotificationRepositoryInterface $notificationRepository)
    {
    }

    public function sendWorkerNotification(int $workerId, string $message, string $recipient): int
    {
        return $this->notificationRepository->create([
            'worker_id' => $workerId,
            'channel' => 'system',
            'recipient' => $recipient,
            'message' => $message,
            'status' => 'sent',
        ]);
    }
}
