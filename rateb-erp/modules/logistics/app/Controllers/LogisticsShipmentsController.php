<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Logistics\Services\LogisticsFormLookupService;
use Rateb\App\Logistics\Services\ShipmentService;

final class LogisticsShipmentsController extends LogisticsBaseController
{
    private const RESOURCE = 'logistics/shipments';

    private ShipmentService $service;

    public function __construct()
    {
        $this->service = new ShipmentService();
    }

    public function index(): void
    {
        $this->bootstrapLogistics();
        $this->guardView(self::RESOURCE);
        $canManage = function_exists('rateb_can_manage_entity') && rateb_can_manage_entity(self::RESOURCE);
        $this->logisticsView('shipments/index', [
            'title' => __('logistics_shipments'),
            'items' => $this->service->listForCompany($this->companyId()),
            'fields' => $this->indexFields(),
            'routePrefix' => rateb_app_route(self::RESOURCE),
            'csrf' => Csrf::token(),
            'permissionResource' => self::RESOURCE,
            'createEnabled' => $canManage,
            'actionsEnabled' => $canManage,
            'bulkEnabled' => false,
            'exportEnabled' => false,
        ]);
    }

    public function create(): void
    {
        $this->bootstrapLogistics();
        $this->guardManage(self::RESOURCE);
        $this->logisticsView('shipments/form', $this->formData([]));
    }

    public function edit(array $params): void
    {
        $this->bootstrapLogistics();
        $this->guardManage(self::RESOURCE);
        $id = (int) ($params['id'] ?? 0);
        $companyId = $this->companyId();
        $item = $this->service->find($id, $companyId);
        if (!$item) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_app_url(self::RESOURCE));
        }
        $data = $this->formData($item);
        $data['nextStatuses'] = $this->service->nextStatuses($id, $companyId);
        $data['history'] = $this->service->history($id, $companyId);
        $this->logisticsView('shipments/form', $data);
    }

    public function store(): void
    {
        $this->bootstrapLogistics();
        $this->guardManage(self::RESOURCE);
        $this->requireCsrf(rateb_app_url(self::RESOURCE . '/create'));
        try {
            $id = $this->service->create($this->companyId(), $this->collect());
            SessionManager::flash('success', __('saved'));
            $this->redirect(rateb_app_url(self::RESOURCE . '/' . $id . '/edit'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_app_url(self::RESOURCE . '/create'));
        }
    }

    public function update(array $params): void
    {
        $this->bootstrapLogistics();
        $this->guardManage(self::RESOURCE);
        $id = (int) ($params['id'] ?? 0);
        $this->requireCsrf(rateb_app_url(self::RESOURCE . '/' . $id . '/edit'));
        try {
            $this->service->update($id, $this->companyId(), $this->collect());
            SessionManager::flash('success', __('saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url(self::RESOURCE . '/' . $id . '/edit'));
    }

    public function transition(array $params): void
    {
        $this->bootstrapLogistics();
        $this->guardManage(self::RESOURCE);
        $id = (int) ($params['id'] ?? 0);
        $this->requireCsrf(rateb_app_url(self::RESOURCE . '/' . $id . '/edit'));
        $to = (string) $this->input('to_status', '');
        try {
            if ($to === 'delivered') {
                $this->service->deliver($id, $this->companyId(), [
                    'receiver_name' => (string) $this->input('receiver_name', ''),
                    'notes' => (string) $this->input('reason', ''),
                ], (string) $this->input('reason', ''));
            } else {
                $this->service->transition($id, $this->companyId(), $to, (string) $this->input('reason', ''));
            }
            SessionManager::flash('success', __('logistics_status_updated'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url(self::RESOURCE . '/' . $id . '/edit'));
    }

    public function destroy(array $params): void
    {
        $this->bootstrapLogistics();
        $this->guardManage(self::RESOURCE);
        $this->requireCsrf(rateb_app_url(self::RESOURCE));
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->service->delete($id, $this->companyId());
            SessionManager::flash('success', __('deleted'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url(self::RESOURCE));
    }

    /** @return array<int, array<string, mixed>> */
    private function indexFields(): array
    {
        return [
            ['name' => 'tracking_number', 'label' => 'logistics_tracking_number'],
            ['name' => 'customer_id', 'label' => 'customers', 'type' => 'fk', 'lookup' => 'customers'],
            ['name' => 'pickup_location', 'label' => 'logistics_origin'],
            ['name' => 'delivery_location', 'label' => 'logistics_destination'],
            ['name' => 'status', 'label' => 'status'],
        ];
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function formData(array $item): array
    {
        $fields = [
            ['name' => 'tracking_number', 'label' => 'logistics_tracking_number', 'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'customer_id', 'label' => 'customers', 'type' => 'fk', 'lookup' => 'customers', 'col' => 'col-md-4'],
            ['name' => 'trip_id', 'label' => 'logistics_trips', 'type' => 'fk', 'lookup' => 'logistics_trips', 'col' => 'col-md-4'],
            ['name' => 'delivery_order_id', 'label' => 'logistics_delivery_orders', 'type' => 'fk', 'lookup' => 'logistics_delivery_orders', 'col' => 'col-md-4'],
            ['name' => 'order_id', 'label' => 'logistics_order_id', 'type' => 'number', 'col' => 'col-md-4'],
            ['name' => 'branch_id', 'label' => 'branches', 'type' => 'fk', 'lookup' => 'branches', 'col' => 'col-md-4'],
            ['name' => 'pickup_location', 'label' => 'logistics_origin', 'type' => 'text', 'col' => 'col-md-6'],
            ['name' => 'delivery_location', 'label' => 'logistics_destination', 'type' => 'text', 'col' => 'col-md-6'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-md-12'],
        ];

        return [
            'title' => __('logistics_shipments'),
            'item' => $item,
            'fields' => $fields,
            'lookups' => (new LogisticsFormLookupService())->forFields($fields, $this->companyId()),
            'routePrefix' => rateb_app_route(self::RESOURCE),
            'csrf' => Csrf::token(),
            'nextStatuses' => [],
            'history' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function collect(): array
    {
        return [
            'tracking_number' => (string) $this->input('tracking_number', ''),
            'customer_id' => (int) $this->input('customer_id', 0),
            'order_id' => (int) $this->input('order_id', 0),
            'delivery_order_id' => (int) $this->input('delivery_order_id', 0),
            'trip_id' => (int) $this->input('trip_id', 0),
            'pickup_location' => (string) $this->input('pickup_location', ''),
            'delivery_location' => (string) $this->input('delivery_location', ''),
            'branch_id' => (int) $this->input('branch_id', 0),
            'notes' => (string) $this->input('notes', ''),
        ];
    }
}
