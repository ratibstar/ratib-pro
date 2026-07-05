<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

/** Resolves POS permission slugs granted to the current cashier. */
interface PosV2PermissionsPortInterface
{
    /**
     * @return list<string>
     */
    public function resolveForUser(int $userId): array;
}
