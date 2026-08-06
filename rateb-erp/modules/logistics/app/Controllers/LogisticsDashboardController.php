<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers;

use Rateb\App\Logistics\Services\DriverService;
use Rateb\App\Logistics\Services\FleetService;
use Rateb\App\Logistics\Services\LogisticsReportsService;
use Rateb\App\Logistics\Services\ShipmentService;
use Rateb\App\Logistics\Services\TripService;

final class LogisticsDashboardController extends LogisticsBaseController
{
    private const RESOURCE = 'logistics';

    public function index(): void
    {
        $this->bootstrapLogistics();
        $this->guardView(self::RESOURCE);
        $companyId = $this->companyId();

        $fleet = new FleetService();
        $drivers = new DriverService();
        $trips = new TripService();
        $shipments = new ShipmentService();
        $kpis = (new LogisticsReportsService())->dashboardKpis($companyId);

        $tripRows = $trips->listForCompany($companyId, 500);
        $shipmentRows = $shipments->listForCompany($companyId, 500);
        $tripCounts = $this->countByStatus($tripRows);
        $shipmentCounts = $this->countByStatus($shipmentRows);

        $this->logisticsView('dashboard/index', [
            'title' => __('logistics_dashboard'),
            'stats' => [
                'vehicles' => $fleet->countForCompany($companyId),
                'drivers' => $drivers->countForCompany($companyId),
                'trips' => count($tripRows),
                'shipments' => (int) ($kpis['shipments'] ?? count($shipmentRows)),
                'delivered' => (int) ($kpis['delivered'] ?? 0),
                'pending' => (int) ($kpis['pending'] ?? 0),
                'trips_active' => (int) ($kpis['active_trips'] ?? 0),
                'shipments_in_transit' => (int) (
                    ($shipmentCounts['shipped'] ?? 0)
                    + ($shipmentCounts['out_for_delivery'] ?? 0)
                    + ($shipmentCounts['packed'] ?? 0)
                ),
                'expenses_total' => (float) ($kpis['expenses_total'] ?? 0),
                'expenses_posted' => (float) ($kpis['expenses_posted'] ?? 0),
                'expenses_draft' => (float) ($kpis['expenses_draft'] ?? 0),
                'expenses_count' => (int) ($kpis['expenses_count'] ?? 0),
            ],
            'tripCounts' => $tripCounts,
            'shipmentCounts' => $shipmentCounts,
            'recentTrips' => array_slice($tripRows, 0, 8),
            'recentShipments' => array_slice($shipmentRows, 0, 8),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function countByStatus(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === '') {
                continue;
            }
            $out[$status] = ($out[$status] ?? 0) + 1;
        }

        return $out;
    }
}
