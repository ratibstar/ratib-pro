<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Pos\Services\PosCashDrawerService;
use Rateb\App\Pos\Services\PosFormLookupService;
use Rateb\App\Pos\Services\PosSessionService;
use Rateb\App\Pos\Services\PosShiftService;
use Rateb\App\Pos\Support\PosFkValidator;

final class PosShiftsController extends PosBaseController
{
    private const RESOURCE = 'pos/shifts';

    private PosShiftService $shifts;

    public function __construct()
    {
        $this->shifts = new PosShiftService();
    }

    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView(self::RESOURCE);
        $companyId = $this->companyId();
        $canOpen = function_exists('rateb_can') && (rateb_can('pos.shift.open') || rateb_is_super_admin());
        $this->posView('shifts/index', [
            'title' => __('pos_shifts'),
            'items' => $this->shifts->listForCompany($companyId),
            'fields' => $this->indexFields(),
            'routePrefix' => rateb_app_route(self::RESOURCE),
            'csrf' => Csrf::token(),
            'permissionResource' => self::RESOURCE,
            'createEnabled' => $canOpen,
            'actionsEnabled' => false,
            'viewEnabled' => true,
            'bulkEnabled' => false,
            'exportEnabled' => false,
            'createUrl' => rateb_app_url('pos/shifts/open'),
        ], 'pos-pages-shell');
    }

    public function show(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosView(self::RESOURCE);
        $id = (int) ($params['id'] ?? 0);
        $shift = $this->shifts->find($id, $this->companyId());
        if (!$shift) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_app_url(self::RESOURCE));
        }
        $canClose = ($shift['status'] ?? '') === 'open'
            && (function_exists('rateb_can') && (rateb_can('pos.shift.close') || rateb_is_super_admin()));
        $this->posView('shifts/show', [
            'title' => __('pos_shifts'),
            'shift' => $shift,
            'csrf' => Csrf::token(),
            'canClose' => $canClose,
        ], 'pos-pages-shell');
    }

    /** Legacy crud links — shifts are view/close only. */
    public function editRedirect(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosView(self::RESOURCE);
        $id = (int) ($params['id'] ?? 0);
        $this->redirect(rateb_app_url(self::RESOURCE . '/' . $id));
    }

    public function openForm(): void
    {
        $this->bootstrapPos();
        $this->guardPosPermission('pos.shift.open', self::RESOURCE);
        $lookups = ['pos_terminals' => (new PosFormLookupService())->activeTerminals($this->companyId())];
        $this->posView('shifts/open', [
            'title' => __('pos_shift_open'),
            'csrf' => Csrf::token(),
            'lookups' => $lookups,
        ], 'pos-pages-shell');
    }

    public function openStore(): void
    {
        $this->bootstrapPos();
        $this->guardPosPermission('pos.shift.open', self::RESOURCE);
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url('pos/shifts/open'));
        }
        try {
            $companyId = $this->companyId();
            $terminalId = (int) $this->input('terminal_id', 0);
            $userId = $this->userId();
            $shiftId = $this->shifts->openShift(
                $companyId,
                $terminalId,
                $userId,
                (float) $this->input('opening_float', 0)
            );
            try {
                $terminal = PosFkValidator::assertTerminal($terminalId, $companyId);
                $branchId = (int) ($terminal['branch_id'] ?? 0);
                $warehouseId = (int) ($terminal['warehouse_id'] ?? 0);
                (new PosSessionService())->bindRegisterContext(
                    $companyId,
                    $userId,
                    $terminalId,
                    $shiftId,
                    $branchId,
                    $warehouseId > 0 ? $warehouseId : null
                );
            } catch (\Throwable $e) {
                // Shift opened; register context syncs on next POS visit.
            }
            SessionManager::flash('success', __('pos_shift_opened'));
            $this->redirect(rateb_app_url('pos/shifts/' . $shiftId));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_app_url('pos/shifts/open'));
        }
    }

    public function closeForm(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosPermission('pos.shift.close', self::RESOURCE);
        $id = (int) ($params['id'] ?? 0);
        $shift = $this->shifts->find($id, $this->companyId());
        if (!$shift || ($shift['status'] ?? '') !== 'open') {
            SessionManager::flash('error', __('pos_shift_not_open'));
            $this->redirect(rateb_app_url('pos/shifts/' . $id));
        }
        $this->posView('shifts/close', [
            'title' => __('pos_shift_close'),
            'shift' => $shift,
            'csrf' => Csrf::token(),
        ], 'pos-pages-shell');
    }

    public function closeStore(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosPermission('pos.shift.close', self::RESOURCE);
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url('pos/shifts'));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $result = $this->shifts->closeShift(
                $id,
                $this->companyId(),
                $this->userId(),
                (float) $this->input('closing_float', 0),
                trim((string) $this->input('notes', ''))
            );
            $zNo = (string) ($result['z_report']['report_no'] ?? '');
            SessionManager::flash(
                'success',
                $zNo !== '' ? __('pos_shift_closed') . ' — ' . $zNo : __('pos_shift_closed')
            );
            $this->redirect(rateb_app_url('pos/shifts/' . $id));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_app_url('pos/shifts/' . $id . '/close'));
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function indexFields(): array
    {
        return [
            ['name' => 'shift_no', 'label' => 'pos_shift_no'],
            ['name' => 'terminal_id', 'label' => 'pos_terminals', 'type' => 'fk', 'lookup' => 'pos_terminals'],
            ['name' => 'status', 'label' => 'status'],
            ['name' => 'opening_float', 'label' => 'pos_opening_float'],
            ['name' => 'opened_at', 'label' => 'opened_at'],
            ['name' => 'closed_at', 'label' => 'closed_at'],
        ];
    }
}
