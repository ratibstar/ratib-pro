<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosCashDrawer;
use Rateb\App\Pos\Models\PosShift;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Rateb\App\Pos\Support\PosDocumentCodes;
use Rateb\App\Pos\Support\PosFkValidator;

final class PosShiftService
{
    public function __construct(
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
        private PosCashDrawerService $drawers = new PosCashDrawerService(),
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listForCompany(int $companyId, int $limit = 100, int $offset = 0): array
    {
        if ($companyId < 1) {
            return [];
        }
        TenantContext::setCompanyId($companyId);
        return (new PosShift())->all($limit, $offset);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $companyId): ?array
    {
        if ($id < 1) {
            return null;
        }
        try {
            return PosFkValidator::assertShift($id, $companyId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function openShift(int $companyId, int $terminalId, int $userId, float $openingFloat): int
    {
        if ($companyId < 1 || $terminalId < 1 || $userId < 1) {
            throw new \RuntimeException(__('invalid_request'));
        }
        $terminal = PosFkValidator::assertTerminal($terminalId, $companyId);
        if (($terminal['status'] ?? '') !== 'active') {
            throw new \RuntimeException(__('pos_terminal_inactive'));
        }

        $db = Database::connection();
        $openStmt = $db->prepare(
            'SELECT id FROM rateb_pos_shifts WHERE terminal_id = :tid AND status = :st LIMIT 1 FOR UPDATE'
        );

        $db->beginTransaction();
        try {
            $lockStmt = $db->prepare(
                'SELECT id FROM rateb_pos_terminals WHERE id = :id AND company_id = :cid LIMIT 1 FOR UPDATE'
            );
            $lockStmt->execute(['id' => $terminalId, 'cid' => $companyId]);
            if (!$lockStmt->fetchColumn()) {
                throw new \RuntimeException(__('no_records'));
            }

            $openStmt->execute(['tid' => $terminalId, 'st' => 'open']);
            if ($openStmt->fetchColumn()) {
                throw new \RuntimeException(__('pos_shift_already_open'));
            }

            $branchId = (int) ($terminal['branch_id'] ?? 0);
            $shiftNo = (new PosShift())->generateDocumentCode(PosDocumentCodes::SHIFT, 'shift_no');
            $shiftId = (new PosShift())->create([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'terminal_id' => $terminalId,
                'user_id' => $userId,
                'shift_no' => $shiftNo,
                'status' => 'open',
                'opening_float' => max(0, round($openingFloat, 2)),
                'expected_cash' => max(0, round($openingFloat, 2)),
            ]);

            $this->drawers->openForShift($companyId, $branchId, $terminalId, $shiftId, $userId, $openingFloat);

            $this->audit->log('pos_shift_open', 'pos_shift', $shiftId, [
                'terminal_id' => $terminalId,
                'opening_float' => $openingFloat,
                'shift_no' => $shiftNo,
            ]);
            $db->commit();
            return $shiftId;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function closeShift(int $shiftId, int $companyId, int $userId, float $closingFloat, string $notes = ''): array
    {
        $shift = PosFkValidator::assertShift($shiftId, $companyId);
        if (($shift['status'] ?? '') !== 'open') {
            throw new \RuntimeException(__('pos_shift_not_open'));
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $drawer = $this->drawers->closeForShift($shiftId, $companyId, $userId, $closingFloat);
            $expected = (float) ($drawer['expected_balance'] ?? $shift['expected_cash'] ?? 0);
            $variance = round($closingFloat - $expected, 2);

            (new PosShift())->update($shiftId, [
                'status' => 'closed',
                'closed_at' => date('Y-m-d H:i:s'),
                'closing_float' => round($closingFloat, 2),
                'expected_cash' => $expected,
                'variance' => $variance,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $zReport = (new PosReportService())->buildAndPersistZReport($shiftId, $companyId, $userId);

            $this->audit->log('pos_shift_close', 'pos_shift', $shiftId, [
                'closing_float' => $closingFloat,
                'expected' => $expected,
                'variance' => $variance,
                'z_report_no' => $zReport['report_no'] ?? null,
                'z_snapshot_id' => $zReport['snapshot_id'] ?? null,
            ]);
            $db->commit();
            return [
                'ok' => true,
                'variance' => $variance,
                'z_report' => $zReport,
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
