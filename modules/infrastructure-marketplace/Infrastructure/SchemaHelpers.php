<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Infrastructure;

/**
 * Schema introspection without prepared SHOW TABLES (unsupported with native MySQL prepares).
 */
final class SchemaHelpers
{
    public static function tableExists(\PDO $pdo, string $table): bool
    {
        if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = ?
                 LIMIT 1'
            );
            $stmt->execute([$table]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
