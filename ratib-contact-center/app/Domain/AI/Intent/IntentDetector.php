<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\AI\Intent;

/**
 * Customer intent detection from conversation text (advisory).
 */
final class IntentDetector
{
    /** @var array<string, mixed> */
    private array $config;

    /** @param array<string, mixed>|null $config */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? $this->loadConfig();
    }

    /**
     * @param list<string> $texts
     * @return array{intent:string,confidence:float,alternatives:list<array{intent:string,score:float}>}
     */
    public function detect(array $texts): array
    {
        $blob = strtolower(implode(' ', array_filter($texts)));
        if ($blob === '') {
            return ['intent' => 'general_inquiry', 'confidence' => 0.40, 'alternatives' => []];
        }

        $patterns = $this->config['intent_patterns'] ?? [];
        $scores = [];
        foreach ($patterns as $intent => $keywords) {
            $hits = 0;
            foreach ($keywords as $kw) {
                if ($kw !== '' && str_contains($blob, strtolower($kw))) {
                    $hits++;
                }
            }
            if ($hits > 0) {
                $scores[$intent] = min(0.98, 0.45 + ($hits * 0.18));
            }
        }

        if ($scores === []) {
            return ['intent' => 'general_inquiry', 'confidence' => 0.42, 'alternatives' => []];
        }

        arsort($scores);
        $top = array_key_first($scores);
        $alternatives = [];
        $i = 0;
        foreach ($scores as $intent => $score) {
            if ($i++ === 0) {
                continue;
            }
            $alternatives[] = ['intent' => $intent, 'score' => round($score, 2)];
            if (count($alternatives) >= 3) {
                break;
            }
        }

        return [
            'intent' => (string) $top,
            'confidence' => round((float) $scores[$top], 2),
            'alternatives' => $alternatives,
        ];
    }

    /** @return array<string, mixed> */
    private function loadConfig(): array
    {
        $path = dirname(__DIR__, 4) . '/config/assistant.php';
        return is_file($path) ? (require $path) : [];
    }
}
