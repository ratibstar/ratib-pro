<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Validators;

final class BundleCircularReferenceValidator
{
    /**
     * @param list<array{component_product_uuid: string}> $components
     */
    public function assertNoCircularReference(string $bundleProductUuid, array $components): void
    {
        foreach ($components as $component) {
            $componentUuid = (string) ($component['component_product_uuid'] ?? '');
            if ($componentUuid === $bundleProductUuid) {
                throw new \InvalidArgumentException('Bundle cannot contain itself');
            }
        }

        $visited = [$bundleProductUuid => true];
        $queue = array_map(
            static fn (array $c): string => (string) $c['component_product_uuid'],
            $components
        );

        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === '') {
                continue;
            }
            if (isset($visited[$current])) {
                throw new \InvalidArgumentException('Circular bundle reference detected');
            }
            $visited[$current] = true;
        }
    }
}
