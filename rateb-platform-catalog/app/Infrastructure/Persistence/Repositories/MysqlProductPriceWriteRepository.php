<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductPriceWriteRepositoryInterface;

final class MysqlProductPriceWriteRepository extends BaseRepository implements ProductPriceWriteRepositoryInterface
{
    protected function table(): string
    {
        return 'product_prices';
    }

    public function replaceForProduct(string $productUuid, array $prices): void
    {
        $this->transaction(function () use ($productUuid, $prices): void {
            $productId = $this->resolveProductIdByUuid($productUuid);
            $keptCurrencies = [];

            foreach ($prices as $price) {
                $currencyCode = (string) ($price['currency_code'] ?? '');
                if ($currencyCode === '') {
                    continue;
                }
                $keptCurrencies[] = $currencyCode;

                $existing = $this->fetchOne(
                    'SELECT id FROM product_prices
                     WHERE product_id = :product_id AND currency_code = :currency_code
                     LIMIT 1',
                    ['product_id' => $productId, 'currency_code' => $currencyCode],
                    false
                );

                if ($existing !== null) {
                    $this->writePdo->prepare(
                        'UPDATE product_prices
                         SET cost = :cost, msrp = :msrp, default_price = :default_price,
                             effective_from = :effective_from, effective_to = :effective_to,
                             is_active = :is_active, deleted_at = NULL, deleted_by = NULL,
                             updated_at = CURRENT_TIMESTAMP(6)
                         WHERE id = :id'
                    )->execute([
                        'id' => (int) $existing['id'],
                        'cost' => $price['cost'] ?? null,
                        'msrp' => $price['msrp'] ?? null,
                        'default_price' => $price['default_price'] ?? null,
                        'effective_from' => $price['effective_from'] ?? null,
                        'effective_to' => $price['effective_to'] ?? null,
                        'is_active' => (int) ($price['is_active'] ?? 1),
                    ]);
                } else {
                    $this->writePdo->prepare(
                        'INSERT INTO product_prices
                         (uuid, product_id, currency_code, cost, msrp, default_price,
                          effective_from, effective_to, is_active)
                         VALUES (:uuid, :product_id, :currency_code, :cost, :msrp, :default_price,
                                 :effective_from, :effective_to, :is_active)'
                    )->execute([
                        'uuid' => $this->newUuid(),
                        'product_id' => $productId,
                        'currency_code' => $currencyCode,
                        'cost' => $price['cost'] ?? null,
                        'msrp' => $price['msrp'] ?? null,
                        'default_price' => $price['default_price'] ?? null,
                        'effective_from' => $price['effective_from'] ?? null,
                        'effective_to' => $price['effective_to'] ?? null,
                        'is_active' => (int) ($price['is_active'] ?? 1),
                    ]);
                }
            }

            if ($keptCurrencies === []) {
                $this->writePdo->prepare(
                    'UPDATE product_prices
                     SET deleted_at = CURRENT_TIMESTAMP(6)
                     WHERE product_id = :product_id AND deleted_at IS NULL'
                )->execute(['product_id' => $productId]);

                return;
            }

            $inClause = [];
            $params = ['product_id' => $productId];
            foreach ($keptCurrencies as $index => $currencyCode) {
                $key = 'cc' . $index;
                $inClause[] = ':' . $key;
                $params[$key] = $currencyCode;
            }

            $this->writePdo->prepare(
                'UPDATE product_prices
                 SET deleted_at = CURRENT_TIMESTAMP(6)
                 WHERE product_id = :product_id AND deleted_at IS NULL
                   AND currency_code NOT IN (' . implode(',', $inClause) . ')'
            )->execute($params);
        });
    }
}
