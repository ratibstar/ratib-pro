<?php
declare(strict_types=1);

namespace Rateb\App\Services\Help;

/**
 * Fast in-memory Help Center search (titles, keywords, module names).
 */
final class HelpSearchService
{
    private HelpCenterRepository $repo;

    public function __construct(?HelpCenterRepository $repo = null)
    {
        $this->repo = $repo ?? new HelpCenterRepository();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function search(string $query, int $limit = 20): array
    {
        $q = $this->normalize($query);
        if ($q === '') {
            return [];
        }
        $tokens = preg_split('/\s+/u', $q) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $t): bool => $t !== ''));
        if ($tokens === []) {
            return [];
        }

        $scored = [];
        foreach ($this->repo->searchIndex() as $item) {
            $hay = $this->normalize(implode(' ', [
                (string) ($item['title'] ?? ''),
                (string) ($item['summary'] ?? ''),
                (string) ($item['module_title'] ?? ''),
                (string) ($item['module'] ?? ''),
                implode(' ', array_map('strval', $item['keywords'] ?? [])),
            ]));
            $score = 0;
            foreach ($tokens as $token) {
                if ($token === '') {
                    continue;
                }
                if ((string) ($item['title'] ?? '') !== '' && mb_stripos((string) $item['title'], $token) !== false) {
                    $score += 12;
                }
                if (mb_strpos($hay, $token) !== false) {
                    $score += 6;
                }
                foreach ($item['keywords'] ?? [] as $kw) {
                    $kwN = $this->normalize((string) $kw);
                    if ($kwN === $token) {
                        $score += 10;
                    } elseif ($kwN !== '' && mb_strpos($kwN, $token) !== false) {
                        $score += 4;
                    }
                }
            }
            if ($score > 0) {
                // Prefer articles over modules when scores are close.
                if (($item['type'] ?? '') === 'article') {
                    $score += 1;
                }
                $item['_score'] = $score;
                $scored[] = $item;
            }
        }

        usort($scored, static function (array $a, array $b): int {
            return ((int) ($b['_score'] ?? 0)) <=> ((int) ($a['_score'] ?? 0));
        });

        $scored = array_slice($scored, 0, max(1, $limit));
        foreach ($scored as &$row) {
            unset($row['_score']);
        }
        unset($row);

        return $scored;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}\s\-]/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}
