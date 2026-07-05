<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Services\Bridge\PosBranchBridgeService;
use Rateb\App\Pos\Services\Bridge\PosWarehouseBridgeService;
use Rateb\App\Pos\Support\PosFkValidator;

/** Terminal / shift / branch / warehouse / register session context. */
final class PosContextService
{
    public function __construct(
        private PosBranchBridgeService $branch = new PosBranchBridgeService(),
        private PosWarehouseBridgeService $warehouse = new PosWarehouseBridgeService(),
        private PosSessionService $session = new PosSessionService(),
    ) {
    }

    public function bootstrapTenant(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $companyId = function_exists('rateb_require_ops_company')
            ? rateb_require_ops_company()
            : (int) (SessionManager::get('rateb_company_id') ?? 0);
        if ($companyId > 0) {
            $this->branch->bootstrap($companyId);
        }
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $companyId = (int) (TenantContext::companyId() ?? 0);
        $register = $this->session->snapshot();
        $context = $this->resolveShiftContext($companyId, $register);

        return [
            'session' => $register,
            'company_id' => $companyId,
            'branch_filter' => \Rateb\App\Core\BranchContext::effectiveFilterIds(),
            'terminal' => $context['terminal'],
            'shift' => $context['shift'],
            'branch' => $context['branch'],
            'warehouse' => $context['warehouse'],
            'register_ready' => $context['shift'] !== null,
        ];
    }

    public function syncRegisterFromOpenShift(int $companyId, int $userId): void
    {
        if ($companyId < 1 || $userId < 1) {
            return;
        }
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT s.*, t.warehouse_id AS terminal_warehouse_id, t.name AS terminal_name, t.code AS terminal_code
             FROM rateb_pos_shifts s
             INNER JOIN rateb_pos_terminals t ON t.id = s.terminal_id
             WHERE s.company_id = :cid AND s.user_id = :uid AND s.status = :st
             ORDER BY s.id DESC LIMIT 1'
        );
        $stmt->execute(['cid' => $companyId, 'uid' => $userId, 'st' => 'open']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }

        $terminalId = (int) ($row['terminal_id'] ?? 0);
        $shiftId = (int) ($row['id'] ?? 0);
        $branchId = (int) ($row['branch_id'] ?? 0);
        $warehouseId = (int) ($row['terminal_warehouse_id'] ?? 0);

        try {
            PosFkValidator::assertTerminal($terminalId, $companyId);
        } catch (\Throwable $e) {
            return;
        }

        $current = $this->session->current();
        if ((int) ($current['shift_id'] ?? 0) !== $shiftId) {
            $this->session->bindRegisterContext($companyId, $userId, $terminalId, $shiftId, $branchId, $warehouseId ?: null);
        }
    }

    /** @param array<string, mixed> $register */
    private function resolveShiftContext(int $companyId, array $register): array
    {
        $terminal = null;
        $shift = null;
        $branch = null;
        $warehouse = null;

        $terminalId = (int) ($register['terminal_id'] ?? 0);
        $shiftId = (int) ($register['shift_id'] ?? 0);

        if ($companyId > 0 && $terminalId > 0) {
            try {
                $termRow = PosFkValidator::assertTerminal($terminalId, $companyId);
                $terminal = [
                    'id' => $terminalId,
                    'code' => (string) ($termRow['code'] ?? ''),
                    'name' => (string) ($termRow['name'] ?? ''),
                ];
                $whId = (int) ($termRow['warehouse_id'] ?? 0);
                if ($whId > 0) {
                    $warehouse = $this->warehouse->label($whId);
                }
            } catch (\Throwable $e) {
                $terminal = null;
            }
        }

        if ($companyId > 0 && $shiftId > 0) {
            try {
                $shiftRow = PosFkValidator::assertShift($shiftId, $companyId);
                $shift = [
                    'id' => $shiftId,
                    'shift_no' => (string) ($shiftRow['shift_no'] ?? ''),
                    'status' => (string) ($shiftRow['status'] ?? ''),
                ];
                $branchId = (int) ($shiftRow['branch_id'] ?? 0);
                if ($branchId > 0) {
                    $branch = $this->branch->label($branchId);
                }
            } catch (\Throwable $e) {
                $shift = null;
            }
        }

        return [
            'terminal' => $terminal,
            'shift' => $shift,
            'branch' => $branch,
            'warehouse' => $warehouse,
        ];
    }
}
