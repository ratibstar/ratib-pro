<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Routing\AI;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\AgentSkillRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\RoutingLogRepository;

/**
 * AI Routing Engine — decides optimal agent + queue from skills, ERP, load, SLA risk.
 * All decisions logged and emitted via EventBus (no polling).
 */
final class RoutingEngine
{
    private SkillMatcher $skillMatcher;
    private ErpContextAnalyzer $erpAnalyzer;
    private SlaPredictor $slaPredictor;
    private AgentScoringEngine $agentScoring;
    private QueueScoringEngine $queueScoring;
    private RoutingLogRepository $routingLog;
    private AgentSkillRepository $skillRepo;
    private EventBus $eventBus;

    public function __construct(?EventBus $eventBus = null)
    {
        $this->eventBus = $eventBus ?? EventBus::instance();
        $this->skillMatcher = new SkillMatcher();
        $this->erpAnalyzer = new ErpContextAnalyzer();
        $this->slaPredictor = new SlaPredictor(null, null);
        $this->agentScoring = new AgentScoringEngine($this->skillMatcher);
        $this->queueScoring = new QueueScoringEngine($this->skillMatcher);
        $this->routingLog = new RoutingLogRepository();
        $this->skillRepo = new AgentSkillRepository();
    }

    public function decide(RoutingContext $context): RoutingDecision
    {
        $this->eventBus->emit([
            'type' => EventType::CALL_SCORING_STARTED,
            'tenant_id' => $context->tenantId,
            'call_id' => $context->callId,
            'payload' => $context->toArray(),
        ]);

        $erpContext = $this->erpAnalyzer->analyze(
            $context->tenantId,
            $context->customerPhone,
            $context->erpCustomerId
        );

        $requiredSkill = $this->skillMatcher->resolveRequiredSkill($context->ivrInput, $context->queueCode);
        $queueScores = $this->queueScoring->scoreQueues(
            $context->tenantId,
            $requiredSkill,
            $context->queueCode
        );

        $selectedQueue = $this->resolveQueue($context, $queueScores);
        $queueId = $selectedQueue['queue_id'];
        $queueCode = $selectedQueue['queue_code'];

        $sla = $this->slaPredictor->predict($context->tenantId, $queueId, $erpContext);
        $slaRisk = (string) $sla['level'];

        $agentSkills = $this->skillRepo->listByTenant($context->tenantId);
        $agentScores = $this->agentScoring->scoreAgents(
            $context->tenantId,
            $queueId,
            $requiredSkill,
            $agentSkills,
            $slaRisk,
            $erpContext
        );

        $escalated = false;
        $selectedAgent = $agentScores[0] ?? null;

        if ($slaRisk === 'red') {
            $seniors = $this->skillRepo->seniorAgentsReady($context->tenantId, $queueId);
            if ($seniors !== []) {
                $senior = $seniors[0];
                $selectedAgent = [
                    'agent_id' => (int) $senior['agent_id'],
                    'final_score' => 1.0,
                    'breakdown' => ['escalation' => 1.0],
                    'extension' => $senior['extension'] ?? null,
                ];
                $escalated = true;
            }
        }

        if ($selectedAgent === null) {
            $selectedAgent = $this->fallbackAgent($context->tenantId, $queueId);
        }

        $agentId = (int) ($selectedAgent['agent_id'] ?? 0);
        $alternatives = array_values(array_filter(array_map(
            static fn ($row) => (int) $row['agent_id'],
            array_slice($agentScores, 1, 5)
        )));

        $reasonParts = [];
        if (($selectedAgent['breakdown']['skill_score'] ?? 0) >= 0.8) {
            $reasonParts[] = 'highest_skill_match';
        }
        if (($selectedAgent['breakdown']['current_load'] ?? 1) <= 0.4) {
            $reasonParts[] = 'low_load';
        }
        if (($erpContext['flags']['vip_customer'] ?? false) === true) {
            $reasonParts[] = 'VIP_customer';
        }
        if ($escalated) {
            $reasonParts[] = 'SLA_escalation_senior_agent';
        }
        if ($reasonParts === []) {
            $reasonParts[] = 'best_weighted_score';
        }

        $decision = new RoutingDecision(
            selectedAgentId: $agentId,
            selectedQueueId: $queueId,
            selectedQueueCode: $queueCode,
            reason: implode(' + ', $reasonParts),
            slaRisk: $slaRisk,
            alternatives: $alternatives,
            escalated: $escalated,
            agentExtension: isset($selectedAgent['extension']) ? (string) $selectedAgent['extension'] : null,
            scoreBreakdown: [
                'required_skill' => $requiredSkill,
                'queue_scores' => $queueScores,
                'agent_scores' => $agentScores,
                'sla' => $sla,
            ],
            erpContext: $erpContext,
        );

        $this->routingLog->log(
            $context->tenantId,
            $context->callId,
            $agentId > 0 ? $agentId : null,
            $queueId,
            $slaRisk,
            $decision->toArray(),
            $decision->scoreBreakdown
        );

        $this->persistCallRouting($context, $decision);

        $this->eventBus->emit([
            'type' => EventType::CALL_SCORING_COMPLETED,
            'tenant_id' => $context->tenantId,
            'call_id' => $context->callId,
            'queue_id' => $queueId,
            'agent_id' => $agentId > 0 ? $agentId : null,
            'payload' => $decision->toArray(),
        ]);

        if ($escalated) {
            $this->eventBus->emit([
                'type' => EventType::SLA_ESCALATED_CALL,
                'tenant_id' => $context->tenantId,
                'call_id' => $context->callId,
                'queue_id' => $queueId,
                'agent_id' => $agentId,
                'payload' => [
                    'reason' => $decision->reason,
                    'sla_risk' => $slaRisk,
                    'decision' => $decision->toArray(),
                ],
            ]);
        }

        if ($agentId > 0) {
            $this->eventBus->emit([
                'type' => EventType::CALL_ASSIGNED,
                'tenant_id' => $context->tenantId,
                'call_id' => $context->callId,
                'queue_id' => $queueId,
                'agent_id' => $agentId,
                'payload' => array_merge($decision->toArray(), [
                    'channel_id' => $context->channelId,
                ]),
            ]);
        }

        return $decision;
    }

    /**
     * @param list<array{queue_id: int, queue_code: string, score: float}> $queueScores
     * @return array{queue_id: int, queue_code: string}
     */
    private function resolveQueue(RoutingContext $context, array $queueScores): array
    {
        if ($context->queueId !== null && $context->queueId > 0) {
            $code = $context->queueCode ?? $this->queueCodeById($context->tenantId, $context->queueId);
            return ['queue_id' => $context->queueId, 'queue_code' => $code ?? 'default'];
        }

        if ($queueScores !== []) {
            return [
                'queue_id' => (int) $queueScores[0]['queue_id'],
                'queue_code' => (string) $queueScores[0]['queue_code'],
            ];
        }

        if ($context->queueCode !== null) {
            $resolved = $this->queueIdByCode($context->tenantId, $context->queueCode);
            if ($resolved !== null) {
                return ['queue_id' => $resolved, 'queue_code' => $context->queueCode];
            }
        }

        return ['queue_id' => 0, 'queue_code' => $context->queueCode ?? 'default'];
    }

    /** @return array{agent_id: int, final_score: float, breakdown: array<string, float>, extension: ?string}|null */
    private function fallbackAgent(int $tenantId, int $queueId): ?array
    {
        $members = $this->skillRepo->agentIdsForQueue($tenantId, $queueId);
        if ($members === []) {
            return null;
        }
        return [
            'agent_id' => $members[0],
            'final_score' => 0.1,
            'breakdown' => ['fallback' => 0.1],
            'extension' => null,
        ];
    }

    private function queueIdByCode(int $tenantId, string $code): ?int
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM rcc_queues WHERE tenant_id = :tid AND code = :code LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'code' => $code]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    private function queueCodeById(int $tenantId, int $queueId): ?string
    {
        $stmt = Database::connection()->prepare(
            'SELECT code FROM rcc_queues WHERE tenant_id = :tid AND id = :id LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $queueId]);
        $code = $stmt->fetchColumn();
        return $code !== false ? (string) $code : null;
    }

    private function persistCallRouting(RoutingContext $context, RoutingDecision $decision): void
    {
        try {
            $stmt = Database::connection()->prepare(
                'UPDATE rcc_calls SET
                    queue_id = :qid,
                    agent_id = :aid,
                    priority_score = :priority,
                    routing_reason = :reason,
                    status = \'routing\'
                 WHERE id = :cid AND tenant_id = :tid'
            );
            $stmt->execute([
                'qid' => $decision->selectedQueueId > 0 ? $decision->selectedQueueId : null,
                'aid' => $decision->selectedAgentId > 0 ? $decision->selectedAgentId : null,
                'priority' => (float) ($decision->erpContext['priority_multiplier'] ?? 1.0),
                'reason' => $decision->reason,
                'cid' => $context->callId,
                'tid' => $context->tenantId,
            ]);
        } catch (\Throwable $e) {
            error_log('[RCC Routing] persistCallRouting: ' . $e->getMessage());
        }
    }
}
