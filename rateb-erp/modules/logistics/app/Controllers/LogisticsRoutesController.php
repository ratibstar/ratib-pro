<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Logistics\Services\LogisticsFormLookupService;
use Rateb\App\Logistics\Services\RouteService;

final class LogisticsRoutesController extends LogisticsBaseController
{
    private const RESOURCE = 'logistics/routes';

    private RouteService $service;

    public function __construct()
    {
        $this->service = new RouteService();
    }

    public function index(): void
    {
        $this->bootstrapLogistics();
        $this->guardView(self::RESOURCE);
        $canManage = function_exists('rateb_can_manage_entity') && rateb_can_manage_entity(self::RESOURCE);
        $this->logisticsView('routes/index', [
            'title' => __('logistics_routes'),
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
        $this->logisticsView('routes/form', $this->formData([]));
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
        $this->logisticsView('routes/form', $this->formData($item));
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
            ['name' => 'code', 'label' => 'code'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'origin', 'label' => 'logistics_origin'],
            ['name' => 'destination', 'label' => 'logistics_destination'],
            ['name' => 'distance_km', 'label' => 'logistics_distance_km'],
            ['name' => 'status', 'label' => 'status'],
        ];
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function formData(array $item): array
    {
        $fields = [
            ['name' => 'code', 'label' => 'code', 'type' => 'text', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'name_ar', 'label' => 'name_ar', 'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'origin', 'label' => 'logistics_origin', 'type' => 'text', 'col' => 'col-md-6'],
            ['name' => 'destination', 'label' => 'logistics_destination', 'type' => 'text', 'col' => 'col-md-6'],
            ['name' => 'distance_km', 'label' => 'logistics_distance_km', 'type' => 'number', 'col' => 'col-md-4'],
            ['name' => 'estimated_minutes', 'label' => 'logistics_estimated_minutes', 'type' => 'number', 'col' => 'col-md-4'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'logistics_route_statuses', 'col' => 'col-md-4'],
            ['name' => 'branch_id', 'label' => 'branches', 'type' => 'fk', 'lookup' => 'branches', 'col' => 'col-md-6'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-md-12'],
        ];

        return [
            'title' => __('logistics_routes'),
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
            'code' => (string) $this->input('code', ''),
            'name' => (string) $this->input('name', ''),
            'name_ar' => (string) $this->input('name_ar', ''),
            'origin' => (string) $this->input('origin', ''),
            'destination' => (string) $this->input('destination', ''),
            'distance_km' => $this->input('distance_km', ''),
            'estimated_minutes' => $this->input('estimated_minutes', ''),
            'status' => (string) $this->input('status', 'active'),
            'branch_id' => (int) $this->input('branch_id', 0),
            'notes' => (string) $this->input('notes', ''),
        ];
    }
}
