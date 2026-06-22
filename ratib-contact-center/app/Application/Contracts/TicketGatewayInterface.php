<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Contracts;

interface TicketGatewayInterface
{
    /** @param array<string, mixed> $context */
    public function createFromIvr(int $tenantId, int $callId, string $subject, string $description, array $context = []): int;

    /**
     * AI assistant auto/manual ticket from conversation.
     *
     * @param array<string, mixed> $context
     */
    public function createFromAssistant(
        int $tenantId,
        int $conversationId,
        ?int $callId,
        string $subject,
        string $description,
        array $context = [],
        string $priority = 'normal'
    ): int;
}
