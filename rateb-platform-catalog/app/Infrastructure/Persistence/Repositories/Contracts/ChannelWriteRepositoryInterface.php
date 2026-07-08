<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts;

interface ChannelWriteRepositoryInterface
{
    /**
     * @param list<array<string, mixed>> $assignments
     */
    public function replaceProductChannels(string $productUuid, array $assignments): void;
}
