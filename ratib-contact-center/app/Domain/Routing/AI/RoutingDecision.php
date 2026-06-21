<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Routing\AI;

/**
 * Routing decision output — consumed by QueueEngineGateway and EventBus.
 */
final class RoutingDecision
{
    /** @param list<int> $alternatives */
    public function __construct(
        public readonly int $selectedAgentId,
        public readonly int $selectedQueueId,
        public readonly string $selectedQueueCode,
        public readonly string $reason,
        public readonly string $slaRisk,
        public readonly array $alternatives,
        public readonly bool $escalated = false,
        public readonly ?string $agentExtension = null,
        /** @var array<string, mixed> */
        public readonly array $scoreBreakdown = [],
        /** @var array<string, mixed> */
        public readonly array $erpContext = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'selected_agent_id' => $this->selectedAgentId,
            'selected_queue_id' => $this->selectedQueueId,
            'selected_queue_code' => $this->selectedQueueCode,
            'reason' => $this->reason,
            'sla_risk' => $this->slaRisk,
            'alternatives' => $this->alternatives,
            'escalated' => $this->escalated,
            'agent_extension' => $this->agentExtension,
            'score_breakdown' => $this->scoreBreakdown,
            'erp_context' => $this->erpContext,
        ];
    }
}
