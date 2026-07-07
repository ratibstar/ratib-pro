<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use PDO;
use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Core\Database;
use Rateb\PlatformCatalog\Support\Uuid;

abstract class BaseRepository
{
    protected readonly PDO $readPdo;

    protected readonly PDO $writePdo;

    public function __construct(
        ?PDO $readPdo = null,
        ?PDO $writePdo = null
    ) {
        $this->readPdo = $readPdo ?? Database::readConnection();
        $this->writePdo = $writePdo ?? Database::writeConnection();
    }

    abstract protected function table(): string;

    protected function notDeletedClause(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';

        return $prefix . 'deleted_at IS NULL';
    }

    protected function newUuid(): string
    {
        return Uuid::v4();
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    protected function fetchAll(string $sql, array $params = [], bool $useRead = true): array
    {
        $pdo = $useRead ? $this->readPdo : $this->writePdo;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    protected function fetchOne(string $sql, array $params = [], bool $useRead = true): ?array
    {
        $pdo = $useRead ? $this->readPdo : $this->writePdo;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return is_array($row) ? $row : null;
    }

    protected function localeParams(LocaleContext $locale): array
    {
        return [
            'locale' => $locale->locale,
            'fallback' => $locale->fallback,
        ];
    }

    protected function translationSelect(string $translationAlias, string $field): string
    {
        return sprintf(
            '%s AS %s',
            $this->translationCoalesce($translationAlias, $field),
            $field
        );
    }

    protected function translationCoalesce(string $translationAlias, string $field): string
    {
        return sprintf(
            'COALESCE(%1$s_loc.%2$s, %1$s_fb.%2$s)',
            $translationAlias,
            $field
        );
    }

    protected function translationJoin(
        string $entityAlias,
        string $entityIdColumn,
        string $translationTable,
        string $translationAlias,
        string $foreignKeyColumn
    ): string {
        return sprintf(
            'LEFT JOIN %1$s AS %2$s_loc ON %2$s_loc.%3$s = %4$s.%5$s
                AND %2$s_loc.language_code = :locale AND %2$s_loc.deleted_at IS NULL
             LEFT JOIN %1$s AS %2$s_fb ON %2$s_fb.%3$s = %4$s.%5$s
                AND %2$s_fb.language_code = :fallback AND %2$s_fb.deleted_at IS NULL',
            $translationTable,
            $translationAlias,
            $foreignKeyColumn,
            $entityAlias,
            $entityIdColumn
        );
    }

    protected function localeFallbackUsed(?array $row, LocaleContext $locale): bool
    {
        if ($row === null) {
            return false;
        }

        return !isset($row['resolved_language_code'])
            || (string) $row['resolved_language_code'] !== $locale->locale;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function transaction(callable $callback): mixed
    {
        if ($this->writePdo->inTransaction()) {
            return $callback();
        }

        $this->writePdo->beginTransaction();
        try {
            $result = $callback();
            $this->writePdo->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->writePdo->inTransaction()) {
                $this->writePdo->rollBack();
            }
            throw $e;
        }
    }

    protected function resolveProductIdByUuid(string $productUuid): int
    {
        $row = $this->fetchOne(
            'SELECT id FROM products WHERE uuid = :uuid AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $productUuid],
            false
        );

        if ($row === null) {
            throw new \RuntimeException('Product not found', 404);
        }

        return (int) $row['id'];
    }

    protected function resolveVariantIdByUuid(string $variantUuid, int $productId): int
    {
        $row = $this->fetchOne(
            'SELECT id FROM product_variants
             WHERE uuid = :uuid AND product_id = :product_id AND deleted_at IS NULL LIMIT 1',
            ['uuid' => $variantUuid, 'product_id' => $productId],
            false
        );

        if ($row === null) {
            throw new \RuntimeException('Variant not found for product', 404);
        }

        return (int) $row['id'];
    }
}
