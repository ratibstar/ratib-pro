<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Contracts;

interface TicketGatewayInterface
{
    /** @param array<string, mixed> $context */
    public function createFromIvr(int $tenantId, int $callId, string $subject, string $description, array $context = []): int;
}

interface QueueGatewayInterface
{
    public function enqueueCaller(int $tenantId, int $callId, string $queueCode, string $channelId): void;
}
