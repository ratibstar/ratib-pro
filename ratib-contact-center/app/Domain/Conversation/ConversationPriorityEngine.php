<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\Conversation;

/**
 * Unified priority + SLA scoring for conversations.
 */
final class ConversationPriorityEngine
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
    }

    /**
     * @param array<string, mixed> $erpContext
     * @param array<string, mixed> $routingContext
     * @return array{priority: string, sla_risk: string, score: int}
     */
    public function compute(
        string $channel,
        array $erpContext = [],
        array $routingContext = [],
        string $slaRisk = 'green'
    ): array {
        $urgency = (float) (($this->config['channel_urgency'] ?? [])[$channel] ?? 0.5);
        $score = (int) round($urgency * 40);

        $flags = is_array($erpContext['flags'] ?? null) ? $erpContext['flags'] : [];
        if (($flags['vip_customer'] ?? false) === true) {
            $score += 35;
        }
        if (($flags['open_sla_breach'] ?? false) === true) {
            $score += 25;
        }
        if (($flags['repeat_caller'] ?? false) === true) {
            $score += 10;
        }

        $routingScore = (float) ($routingContext['priority_multiplier'] ?? $routingContext['priority_score'] ?? 1.0);
        $score += (int) round(($routingScore - 1.0) * 50);

        $penalties = $this->config['sla_status_penalty'] ?? [];
        $score += (int) ($penalties[$slaRisk] ?? 0);

        $score = max(0, min(100, $score));
        $thresholds = $this->config['priority_thresholds'] ?? [];

        $priority = 'low';
        if ($score >= (int) ($thresholds['vip'] ?? 85) || ($flags['vip_customer'] ?? false) === true) {
            $priority = 'vip';
        } elseif ($score >= (int) ($thresholds['high'] ?? 70)) {
            $priority = 'high';
        } elseif ($score >= (int) ($thresholds['medium'] ?? 40)) {
            $priority = 'medium';
        }

        return [
            'priority' => $priority,
            'sla_risk' => $slaRisk,
            'score' => $score,
        ];
    }

    /** @return array<string, mixed> */
    private function loadConfig(): array
    {
        $path = dirname(__DIR__, 4) . '/config/conversation.php';
        if (!is_file($path)) {
            return [];
        }
        $loaded = require $path;
        return is_array($loaded) ? $loaded : [];
    }
}
