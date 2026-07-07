<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductCompletenessWriteRepositoryInterface;

final class MysqlProductCompletenessWriteRepository extends BaseRepository implements ProductCompletenessWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'product_completeness_scores';
    }

    public function upsert(
        int $productId,
        string $locale,
        float $score,
        bool $blockingFailed,
        array $failedRules
    ): void {
        $this->writePdo->prepare(
            'INSERT INTO product_completeness_scores (product_id, locale, score, blocking_failed, failed_rules, computed_at)
             VALUES (:product_id, :locale, :score, :blocking_failed, :failed_rules, CURRENT_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                blocking_failed = VALUES(blocking_failed),
                failed_rules = VALUES(failed_rules),
                computed_at = CURRENT_TIMESTAMP(6),
                updated_at = CURRENT_TIMESTAMP(6)'
        )->execute([
            'product_id' => $productId,
            'locale' => $locale,
            'score' => round($score, 2),
            'blocking_failed' => (int) $blockingFailed,
            'failed_rules' => json_encode(array_values($failedRules), JSON_UNESCAPED_UNICODE) ?: '[]',
        ]);
    }
}
