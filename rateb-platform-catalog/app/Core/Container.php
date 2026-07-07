<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Core;

use Closure;

final class Container
{
    /** @var array<string, Closure(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, Closure $factory): void
    {
        unset($this->instances[$id]);
        $this->factories[$id] = $factory;
    }

    public function instance(string $id, mixed $object): void
    {
        $this->instances[$id] = $object;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new \RuntimeException('Service not registered: ' . $id);
        }

        $this->instances[$id] = ($this->factories[$id])($this);

        return $this->instances[$id];
    }

    public function make(string $className): object
    {
        if ($this->has($className)) {
            $resolved = $this->get($className);

            return is_object($resolved) ? $resolved : throw new \RuntimeException('Resolved service is not an object: ' . $className);
        }

        if (!class_exists($className)) {
            throw new \RuntimeException('Class not found: ' . $className);
        }

        $ref = new \ReflectionClass($className);
        if (!$ref->isInstantiable()) {
            throw new \RuntimeException('Class is not instantiable: ' . $className);
        }

        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                    continue;
                }

                throw new \RuntimeException('Cannot resolve parameter $' . $param->getName() . ' for ' . $className);
            }

            $args[] = $this->get($type->getName());
        }

        return $ref->newInstanceArgs($args);
    }
}
