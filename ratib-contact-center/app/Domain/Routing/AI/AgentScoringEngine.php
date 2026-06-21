<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Routing\AI;

use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\AgentSkillRepository;
use Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\RoutingLogRepository;

/**
 * Scores candidate agents using weighted factors from config/routing.php.
 */
final class AgentScoringEngine
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private readonly SkillMatcher $skillMatcher,
        private readonly ?AgentSkillRepository $skillRepo = null,
        private readonly ?RoutingLogRepository $routingLog = null,
        ?array $config = null
    ) {
        $this->config = $config ?? $this->loadConfig();
    }

    /**
     * @param array<string, mixed> $erpContext
     * @param list<array{agent_id: int, skill: string, level: int}> $agentSkills
     * @return list<array{agent_id: int, final_score: float, breakdown: array<string, float>}>
     */
    public function scoreAgents(
        int $tenantId,
        int $queueId,
        string $requiredSkill,
        array $agentSkills,
        string $slaRisk,
        array $erpContext = []
    ): array {
        $weights = $this->config['agent_weights'] ?? [];
        $memberIds = ($this->skillRepo ?? new AgentSkillRepository())->agentIdsForQueue($tenantId, $queueId);
        if ($memberIds === []) {
            $memberIds = array_values(array_unique(array_map(
                static fn ($row) => (int) $row['agent_id'],
                $agentSkills
            )));
        }

        $liveStates = $this->loadLiveStates($tenantId, $memberIds);
        $contactId = isset($erpContext['contact_id']) ? (int) $erpContext['contact_id'] : null;
        $results = [];

        foreach ($memberIds as $agentId) {
            $state = $liveStates[$agentId] ?? ['status' => 'offline', 'current_call_id' => null];
            $status = (string) ($state['status'] ?? 'offline');

            $availability = $status === 'ready' ? 1.0 : ($status === 'wrapup' ? 0.3 : 0.0);
            if ($availability <= 0.0) {
                continue;
            }

            $skillScore = $this->skillMatcher->skillScoreForAgent($agentId, $requiredSkill, $agentSkills);
            $loadScore = $this->loadScore($agentId, $liveStates);
            $familiarity = ($this->routingLog ?? new RoutingLogRepository())
                ->agentFamiliarityScore($tenantId, $agentId, $contactId);
            $slaPenalty = $slaRisk === 'red' ? 1.0 : ($slaRisk === 'yellow' ? 0.5 : 0.0);

            $breakdown = [
                'availability_score' => round($availability, 4),
                'skill_score' => round($skillScore, 4),
                'current_load' => round($loadScore, 4),
                'erp_familiarity' => round($familiarity, 4),
                'sla_risk_penalty' => round($slaPenalty, 4),
            ];

            $final = (
                ($breakdown['skill_score'] * (float) ($weights['skill_match'] ?? 0.30))
                + ((1.0 - $breakdown['current_load']) * (float) ($weights['current_load'] ?? 0.25))
                + ($breakdown['availability_score'] * (float) ($weights['availability'] ?? 0.20))
                + ($breakdown['erp_familiarity'] * (float) ($weights['erp_familiarity'] ?? 0.15))
                - ($breakdown['sla_risk_penalty'] * (float) ($weights['sla_risk_penalty'] ?? 0.10))
            );

            $results[] = [
                'agent_id' => $agentId,
                'final_score' => round(max(0.0, min(1.0, $final)), 4),
                'breakdown' => $breakdown,
                'extension' => $this->agentExtension($tenantId, $agentId),
            ];
        }

        usort($results, static fn ($a, $b) => $b['final_score'] <=> $a['final_score']);
        return $results;
    }

    /** @param array<int, array<string, mixed>> $liveStates */
    private function loadScore(int $agentId, array $liveStates): float
    {
        $state = $liveStates[$agentId] ?? null;
        if ($state === null) {
            return 0.5;
        }
        return match ((string) ($state['status'] ?? 'offline')) {
            'busy' => 1.0,
            'wrapup' => 0.4,
            'ready' => 0.0,
            default => 0.8,
        };
    }

    /** @param list<int> $agentIds
     * @return array<int, array<string, mixed>>
     */
    private function loadLiveStates(int $tenantId, array $agentIds): array
    {
        if ($agentIds === []) {
            return [];
        }
        try {
            $placeholders = implode(',', array_fill(0, count($agentIds), '?'));
            $sql = "SELECT agent_id, status, current_call_id FROM rcc_agent_live_state
                    WHERE tenant_id = ? AND agent_id IN ($placeholders)";
            $stmt = Database::connection()->prepare($sql);
            $params = array_merge([$tenantId], $agentIds);
            $stmt->execute($params);
            $map = [];
            foreach ($stmt->fetchAll() as $row) {
                $map[(int) $row['agent_id']] = $row;
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function agentExtension(int $tenantId, int $agentId): ?string
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT extension FROM rcc_agents WHERE tenant_id = :tid AND id = :aid LIMIT 1'
            );
            $stmt->execute(['tid' => $tenantId, 'aid' => $agentId]);
            $ext = $stmt->fetchColumn();
            return $ext !== false && $ext !== null ? (string) $ext : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private function loadConfig(): array
    {
        $path = dirname(__DIR__, 4) . '/config/routing.php';
        if (!is_file($path)) {
            return [];
        }
        $loaded = require $path;
        return is_array($loaded) ? $loaded : [];
    }
}
