<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Services\ProductSnapshotBuilder;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlCompletenessDataReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductAttributeReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductBarcodeReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductBundleReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductFileReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductImageReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductRelationReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSeoReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSnapshotGraphReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSnapshotGraphWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductTranslationReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductVariantReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductVideoReadRepository;

function phase28_graph_read_repository(): MysqlProductSnapshotGraphReadRepository
{
    return new MysqlProductSnapshotGraphReadRepository(
        null,
        null,
        new MysqlProductVariantReadRepository(),
        new MysqlProductBarcodeReadRepository(),
        new MysqlProductBundleReadRepository(),
        new MysqlProductImageReadRepository(),
        new MysqlProductFileReadRepository(),
        new MysqlProductVideoReadRepository()
    );
}

function phase28_snapshot_builder(): ProductSnapshotBuilder
{
    return new ProductSnapshotBuilder(
        new MysqlProductReadRepository(),
        new MysqlProductTranslationReadRepository(),
        new MysqlProductAttributeReadRepository(),
        new MysqlProductRelationReadRepository(),
        new MysqlProductSeoReadRepository(),
        new MysqlCompletenessDataReadRepository(),
        phase28_graph_read_repository()
    );
}

function phase28_graph_write_repository(): MysqlProductSnapshotGraphWriteRepository
{
    return new MysqlProductSnapshotGraphWriteRepository();
}
