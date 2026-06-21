<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Contracts;

use Ratib\ContactCenter\App\Domain\IVR\IvrSession;
use Ratib\ContactCenter\App\Domain\IVR\Enums\IvrSessionStatus;

interface IvrSessionRepositoryInterface
{
    public function findById(int $sessionId, int $tenantId): ?IvrSession;

    public function findActiveByCallId(int $callId, int $tenantId): ?IvrSession;

    public function findActiveByChannelId(string $channelId, int $tenantId): ?IvrSession;

    /** @param array<string, mixed> $state */
    public function create(
        int $callId,
        ?string $callUuid,
        int $tenantId,
        int $flowId,
        ?int $currentNodeId,
        array $state,
        ?string $channelId,
        string $locale
    ): IvrSession;

    /** @param array<string, mixed> $state */
    public function persist(
        int $sessionId,
        int $tenantId,
        ?int $currentNodeId,
        array $state,
        IvrSessionStatus $status,
        int $retryCount
    ): void;

    public function finalize(int $sessionId, int $tenantId, IvrSessionStatus $status): void;
}
