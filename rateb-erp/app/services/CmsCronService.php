<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use PDO;

final class CmsCronService
{
    /** @return array{pages: int, articles: int} */
    public function publishScheduled(): array
    {
        $pdo = Database::connection();
        $pages = $this->publishTable(
            $pdo,
            'rateb_cms_pages',
            "status = 'scheduled' AND published_at IS NOT NULL AND published_at <= NOW()"
        );
        $articles = $this->publishTable(
            $pdo,
            'rateb_cms_blog_articles',
            "status = 'scheduled' AND published_at IS NOT NULL AND published_at <= NOW()"
        );
        return ['pages' => $pages, 'articles' => $articles];
    }

    private function publishTable(PDO $pdo, string $table, string $where): int
    {
        $sql = "UPDATE {$table} SET status = 'published' WHERE {$where}";
        $stmt = $pdo->exec($sql);
        return $stmt === false ? 0 : (int) $stmt;
    }
}
