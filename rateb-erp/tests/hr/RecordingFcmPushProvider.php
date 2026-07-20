<?php
declare(strict_types=1);

use Rateb\App\Services\Push\FcmPushProviderInterface;
use Rateb\App\Services\Push\PushSendResult;

/** Test double FCM provider. */
final class RecordingFcmPushProvider implements FcmPushProviderInterface
{
    /** @var list<array{token:string,payload:array<string,mixed>}> */
    public array $sent = [];
    public bool $failNext = false;
    public bool $invalidNext = false;
    public int $failUntilAttempt = 0;
    private int $calls = 0;

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $deviceToken, array $payload): PushSendResult
    {
        $this->calls++;
        $this->sent[] = ['token' => $deviceToken, 'payload' => $payload];
        if ($this->invalidNext) {
            $this->invalidNext = false;

            return PushSendResult::failure('invalid_token', 'token rejected', true);
        }
        if ($this->failNext || ($this->failUntilAttempt > 0 && $this->calls <= $this->failUntilAttempt)) {
            return PushSendResult::failure('transient', 'temporary failure');
        }

        return PushSendResult::success();
    }
}
