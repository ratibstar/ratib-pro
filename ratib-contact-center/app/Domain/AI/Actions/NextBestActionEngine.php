<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\AI\Actions;

/**
 * Advisory next-best-action — never overrides routing.
 */
final class NextBestActionEngine
{
    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed>|null $config */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
    }

    /**
     * @param array<string, mixed> $context sentiment, intent, risk_score, sla_status, erp_flags
     * @return array{action:string,label:string,reason:string,actions:list<array{action:string,label:string}>}
     */
    public function recommend(array $context): array
    {
        $risk = (float) ($context['risk_score'] ?? 0.0);
        $sentiment = (string) ($context['sentiment'] ?? 'neutral');
        $intent = (string) ($context['intent'] ?? 'general_inquiry');
        $sla = (string) ($context['sla_status'] ?? 'green');
        $erpFlags = is_array($context['erp_flags'] ?? null) ? $context['erp_flags'] : [];

        $candidates = [];
        $rules = $this->config['actions'] ?? [];

        foreach ($rules as $action => $rule) {
            if (!$this->matchesRule($rule, $sentiment, $intent, $risk, $sla, $erpFlags)) {
                continue;
            }
            $candidates[] = [
                'action' => $action,
                'label' => $this->labelFor($action),
                'score' => $this->scoreAction($action, $risk, $sla),
            ];
        }

        usort($candidates, static fn ($a, $b) => $b['score'] <=> $a['score']);

        if ($candidates === []) {
            if ($risk >= 0.45) {
                $primary = 'MONITOR_CLOSELY';
            } elseif ($sentiment === 'positive') {
                $primary = 'CLOSE_CONVERSATION';
            } else {
                $primary = 'CONTINUE_ASSISTING';
            }
            return [
                'action' => $primary,
                'label' => $this->labelFor($primary),
                'reason' => 'Default advisory path based on current context.',
                'actions' => [
                    ['action' => $primary, 'label' => $this->labelFor($primary)],
                    ['action' => 'CREATE_TICKET', 'label' => $this->labelFor('CREATE_TICKET')],
                ],
            ];
        }

        $top = $candidates[0];
        $actions = array_map(static fn ($c) => ['action' => $c['action'], 'label' => $c['label']], array_slice($candidates, 0, 4));

        return [
            'action' => $top['action'],
            'label' => $top['label'],
            'reason' => sprintf(
                'Risk %.0f%%, sentiment=%s, intent=%s, SLA=%s',
                $risk * 100,
                $sentiment,
                $intent,
                $sla
            ),
            'actions' => $actions,
        ];
    }

    /** @return array<string, mixed> */
    public function computeRisk(
        string $sentimentLabel,
        float $sentimentScore,
        string $intent,
        string $slaStatus
    ): array {
        $weights = $this->config['risk_weights'] ?? [];
        $risk = 0.10;

        if ($sentimentLabel === 'angry') {
            $risk += (float) ($weights['sentiment_angry'] ?? 0.35);
        } elseif ($sentimentLabel === 'negative') {
            $risk += (float) ($weights['sentiment_negative'] ?? 0.20);
        }

        if ($intent === 'complaint') {
            $risk += (float) ($weights['intent_complaint'] ?? 0.25);
        } elseif ($intent === 'cancellation_risk') {
            $risk += (float) ($weights['intent_cancellation_risk'] ?? 0.30);
        }

        if ($slaStatus === 'yellow') {
            $risk += (float) ($weights['sla_yellow'] ?? 0.15);
        } elseif ($slaStatus === 'red') {
            $risk += (float) ($weights['sla_red'] ?? 0.35);
        }

        if ($sentimentScore < -0.5) {
            $risk += 0.08;
        }

        return ['risk_score' => round(min(1.0, $risk), 2)];
    }

    /** @param array<string, mixed> $rule */
    private function matchesRule(
        array $rule,
        string $sentiment,
        string $intent,
        float $risk,
        string $sla,
        array $erpFlags
    ): bool {
        $minRisk = (float) ($rule['min_risk'] ?? 0.0);
        if ($risk < $minRisk) {
            return false;
        }
        $sentiments = $rule['sentiments'] ?? null;
        if (is_array($sentiments) && !in_array($sentiment, $sentiments, true)) {
            return false;
        }
        $intents = $rule['intents'] ?? null;
        if (is_array($intents) && !in_array($intent, $intents, true)) {
            return false;
        }
        $erpFlag = $rule['erp_flag'] ?? null;
        if (is_string($erpFlag) && empty($erpFlags[$erpFlag])) {
            return false;
        }
        return true;
    }

    private function scoreAction(string $action, float $risk, string $sla): float
    {
        $score = $risk;
        if (in_array($action, ['ESCALATE_TO_SUPERVISOR', 'CREATE_TICKET'], true) && in_array($sla, ['yellow', 'red'], true)) {
            $score += 0.15;
        }
        return $score;
    }

    private function labelFor(string $action): string
    {
        return match ($action) {
            'ESCALATE_TO_SUPERVISOR' => 'Escalate to supervisor',
            'CREATE_TICKET' => 'Create ticket',
            'OFFER_DISCOUNT' => 'Offer retention discount',
            'TRANSFER_SENIOR' => 'Transfer to senior agent',
            'CLOSE_CONVERSATION' => 'Close conversation',
            'MONITOR_CLOSELY' => 'Monitor closely',
            'CONTINUE_ASSISTING' => 'Continue assisting',
            'MARK_VIP' => 'Mark VIP',
            default => str_replace('_', ' ', strtolower($action)),
        };
    }

    /** @return array<string, mixed> */
    private function loadConfig(): array
    {
        $path = dirname(__DIR__, 4) . '/config/assistant.php';
        return is_file($path) ? (require $path) : [];
    }
}
