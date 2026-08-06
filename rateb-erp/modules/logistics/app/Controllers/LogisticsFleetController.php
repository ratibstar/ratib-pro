<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Logistics\Services\FleetService;
use Rateb\App\Logistics\Services\LogisticsFormLookupService;

final class LogisticsFleetController extends LogisticsBaseController
{
    private const RESOURCE = 'logistics/vehicles';

    private FleetService $service;

    public function __construct()
    {
        $this->service = new FleetService();
    }

    public function index(): void
    {
        $this->bootstrapLogistics();
        $this->guardView(self::RESOURCE);
        $canManage = function_exists('rateb_can_manage_entity') && rateb_can_manage_entity(self::RESOURCE);
        $this->logisticsView('vehicles/index', [
            'title' => __('logistics_fleet'),
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
        $this->logisticsView('vehicles/form', $this->formData([]));
    }

    public function edit(array $params): void
    {
        $this->bootstrapLogistics();
        $this->guardManage(self::RESOURCE);
        $id = (int) ($params['id'] ?? 0);
        $item = $this->service->find($id, $this->companyId());
        if (!$item) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_app_url(self::RESOURCE));
        }
        $this->logisticsView('vehicles/form', $this->formData($item));
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
            ['name' => 'plate_number', 'label' => 'logistics_plate_number'],
            ['name' => 'vehicle_type', 'label' => 'logistics_vehicle_type'],
            ['name' => 'brand', 'label' => 'brand'],
            ['name' => 'model', 'label' => 'model'],
            ['name' => 'year', 'label' => 'year'],
            ['name' => 'status', 'label' => 'status'],
        ];
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function formData(array $item): array
    {
        $fields = [
            ['name' => 'plate_number', 'label' => 'logistics_plate_number', 'type' => 'text', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'vehicle_type', 'label' => 'logistics_vehicle_type', 'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'logistics_vehicle_statuses', 'col' => 'col-md-4'],
            ['name' => 'brand', 'label' => 'brand', 'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'model', 'label' => 'model', 'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'year', 'label' => 'year', 'type' => 'number', 'col' => 'col-md-2'],
            ['name' => 'capacity', 'label' => 'logistics_capacity', 'type' => 'number', 'col' => 'col-md-2'],
            ['name' => 'current_driver_id', 'label' => 'logistics_drivers', 'type' => 'fk', 'lookup' => 'logistics_drivers', 'col' => 'col-md-6'],
            ['name' => 'branch_id', 'label' => 'branches', 'type' => 'fk', 'lookup' => 'branches', 'col' => 'col-md-6'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-md-12'],
        ];

        return [
            'title' => __('logistics_fleet'),
            'item' => $item,
            'fields' => $fields,
            'lookups' => (new LogisticsFormLookupService())->forFields($fields, $this->companyId()),
            'routePrefix' => rateb_app_route(self::RESOURCE),
            'csrf' => Csrf::token(),
        ];
    }

    /** @return array<string, mixed> */
    private function collect(): array
    {
        return [
            'plate_number' => (string) $this->input('plate_number', ''),
            'vehicle_type' => (string) $this->input('vehicle_type', ''),
            'brand' => (string) $this->input('brand', ''),
            'model' => (string) $this->input('model', ''),
            'year' => $this->input('year', ''),
            'capacity' => $this->input('capacity', ''),
            'status' => (string) $this->input('status', 'available'),
            'current_driver_id' => (int) $this->input('current_driver_id', 0),
            'branch_id' => (int) $this->input('branch_id', 0),
            'notes' => (string) $this->input('notes', ''),
        ];
    }
}
