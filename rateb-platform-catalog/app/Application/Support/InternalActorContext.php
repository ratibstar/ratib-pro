<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

/**
 * Trusted in-process actor identity (scheduler, CLI jobs).
 * Not derived from HTTP headers — immune to client spoofing.
 */
final class InternalActorContext
{
    /** @var list<int> */
    private static array $actorIdStack = [];

    public static function actorId(): ?int
    {
        if (self::$actorIdStack === []) {
            return null;
        }

        return self::$actorIdStack[array_key_last(self::$actorIdStack)];
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function runAs(int $actorId, callable $callback): mixed
    {
        self::$actorIdStack[] = $actorId;

        try {
            return $callback();
        } finally {
            array_pop(self::$actorIdStack);
        }
    }
}
