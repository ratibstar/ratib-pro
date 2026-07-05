<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

/**
 * V1 adapter port — tenant bootstrap and register metadata (no cart/pricing/inventory).
 */
interface PosV2PosContextPortInterface
{
    public function bootstrapTenant(): void;

    public function syncRegisterFromOpenShift(int $companyId, int $userId): void;

    /**
     * Register metadata from V1 PosContextService (terminal/shift/branch only).
     *
     * @return array{
     *   company_id: int,
     *   session_id: int,
     *   branch_id: int,
     *   warehouse_id: int,
     *   register_ready: bool,
     *   terminal: array{id: int, code: string, name: string, warehouse_id: int}|null,
     *   shift: array{id: int, shift_no: string, status: string}|null,
     *   branch: array{id: int, name: string}|null
     * }
     */
    public function getRegisterMetadata(): array;
}
