<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Core\Container;

catalog_test('Container resolves registered factory', static function (): void {
    $container = new Container();
    $container->set('greeting', static fn (): string => 'catalog');

    catalog_assert_same('catalog', $container->get('greeting'));
});

catalog_test('Container auto-wires constructor dependencies', static function (): void {
  $container = new Container();
  $container->set(HealthProbeDep::class, static fn (): HealthProbeDep => new HealthProbeDep('ok'));

  $service = $container->make(HealthProbeService::class);
  catalog_assert_true($service instanceof HealthProbeService);
  catalog_assert_same('ok', $service->value());
});

final class HealthProbeDep
{
    public function __construct(public readonly string $value)
    {
    }
}

final class HealthProbeService
{
    public function __construct(private readonly HealthProbeDep $dep)
    {
    }

    public function value(): string
    {
        return $this->dep->value;
    }
}
