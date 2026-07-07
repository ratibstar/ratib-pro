<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

final class SystemActorContext
{
    public const SYSTEM_USER_UUID = '00000000-0000-4000-8000-000000000001';

    public const SYSTEM_USER_ID = 1;

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function runAsSystem(callable $callback): mixed
    {
        return InternalActorContext::runAs(self::SYSTEM_USER_ID, $callback);
    }
}
