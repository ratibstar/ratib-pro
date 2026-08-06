<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services;

use Rateb\App\Services\FormLookupService;

/** Extends core FormLookupService with logistics-specific option lists (no Core edits). */
final class LogisticsFormLookupService
{
    public function __construct(
        private FormLookupService $core = new FormLookupService(),
        private FleetService $fleet = new FleetService(),
        private DriverService $drivers = new DriverService(),
        private RouteService $routes = new RouteService(),
        private TripService $trips = new TripService(),
        private DeliveryOrderService $deliveryOrders = new DeliveryOrderService(),
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<string, list<array{value: string|int, label: string}>>
     */
    public function forFields(array $fields, int $companyId = 0): array
    {
        $lookups = $this->core->forFields($fields);
        $needed = [];
        foreach ($fields as $field) {
            $key = (string) ($field['lookup'] ?? '');
            if ($key !== '') {
                $needed[$key] = true;
            }
        }

        if (isset($needed['logistics_vehicle_statuses'])) {
            $lookups['logistics_vehicle_statuses'] = $this->statusOptions([
                'available', 'assigned', 'maintenance', 'inactive',
            ]);
        }
        if (isset($needed['logistics_driver_statuses'])) {
            $lookups['logistics_driver_statuses'] = $this->statusOptions([
                'active', 'inactive', 'suspended',
            ]);
        }
        if (isset($needed['logistics_trip_statuses'])) {
            $lookups['logistics_trip_statuses'] = $this->statusOptions([
                'draft', 'assigned', 'started', 'completed', 'cancelled',
            ]);
        }
        if (isset($needed['logistics_shipment_statuses'])) {
            $lookups['logistics_shipment_statuses'] = $this->statusOptions([
                'created', 'picked', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'failed',
            ]);
        }
        if (isset($needed['logistics_vehicles']) && $companyId > 0) {
            $lookups['logistics_vehicles'] = $this->fleet->options($companyId);
        }
        if (isset($needed['logistics_drivers']) && $companyId > 0) {
            $lookups['logistics_drivers'] = $this->drivers->options($companyId, false);
        }
        if (isset($needed['logistics_routes']) && $companyId > 0) {
            $lookups['logistics_routes'] = $this->routes->options($companyId);
        }
        if (isset($needed['logistics_trips']) && $companyId > 0) {
            $lookups['logistics_trips'] = $this->trips->options($companyId);
        }
        if (isset($needed['logistics_delivery_orders']) && $companyId > 0) {
            $lookups['logistics_delivery_orders'] = $this->deliveryOrders->options($companyId);
        }

        return $lookups;
    }

    /**
     * @param list<string> $statuses
     * @return list<array{value:string,label:string}>
     */
    private function statusOptions(array $statuses): array
    {
        $out = [];
        foreach ($statuses as $status) {
            $key = 'logistics_status_' . $status;
            $label = __($key);
            if ($label === $key) {
                $label = $status;
            }
            $out[] = ['value' => $status, 'label' => $label];
        }

        return $out;
    }
}
