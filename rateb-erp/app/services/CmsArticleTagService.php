<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use PDO;

final class CmsArticleTagService
{
    /** @return array<int, int> */
    public function tagIdsForArticle(int $articleId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT tag_id FROM rateb_cms_article_tags WHERE article_id = :a ORDER BY tag_id ASC'
        );
        $stmt->execute(['a' => $articleId]);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'tag_id'));
    }

    /** @param array<int, int|string> $tagIds */
    public function syncForArticle(int $articleId, array $tagIds): void
    {
        $pdo = Database::connection();
        $pdo->prepare('DELETE FROM rateb_cms_article_tags WHERE article_id = :a')->execute(['a' => $articleId]);
        $ins = $pdo->prepare('INSERT IGNORE INTO rateb_cms_article_tags (article_id, tag_id) VALUES (:a, :t)');
        foreach ($tagIds as $tid) {
            $tid = (int) $tid;
            if ($tid > 0) {
                $ins->execute(['a' => $articleId, 't' => $tid]);
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function tagsForArticle(int $articleId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.* FROM rateb_cms_blog_tags t
             INNER JOIN rateb_cms_article_tags at ON at.tag_id = t.id
             WHERE at.article_id = :a ORDER BY t.name_en ASC'
        );
        $stmt->execute(['a' => $articleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
