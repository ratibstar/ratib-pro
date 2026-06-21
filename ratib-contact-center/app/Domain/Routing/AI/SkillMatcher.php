<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Routing\AI;

/**
 * Maps call intent (IVR / queue code) to required agent skills.
 */
final class SkillMatcher
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
    }

    public function resolveRequiredSkill(?string $ivrInput, ?string $queueCode): string
    {
        $ivrMap = $this->config['ivr_skill_map'] ?? [];
        if ($ivrInput !== null && $ivrInput !== '' && isset($ivrMap[$ivrInput])) {
            return (string) $ivrMap[$ivrInput];
        }

        $queueMap = $this->config['queue_skill_map'] ?? [];
        if ($queueCode !== null && isset($queueMap[$queueCode])) {
            return (string) $queueMap[$queueCode];
        }

        return (string) ($queueMap['default'] ?? 'support');
    }

    /**
     * @param list<array{agent_id: int, skill: string, level: int}> $agentSkills
     */
    public function skillScoreForAgent(int $agentId, string $requiredSkill, array $agentSkills): float
    {
        $best = 0.0;
        foreach ($agentSkills as $row) {
            if ((int) $row['agent_id'] !== $agentId) {
                continue;
            }
            $skill = (string) $row['skill'];
            $level = max(1, min(5, (int) $row['level']));
            if ($skill === $requiredSkill) {
                $best = max($best, $level / 5.0);
            } elseif ($this->isRelatedSkill($skill, $requiredSkill)) {
                $best = max($best, ($level / 5.0) * 0.5);
            }
        }
        return round($best, 4);
    }

    /** @param list<array{agent_id: int, skill: string, level: int}> $agentSkills */
    public function agentsMatchingSkill(string $requiredSkill, array $agentSkills): array
    {
        $scores = [];
        foreach ($agentSkills as $row) {
            $agentId = (int) $row['agent_id'];
            $scores[$agentId] = max(
                $scores[$agentId] ?? 0.0,
                $this->skillScoreForAgent($agentId, $requiredSkill, $agentSkills)
            );
        }
        arsort($scores);
        return $scores;
    }

    private function isRelatedSkill(string $skill, string $required): bool
    {
        $groups = [
            'support' => ['billing'],
            'billing' => ['support'],
            'sales' => ['support'],
        ];
        return in_array($skill, $groups[$required] ?? [], true);
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
