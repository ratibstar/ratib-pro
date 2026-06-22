<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Knowledge;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;

final class KnowledgeBaseService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    /** @return list<array<string, mixed>> */
    public function search(int $tenantId, string $query, int $limit = 20): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT id, slug, title, title_ar, category_id, view_count
             FROM rcc_kb_articles
             WHERE tenant_id = :tid AND status = 'published'
               AND (title LIKE :q OR body LIKE :q OR title_ar LIKE :q OR body_ar LIKE :q)
             ORDER BY view_count DESC LIMIT " . max(1, min(50, $limit))
        );
        $like = '%' . $query . '%';
        $stmt->execute(['tid' => $tenantId, 'q' => $like]);
        return $stmt->fetchAll() ?: [];
    }

    /** @param array<string, mixed> $data */
    public function saveArticle(int $tenantId, array $data, ?int $userId): array
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        if ($id > 0) {
            Database::connection()->prepare(
                'UPDATE rcc_kb_articles SET title=:title, title_ar=:title_ar, body=:body, body_ar=:body_ar, category_id=:cat, visibility=:vis, status=:status
                 WHERE tenant_id=:tid AND id=:id'
            )->execute([
                'tid' => $tenantId, 'id' => $id,
                'title' => (string) ($data['title'] ?? ''),
                'title_ar' => $data['title_ar'] ?? null,
                'body' => (string) ($data['body'] ?? ''),
                'body_ar' => $data['body_ar'] ?? null,
                'cat' => $data['category_id'] ?? null,
                'vis' => (string) ($data['visibility'] ?? 'internal'),
                'status' => (string) ($data['status'] ?? 'draft'),
            ]);
        } else {
            $slug = (string) ($data['slug'] ?? ('kb-' . bin2hex(random_bytes(4))));
            Database::connection()->prepare(
                'INSERT INTO rcc_kb_articles (tenant_id, category_id, slug, title, title_ar, body, body_ar, visibility, status, author_user_id)
                 VALUES (:tid, :cat, :slug, :title, :title_ar, :body, :body_ar, :vis, :status, :uid)'
            )->execute([
                'tid' => $tenantId,
                'cat' => $data['category_id'] ?? null,
                'slug' => $slug,
                'title' => (string) ($data['title'] ?? 'Article'),
                'title_ar' => $data['title_ar'] ?? null,
                'body' => (string) ($data['body'] ?? ''),
                'body_ar' => $data['body_ar'] ?? null,
                'vis' => (string) ($data['visibility'] ?? 'internal'),
                'status' => (string) ($data['status'] ?? 'draft'),
                'uid' => $userId,
            ]);
            $id = (int) Database::connection()->lastInsertId();
        }
        $this->audit->log($tenantId, 'kb.article.save', $userId, 'kb_article', $id);
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_kb_articles WHERE tenant_id = :tid AND id = :id');
        $stmt->execute(['tid' => $tenantId, 'id' => $id]);
        return $stmt->fetch() ?: [];
    }

    public function feedback(int $tenantId, int $articleId, bool $helpful, ?int $userId, ?string $comment = null): void
    {
        Database::connection()->prepare(
            'INSERT INTO rcc_kb_feedback (tenant_id, article_id, user_id, is_helpful, comment) VALUES (:tid, :aid, :uid, :h, :c)'
        )->execute(['tid' => $tenantId, 'aid' => $articleId, 'uid' => $userId, 'h' => $helpful ? 1 : 0, 'c' => $comment]);
    }
}
