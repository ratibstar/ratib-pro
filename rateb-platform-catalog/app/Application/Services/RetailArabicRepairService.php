<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use PDO;
use Rateb\PlatformCatalog\Core\Database;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations\M022ComprehensiveRetailSeed;

/** Re-apply RC-* retail seed translations to fix latin1→utf8 mojibake (????). */
final class RetailArabicRepairService
{
    /**
     * @return array{repaired:bool, corrupted_before:int, message:string}
     */
    public function repairIfNeeded(bool $force = false): array
    {
        $pdo = Database::writeConnection();
        try {
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Throwable) {
            // continue
        }

        $corrupted = $this->countCorruptedArabic($pdo);
        if (!$force && $corrupted < 1) {
            return [
                'repaired' => false,
                'corrupted_before' => 0,
                'message' => 'ok',
            ];
        }

        (new M022ComprehensiveRetailSeed($pdo))->up();
        $after = $this->countCorruptedArabic($pdo);

        return [
            'repaired' => true,
            'corrupted_before' => $corrupted,
            'message' => $after > 0 ? 'partial' : 'fixed',
        ];
    }

    private function countCorruptedArabic(PDO $pdo): int
    {
        try {
            return (int) $pdo->query(
                "SELECT COUNT(*)
                 FROM product_translations pt
                 INNER JOIN products p ON p.id = pt.product_id
                 WHERE p.sku LIKE 'RC-%'
                   AND p.deleted_at IS NULL
                   AND pt.deleted_at IS NULL
                   AND pt.language_code = 'ar'
                   AND pt.name LIKE '%??%'"
            )->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
