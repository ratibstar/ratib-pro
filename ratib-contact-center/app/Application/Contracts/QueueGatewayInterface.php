<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Contracts;

interface QueueGatewayInterface
{
    /**
     * Enqueue caller, run AI routing, return decision (null if queue missing).
     *
     * @param array<string, mixed> $context ivr_input, customer_phone, erp_customer_id
     * @return array<string, mixed>|null RoutingDecision::toArray()
     */
    public function enqueueCaller(
        int $tenantId,
        int $callId,
        string $queueCode,
        string $channelId,
        array $context = []
    ): ?array;

    /**
     * Score and assign agent for an already-queued call.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed> RoutingDecision::toArray()
     */
    public function assignCall(
        int $tenantId,
        int $callId,
        int $queueId,
        string $channelId,
        array $context = []
    ): array;
}
