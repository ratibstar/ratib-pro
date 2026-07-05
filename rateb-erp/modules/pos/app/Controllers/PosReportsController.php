<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Pos\Services\PosReportService;
use Rateb\App\Pos\Services\PosShiftService;
use Rateb\App\Pos\Support\PosBranchScope;

final class PosReportsController extends PosBaseController
{
    private const RESOURCE = 'pos/reports';

    private PosReportService $reports;
    private PosShiftService $shifts;

    public function __construct()
    {
        $this->reports = new PosReportService();
        $this->shifts = new PosShiftService();
    }

    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView(self::RESOURCE);
        $companyId = $this->companyId();
        $this->posView('reports/index', [
            'title' => __('pos_reports'),
            'snapshots' => $this->reports->listSnapshots($companyId),
            'shifts' => $this->shifts->listForCompany($companyId, 30),
            'csrf' => Csrf::token(),
        ]);
    }

    public function xReport(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosView(self::RESOURCE);
        $shiftId = (int) ($params['id'] ?? 0);
        $companyId = $this->companyId();
        try {
            $report = $this->reports->buildXReport($shiftId, $companyId);
            $this->posView('reports/show', [
                'title' => __('pos_x_report'),
                'report' => $report,
                'csrf' => Csrf::token(),
            ]);
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_app_url('pos/reports'));
        }
    }

    public function zReport(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosView(self::RESOURCE);
        $snapshotId = (int) ($params['id'] ?? 0);
        $row = $this->reports->findSnapshot($snapshotId, $this->companyId());
        if (!$row) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_app_url('pos/reports'));
            return;
        }
        try {
            PosBranchScope::assertSnapshotReadable($row);
        } catch (\Throwable $e) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('pos/reports'));
            return;
        }
        $this->posView('reports/show', [
            'title' => __('pos_z_report'),
            'report' => is_array($row['snapshot'] ?? null) ? $row['snapshot'] : [],
            'snapshot' => $row,
            'csrf' => Csrf::token(),
        ]);
    }

    /** JSON API for register / integrations. */
    public function xReportJson(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosView(self::RESOURCE);
        $shiftId = (int) ($params['id'] ?? 0);
        try {
            $this->jsonOk($this->reports->buildXReport($shiftId, $this->companyId()));
        } catch (\Throwable $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }

    private function jsonOk(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function jsonError(string $message, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
