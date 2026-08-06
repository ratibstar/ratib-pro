<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Logistics\Repositories\LogisticsDriverRepository;
use Rateb\App\Logistics\Repositories\LogisticsExpenseRepository;
use Rateb\App\Logistics\Repositories\LogisticsShipmentRepository;
use Rateb\App\Logistics\Repositories\LogisticsTripRepository;
use Rateb\App\Logistics\Repositories\LogisticsVehicleRepository;

/** Admin logistics reports + dashboard KPIs (tenant-scoped, no Core edits). */
final class LogisticsReportsService
{
    public const REPORT_SHIPMENT = 'shipments';
    public const REPORT_TRIP = 'trips';
    public const REPORT_DRIVER = 'driver_performance';
    public const REPORT_VEHICLE = 'vehicle_usage';
    public const REPORT_COST = 'logistics_cost';

    public function __construct(
        private LogisticsShipmentRepository $shipments = new LogisticsShipmentRepository(),
        private LogisticsTripRepository $trips = new LogisticsTripRepository(),
        private LogisticsDriverRepository $drivers = new LogisticsDriverRepository(),
        private LogisticsVehicleRepository $vehicles = new LogisticsVehicleRepository(),
        private LogisticsExpenseRepository $expenses = new LogisticsExpenseRepository(),
    ) {
    }

    /** @return list<array{key:string,label:string}> */
    public function catalog(): array
    {
        return [
            ['key' => self::REPORT_SHIPMENT, 'label' => __('logistics_report_shipments')],
            ['key' => self::REPORT_TRIP, 'label' => __('logistics_report_trips')],
            ['key' => self::REPORT_DRIVER, 'label' => __('logistics_report_driver_performance')],
            ['key' => self::REPORT_VEHICLE, 'label' => __('logistics_report_vehicle_usage')],
            ['key' => self::REPORT_COST, 'label' => __('logistics_report_cost')],
        ];
    }

    /**
     * @return array{key:string,title:string,columns:list<string>,rows:list<array<string,mixed>>,summary:array<string,mixed>}
     */
    public function build(int $companyId, string $reportKey): array
    {
        if ($companyId < 1) {
            throw new \RuntimeException(__('select_company_ops'));
        }
        TenantContext::setCompanyId($companyId);

        return match ($reportKey) {
            self::REPORT_SHIPMENT => $this->shipmentReport($companyId),
            self::REPORT_TRIP => $this->tripReport($companyId),
            self::REPORT_DRIVER => $this->driverPerformanceReport($companyId),
            self::REPORT_VEHICLE => $this->vehicleUsageReport($companyId),
            self::REPORT_COST => $this->costReport($companyId),
            default => throw new \InvalidArgumentException('logistics_report_unknown'),
        };
    }

    /** @return array<string, int|float> */
    public function dashboardKpis(int $companyId): array
    {
        TenantContext::setCompanyId($companyId);
        $shipmentRows = $this->shipments->listForCompany($companyId, 1000, 0);
        $tripRows = $this->trips->listForCompany($companyId, 1000, 0);
        $expenseRows = $this->expenses->listForCompany($companyId, 1000, 0);

        $delivered = 0;
        $pending = 0;
        foreach ($shipmentRows as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'delivered') {
                ++$delivered;
            } elseif (!in_array($status, ['failed', 'cancelled'], true)) {
                ++$pending;
            }
        }

        $activeTrips = 0;
        foreach ($tripRows as $row) {
            if (in_array((string) ($row['status'] ?? ''), ['assigned', 'started'], true)) {
                ++$activeTrips;
            }
        }

        $expenseTotal = 0.0;
        $expensePosted = 0.0;
        $expenseDraft = 0.0;
        foreach ($expenseRows as $row) {
            $amount = (float) ($row['amount'] ?? 0);
            $expenseTotal += $amount;
            if ((string) ($row['status'] ?? '') === 'posted') {
                $expensePosted += $amount;
            } elseif ((string) ($row['status'] ?? '') === 'draft') {
                $expenseDraft += $amount;
            }
        }

        return [
            'shipments' => count($shipmentRows),
            'delivered' => $delivered,
            'pending' => $pending,
            'active_trips' => $activeTrips,
            'expenses_count' => count($expenseRows),
            'expenses_total' => round($expenseTotal, 2),
            'expenses_posted' => round($expensePosted, 2),
            'expenses_draft' => round($expenseDraft, 2),
        ];
    }

    /** @return array{key:string,title:string,columns:list<string>,rows:list<array<string,mixed>>,summary:array<string,mixed>} */
    private function shipmentReport(int $companyId): array
    {
        $rows = [];
        $byStatus = [];
        foreach ($this->shipments->listForCompany($companyId, 1000, 0) as $row) {
            $status = (string) ($row['status'] ?? '');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $rows[] = [
                'tracking_number' => (string) ($row['tracking_number'] ?? ''),
                'status' => $status,
                'pickup_location' => (string) ($row['pickup_location'] ?? ''),
                'delivery_location' => (string) ($row['delivery_location'] ?? ''),
                'trip_id' => (int) ($row['trip_id'] ?? 0),
                'delivered_at' => (string) ($row['delivered_at'] ?? ''),
            ];
        }

        return [
            'key' => self::REPORT_SHIPMENT,
            'title' => __('logistics_report_shipments'),
            'columns' => ['tracking_number', 'status', 'pickup_location', 'delivery_location', 'trip_id', 'delivered_at'],
            'rows' => $rows,
            'summary' => ['total' => count($rows), 'by_status' => $byStatus],
        ];
    }

    /** @return array{key:string,title:string,columns:list<string>,rows:list<array<string,mixed>>,summary:array<string,mixed>} */
    private function tripReport(int $companyId): array
    {
        $rows = [];
        $byStatus = [];
        foreach ($this->trips->listForCompany($companyId, 1000, 0) as $row) {
            $status = (string) ($row['status'] ?? '');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'status' => $status,
                'driver_id' => (int) ($row['driver_id'] ?? 0),
                'vehicle_id' => (int) ($row['vehicle_id'] ?? 0),
                'origin' => (string) ($row['origin'] ?? ''),
                'destination' => (string) ($row['destination'] ?? ''),
                'planned_date' => (string) ($row['planned_date'] ?? ''),
            ];
        }

        return [
            'key' => self::REPORT_TRIP,
            'title' => __('logistics_report_trips'),
            'columns' => ['id', 'status', 'driver_id', 'vehicle_id', 'origin', 'destination', 'planned_date'],
            'rows' => $rows,
            'summary' => ['total' => count($rows), 'by_status' => $byStatus],
        ];
    }

    /** @return array{key:string,title:string,columns:list<string>,rows:list<array<string,mixed>>,summary:array<string,mixed>} */
    private function driverPerformanceReport(int $companyId): array
    {
        $drivers = [];
        foreach ($this->drivers->listForCompany($companyId, 500, 0) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $drivers[$id] = [
                'driver_id' => $id,
                'employee_id' => (int) ($row['employee_id'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'trips_total' => 0,
                'trips_completed' => 0,
                'trips_active' => 0,
            ];
        }
        foreach ($this->trips->listForCompany($companyId, 1000, 0) as $trip) {
            $driverId = (int) ($trip['driver_id'] ?? 0);
            if ($driverId < 1 || !isset($drivers[$driverId])) {
                continue;
            }
            $drivers[$driverId]['trips_total']++;
            $status = (string) ($trip['status'] ?? '');
            if ($status === 'completed') {
                $drivers[$driverId]['trips_completed']++;
            } elseif (in_array($status, ['assigned', 'started'], true)) {
                $drivers[$driverId]['trips_active']++;
            }
        }

        $rows = array_values($drivers);

        return [
            'key' => self::REPORT_DRIVER,
            'title' => __('logistics_report_driver_performance'),
            'columns' => ['driver_id', 'employee_id', 'status', 'trips_total', 'trips_completed', 'trips_active'],
            'rows' => $rows,
            'summary' => ['drivers' => count($rows)],
        ];
    }

    /** @return array{key:string,title:string,columns:list<string>,rows:list<array<string,mixed>>,summary:array<string,mixed>} */
    private function vehicleUsageReport(int $companyId): array
    {
        $vehicles = [];
        foreach ($this->vehicles->listForCompany($companyId, 500, 0) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $vehicles[$id] = [
                'vehicle_id' => $id,
                'plate_number' => (string) ($row['plate_number'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'trips_total' => 0,
                'trips_completed' => 0,
                'trips_active' => 0,
            ];
        }
        foreach ($this->trips->listForCompany($companyId, 1000, 0) as $trip) {
            $vehicleId = (int) ($trip['vehicle_id'] ?? 0);
            if ($vehicleId < 1 || !isset($vehicles[$vehicleId])) {
                continue;
            }
            $vehicles[$vehicleId]['trips_total']++;
            $status = (string) ($trip['status'] ?? '');
            if ($status === 'completed') {
                $vehicles[$vehicleId]['trips_completed']++;
            } elseif (in_array($status, ['assigned', 'started'], true)) {
                $vehicles[$vehicleId]['trips_active']++;
            }
        }

        $rows = array_values($vehicles);

        return [
            'key' => self::REPORT_VEHICLE,
            'title' => __('logistics_report_vehicle_usage'),
            'columns' => ['vehicle_id', 'plate_number', 'status', 'trips_total', 'trips_completed', 'trips_active'],
            'rows' => $rows,
            'summary' => ['vehicles' => count($rows)],
        ];
    }

    /** @return array{key:string,title:string,columns:list<string>,rows:list<array<string,mixed>>,summary:array<string,mixed>} */
    private function costReport(int $companyId): array
    {
        $rows = [];
        $byType = [];
        $total = 0.0;
        $posted = 0.0;
        foreach ($this->expenses->listForCompany($companyId, 1000, 0) as $row) {
            $type = (string) ($row['expense_type'] ?? 'other');
            $amount = (float) ($row['amount'] ?? 0);
            $status = (string) ($row['status'] ?? '');
            $byType[$type] = ($byType[$type] ?? 0.0) + $amount;
            $total += $amount;
            if ($status === 'posted') {
                $posted += $amount;
            }
            $rows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'expense_type' => $type,
                'amount' => $amount,
                'currency' => (string) ($row['currency'] ?? 'SAR'),
                'expense_date' => (string) ($row['expense_date'] ?? ''),
                'status' => $status,
                'trip_id' => (int) ($row['trip_id'] ?? 0),
                'vehicle_id' => (int) ($row['vehicle_id'] ?? 0),
                'driver_id' => (int) ($row['driver_id'] ?? 0),
            ];
        }

        return [
            'key' => self::REPORT_COST,
            'title' => __('logistics_report_cost'),
            'columns' => ['id', 'expense_type', 'amount', 'currency', 'expense_date', 'status', 'trip_id', 'vehicle_id', 'driver_id'],
            'rows' => $rows,
            'summary' => [
                'total' => round($total, 2),
                'posted' => round($posted, 2),
                'by_type' => $byType,
                'count' => count($rows),
            ],
        ];
    }
}
