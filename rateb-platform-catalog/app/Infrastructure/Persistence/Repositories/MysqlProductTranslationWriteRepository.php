<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductTranslationWriteRepositoryInterface;

final class MysqlProductTranslationWriteRepository extends BaseRepository implements ProductTranslationWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'product_translations';
    }

    public function upsertForProduct(int $productId, array $translations, ?int $actorId = null): void
    {
        foreach ($translations as $translation) {
            if (!isset($translation['language_code'], $translation['name'])) {
                continue;
            }

            $existing = $this->fetchOne(
                'SELECT id FROM product_translations
                 WHERE product_id = :product_id AND language_code = :language_code AND deleted_at IS NULL
                 LIMIT 1',
                [
                    'product_id' => $productId,
                    'language_code' => (string) $translation['language_code'],
                ],
                false
            );

            if ($existing === null) {
                $stmt = $this->writePdo->prepare(
                    'INSERT INTO product_translations (
                        uuid, product_id, language_code, name, short_description, description, created_by
                     ) VALUES (
                        :uuid, :product_id, :language_code, :name, :short_description, :description, :created_by
                     )'
                );
                $stmt->execute([
                    'uuid' => $this->newUuid(),
                    'product_id' => $productId,
                    'language_code' => (string) $translation['language_code'],
                    'name' => (string) $translation['name'],
                    'short_description' => $translation['short_description'] ?? null,
                    'description' => $translation['description'] ?? null,
                    'created_by' => $actorId,
                ]);
                continue;
            }

            $stmt = $this->writePdo->prepare(
                'UPDATE product_translations SET
                    name = :name,
                    short_description = :short_description,
                    description = :description,
                    updated_by = :updated_by
                 WHERE id = :id'
            );
            $stmt->execute([
                'id' => (int) $existing['id'],
                'name' => (string) $translation['name'],
                'short_description' => $translation['short_description'] ?? null,
                'description' => $translation['description'] ?? null,
                'updated_by' => $actorId,
            ]);
        }
    }
}
