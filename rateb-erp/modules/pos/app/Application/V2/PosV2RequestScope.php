<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

/**
 * Binds a single PosV2RequestCompositionRoot per HTTP request.
 *
 * Uses $_SERVER (request-local) — not a process-wide static singleton.
 */
final class PosV2RequestScope
{
    private const SERVER_KEY = 'RATEB_POS_V2_COMPOSITION_ROOT';

    public static function bind(PosV2RequestCompositionRoot $root): void
    {
        $_SERVER[self::SERVER_KEY] = $root;
    }

    public static function get(): ?PosV2RequestCompositionRoot
    {
        $value = $_SERVER[self::SERVER_KEY] ?? null;

        return $value instanceof PosV2RequestCompositionRoot ? $value : null;
    }

    public static function ensure(): PosV2RequestCompositionRoot
    {
        $existing = self::get();
        if ($existing !== null) {
            return $existing;
        }

        $root = PosV2RequestCompositionRoot::create();
        self::bind($root);

        return $root;
    }
}
