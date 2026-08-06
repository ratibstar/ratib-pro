<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers;

use Rateb\App\Core\SessionManager;
use Rateb\App\Logistics\Services\LogisticsReportsService;

final class LogisticsReportsController extends LogisticsBaseController
{
    private const RESOURCE = 'logistics/reports';

    private LogisticsReportsService $reports;

    public function __construct()
    {
        $this->reports = new LogisticsReportsService();
    }

    public function index(): void
    {
        $this->bootstrapLogistics();
        $this->guardView(self::RESOURCE);
        $this->logisticsView('reports/index', [
            'title' => __('logistics_reports'),
            'catalog' => $this->reports->catalog(),
            'kpis' => $this->reports->dashboardKpis($this->companyId()),
        ]);
    }

    public function show(array $params): void
    {
        $this->bootstrapLogistics();
        $this->guardView(self::RESOURCE);
        $key = (string) ($params['type'] ?? ($_GET['type'] ?? ''));
        try {
            $report = $this->reports->build($this->companyId(), $key);
            $this->logisticsView('reports/show', [
                'title' => (string) ($report['title'] ?? __('logistics_reports')),
                'report' => $report,
            ]);
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_app_url(self::RESOURCE));
        }
    }
}
