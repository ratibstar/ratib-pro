<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Logistics\Services\LogisticsFormLookupService;
use Rateb\App\Logistics\Services\LogisticsStatusService;
use Rateb\App\Logistics\Services\TripService;
use Rateb\App\Logistics\Policies\LogisticsStatusPolicy;

final class LogisticsTripsController extends LogisticsBaseController
{
    private const RESOURCE = 'logistics/trips';

    private TripService $service;

    public function __construct()
    {
        $this->service = new TripService();
    }

    public function index(): void
    {
        $this->bootstrapLogistics();
        $this->guardView(self::RESOURCE);
        $canManage = function_exists('rateb_can_manage_entity') && rateb_can_manage_entity(self::RESOURCE);
        $this->logisticsView('trips/index', [
            'title' => __('logistics_trips'),
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
        $this->logisticsView('trips/form', $this->formData([]));
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
        $data['history'] = (new LogisticsStatusService())->history(
            $companyId,
            LogisticsStatusPolicy::ENTITY_TRIP,
            $id
        );
        $this->logisticsView('trips/form', $data);
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
        try {
            $this->service->transition(
                $id,
                $this->companyId(),
                (string) $this->input('to_status', ''),
                (string) $this->input('reason', '')
            );
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
            ['name' => 'planned_date', 'label' => 'logistics_planned_date'],
            ['name' => 'origin', 'label' => 'logistics_origin'],
            ['name' => 'destination', 'label' => 'logistics_destination'],
            ['name' => 'driver_id', 'label' => 'logistics_drivers'],
            ['name' => 'vehicle_id', 'label' => 'logistics_fleet'],
            ['name' => 'status', 'label' => 'status'],
        ];
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function formData(array $item): array
    {
        $fields = [
            ['name' => 'planned_date', 'label' => 'logistics_planned_date', 'type' => 'date', 'col' => 'col-md-4'],
            ['name' => 'driver_id', 'label' => 'logistics_drivers', 'type' => 'fk', 'lookup' => 'logistics_drivers', 'col' => 'col-md-4'],
            ['name' => 'vehicle_id', 'label' => 'logistics_fleet', 'type' => 'fk', 'lookup' => 'logistics_vehicles', 'col' => 'col-md-4'],
            ['name' => 'route_id', 'label' => 'logistics_routes', 'type' => 'fk', 'lookup' => 'logistics_routes', 'col' => 'col-md-4'],
            ['name' => 'origin', 'label' => 'logistics_origin', 'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'destination', 'label' => 'logistics_destination', 'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'branch_id', 'label' => 'branches', 'type' => 'fk', 'lookup' => 'branches', 'col' => 'col-md-6'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-md-12'],
        ];

        return [
            'title' => __('logistics_trips'),
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
            'driver_id' => (int) $this->input('driver_id', 0),
            'vehicle_id' => (int) $this->input('vehicle_id', 0),
            'route_id' => (int) $this->input('route_id', 0),
            'origin' => (string) $this->input('origin', ''),
            'destination' => (string) $this->input('destination', ''),
            'planned_date' => (string) $this->input('planned_date', ''),
            'branch_id' => (int) $this->input('branch_id', 0),
            'notes' => (string) $this->input('notes', ''),
        ];
    }
}
