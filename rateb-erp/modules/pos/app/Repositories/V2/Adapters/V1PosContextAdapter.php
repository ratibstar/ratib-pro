<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Pos\Repositories\V2\Contracts\PosV2PosContextPortInterface;
use Rateb\App\Pos\Services\PosContextService;

/**
 * Wraps V1 PosContextService — no duplicated SQL or business logic.
 */
final class V1PosContextAdapter implements PosV2PosContextPortInterface
{
    public function __construct(
        private readonly PosContextService $posContext,
    ) {
    }

    public function bootstrapTenant(): void
    {
        $this->posContext->bootstrapTenant();
    }

    public function syncRegisterFromOpenShift(int $companyId, int $userId): void
    {
        $this->posContext->syncRegisterFromOpenShift($companyId, $userId);
    }

    public function getRegisterMetadata(): array
    {
        $snapshot = $this->posContext->snapshot();
        $session = is_array($snapshot['session'] ?? null) ? $snapshot['session'] : [];

        $terminal = $this->normalizeTerminal($snapshot['terminal'] ?? null);
        $shift = $this->normalizeShift($snapshot['shift'] ?? null);
        $branch = $this->normalizeBranch($snapshot['branch'] ?? null);

        return [
            'company_id' => (int) ($snapshot['company_id'] ?? 0),
            'session_id' => (int) ($session['db_session_id'] ?? 0),
            'branch_id' => (int) ($branch['id'] ?? ($session['branch_id'] ?? 0)),
            'warehouse_id' => (int) ($terminal['warehouse_id'] ?? ($session['warehouse_id'] ?? 0)),
            'register_ready' => (bool) ($snapshot['register_ready'] ?? false),
            'terminal' => $terminal,
            'shift' => $shift,
            'branch' => $branch,
        ];
    }

    /**
     * @return array{id: int, code: string, name: string, warehouse_id: int}|null
     */
    private function normalizeTerminal(mixed $raw): ?array
    {
        if (!is_array($raw) || (int) ($raw['id'] ?? 0) < 1) {
            return null;
        }

        return [
            'id' => (int) $raw['id'],
            'code' => (string) ($raw['code'] ?? ''),
            'name' => (string) ($raw['name'] ?? ''),
            'warehouse_id' => (int) ($raw['warehouse_id'] ?? 0),
        ];
    }

    /**
     * @return array{id: int, shift_no: string, status: string}|null
     */
    private function normalizeShift(mixed $raw): ?array
    {
        if (!is_array($raw) || (int) ($raw['id'] ?? 0) < 1) {
            return null;
        }

        return [
            'id' => (int) $raw['id'],
            'shift_no' => (string) ($raw['shift_no'] ?? ''),
            'status' => (string) ($raw['status'] ?? ''),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function normalizeBranch(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $id = (int) ($raw['id'] ?? 0);
        if ($id < 1) {
            return null;
        }

        return [
            'id' => $id,
            'name' => (string) ($raw['name'] ?? $raw['label'] ?? ''),
        ];
    }
}
