<?php
declare(strict_types=1);

namespace Rateb\App\Services\Help;

use Rateb\App\Core\Database;
use PDO;

/**
 * Tenant-aware Help Assistant analytics (asks, opens, unanswered).
 * Never stores secrets — questions are truncated and sanitized.
 */
final class HelpAnalyticsService
{
    public function track(array $event): void
    {
        try {
            $pdo = Database::connection();
            if (!$this->tableExists($pdo, 'rateb_help_chat_events')) {
                return;
            }
            $companyId = $this->companyId();
            $userId = (int) ($_SESSION['rateb_user_id'] ?? 0);
            $type = (string) ($event['event_type'] ?? 'ask');
            $allowed = ['ask', 'open_article', 'quick', 'unanswered', 'lang', 'clear'];
            if (!in_array($type, $allowed, true)) {
                $type = 'ask';
            }
            $query = $this->sanitizeText((string) ($event['query_text'] ?? ''), 500);
            $stmt = $pdo->prepare(
                'INSERT INTO rateb_help_chat_events
                (company_id, user_id, event_type, locale, module_slug, route_hint, query_text, article_slug, has_answer)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $companyId,
                $userId,
                $type,
                substr((string) ($event['locale'] ?? 'ar'), 0, 8),
                $this->nullableSlug((string) ($event['module_slug'] ?? '')),
                $this->sanitizeText((string) ($event['route_hint'] ?? ''), 255) ?: null,
                $query !== '' ? $query : null,
                $this->nullableSlug((string) ($event['article_slug'] ?? '')),
                !empty($event['has_answer']) ? 1 : 0,
            ]);

            if ($type === 'unanswered' || (isset($event['has_answer']) && !(bool) $event['has_answer'] && $query !== '')) {
                $this->upsertUnanswered($pdo, $companyId, $userId, $event, $query);
            }
        } catch (\Throwable $e) {
            // Analytics must never break chat.
        }
    }

    /** @return array{totals:array<string,int>,top_queries:list<array<string,mixed>>,top_articles:list<array<string,mixed>>,unanswered:list<array<string,mixed>>,locales:array<string,int>} */
    public function report(int $limit = 30): array
    {
        $empty = [
            'totals' => ['asks' => 0, 'opens' => 0, 'unanswered' => 0],
            'top_queries' => [],
            'top_articles' => [],
            'unanswered' => [],
            'locales' => ['ar' => 0, 'en' => 0],
        ];
        try {
            $pdo = Database::connection();
            if (!$this->tableExists($pdo, 'rateb_help_chat_events')) {
                return $empty;
            }
            $companyId = $this->companyId();
            $isSa = function_exists('rateb_is_super_admin') && rateb_is_super_admin();
            if ($isSa && $companyId < 1) {
                $scopeSql = '1=1';
                $params = [];
            } else {
                $scopeSql = 'company_id = ?';
                $params = [$companyId];
            }

            $totals = ['asks' => 0, 'opens' => 0, 'unanswered' => 0];
            $sql = "SELECT event_type, COUNT(*) c FROM rateb_help_chat_events WHERE {$scopeSql} GROUP BY event_type";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $t = (string) ($row['event_type'] ?? '');
                $c = (int) ($row['c'] ?? 0);
                if ($t === 'ask' || $t === 'quick') {
                    $totals['asks'] += $c;
                } elseif ($t === 'open_article') {
                    $totals['opens'] += $c;
                } elseif ($t === 'unanswered') {
                    $totals['unanswered'] += $c;
                }
            }

            $locales = ['ar' => 0, 'en' => 0];
            $stmt = $pdo->prepare("SELECT locale, COUNT(*) c FROM rateb_help_chat_events WHERE {$scopeSql} GROUP BY locale");
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $loc = strtolower(substr((string) ($row['locale'] ?? 'ar'), 0, 2));
                if (isset($locales[$loc])) {
                    $locales[$loc] = (int) ($row['c'] ?? 0);
                }
            }

            $topQueries = [];
            $stmt = $pdo->prepare(
                "SELECT query_text, COUNT(*) c FROM rateb_help_chat_events
                 WHERE ({$scopeSql}) AND query_text IS NOT NULL AND query_text <> ''
                 GROUP BY query_text ORDER BY c DESC LIMIT " . (int) $limit
            );
            $stmt->execute($params);
            $topQueries = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $topArticles = [];
            $stmt = $pdo->prepare(
                "SELECT article_slug, COUNT(*) c FROM rateb_help_chat_events
                 WHERE ({$scopeSql}) AND event_type = 'open_article' AND article_slug IS NOT NULL
                 GROUP BY article_slug ORDER BY c DESC LIMIT " . (int) $limit
            );
            $stmt->execute($params);
            $topArticles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $unanswered = [];
            if ($this->tableExists($pdo, 'rateb_help_unanswered')) {
                if ($isSa && $companyId < 1) {
                    $stmt = $pdo->query(
                        'SELECT * FROM rateb_help_unanswered WHERE status = \'open\' ORDER BY hit_count DESC, last_seen_at DESC LIMIT ' . (int) $limit
                    );
                    $unanswered = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
                } else {
                    $stmt = $pdo->prepare(
                        'SELECT * FROM rateb_help_unanswered WHERE company_id = ? AND status = \'open\'
                         ORDER BY hit_count DESC, last_seen_at DESC LIMIT ' . (int) $limit
                    );
                    $stmt->execute([$companyId]);
                    $unanswered = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }
            }

            return [
                'totals' => $totals,
                'top_queries' => $topQueries,
                'top_articles' => $topArticles,
                'unanswered' => $unanswered,
                'locales' => $locales,
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    private function upsertUnanswered(PDO $pdo, int $companyId, int $userId, array $event, string $query): void
    {
        if (!$this->tableExists($pdo, 'rateb_help_unanswered') || $query === '') {
            return;
        }
        $norm = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $query) ?? $query));
        if ($norm === '') {
            return;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO rateb_help_unanswered
            (company_id, user_id, locale, module_slug, route_hint, question, normalized_question, hit_count, last_seen_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE hit_count = hit_count + 1, last_seen_at = NOW(),
              user_id = VALUES(user_id), module_slug = VALUES(module_slug), route_hint = VALUES(route_hint)'
        );
        $stmt->execute([
            $companyId,
            $userId,
            substr((string) ($event['locale'] ?? 'ar'), 0, 8),
            $this->nullableSlug((string) ($event['module_slug'] ?? '')),
            $this->sanitizeText((string) ($event['route_hint'] ?? ''), 255) ?: null,
            $query,
            $norm,
        ]);
    }

    private function companyId(): int
    {
        if (function_exists('rateb_resolve_ops_company_id')) {
            return max(0, (int) rateb_resolve_ops_company_id());
        }

        return max(0, (int) ($_SESSION['rateb_company_id'] ?? 0));
    }

    private function sanitizeText(string $value, int $max): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;
        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max);
        }

        return trim($value);
    }

    private function nullableSlug(string $slug): ?string
    {
        $slug = strtolower(preg_replace('/[^a-z0-9\-_]/', '', $slug) ?? '');

        return $slug !== '' ? substr($slug, 0, 160) : null;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table]);
            $cache[$table] = (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $cache[$table] = false;
        }

        return $cache[$table];
    }
}
