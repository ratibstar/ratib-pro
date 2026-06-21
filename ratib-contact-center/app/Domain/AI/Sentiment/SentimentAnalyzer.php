<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Domain\AI\Sentiment;

/**
 * Real-time sentiment from message text (heuristic; advisory only).
 */
final class SentimentAnalyzer
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
     * @return array{score:float,label:string,confidence:float}
     */
    public function analyze(array $texts): array
    {
        $blob = strtolower(implode(' ', array_filter($texts)));
        if ($blob === '') {
            return ['score' => 0.0, 'label' => 'neutral', 'confidence' => 0.5];
        }

        $lex = $this->config['sentiment'] ?? [];
        $angryHits = $this->countHits($blob, $lex['angry'] ?? []);
        $negHits = $this->countHits($blob, $lex['negative'] ?? []);
        $posHits = $this->countHits($blob, $lex['positive'] ?? []);

        $raw = ($posHits * 0.35) - ($negHits * 0.45) - ($angryHits * 0.75);
        $score = max(-1.0, min(1.0, $raw));

        if ($angryHits > 0 && $score <= -0.35) {
            $label = 'angry';
            $confidence = min(0.98, 0.65 + ($angryHits * 0.1));
        } elseif ($score <= -0.2) {
            $label = 'negative';
            $confidence = min(0.92, 0.55 + ($negHits * 0.08));
        } elseif ($score >= 0.25) {
            $label = 'positive';
            $confidence = min(0.90, 0.55 + ($posHits * 0.08));
        } else {
            $label = 'neutral';
            $confidence = 0.60;
        }

        return [
            'score' => round($score, 2),
            'label' => $label,
            'confidence' => round($confidence, 2),
        ];
    }

    /** @param list<string> $needles */
    private function countHits(string $haystack, array $needles): int
    {
        $count = 0;
        foreach ($needles as $word) {
            if ($word !== '' && str_contains($haystack, strtolower($word))) {
                $count++;
            }
        }
        return $count;
    }

    /** @return array<string, mixed> */
    private function loadConfig(): array
    {
        $path = dirname(__DIR__, 4) . '/config/assistant.php';
        return is_file($path) ? (require $path) : [];
    }
}
