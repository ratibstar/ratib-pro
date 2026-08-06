<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Logistics\Services\DriverService;
use Rateb\App\Logistics\Services\LogisticsFormLookupService;

final class LogisticsDriversController extends LogisticsBaseController
{
    private const RESOURCE = 'logistics/drivers';

    private DriverService $service;

    public function __construct()
    {
        $this->service = new DriverService();
    }

    public function index(): void
    {
        $this->bootstrapLogistics();
        $this->guardView(self::RESOURCE);
        $canManage = function_exists('rateb_can_manage_entity') && rateb_can_manage_entity(self::RESOURCE);
        $this->logisticsView('drivers/index', [
            'title' => __('logistics_drivers'),
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
        $this->logisticsView('drivers/form', $this->formData([]));
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
        $this->logisticsView('drivers/form', $this->formData($item));
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
            ['name' => 'employee_name', 'label' => 'employee'],
            ['name' => 'license_number', 'label' => 'logistics_license_number'],
            ['name' => 'license_type', 'label' => 'logistics_license_type'],
            ['name' => 'license_expiry', 'label' => 'logistics_license_expiry'],
            ['name' => 'status', 'label' => 'status'],
        ];
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function formData(array $item): array
    {
        $fields = [
            ['name' => 'employee_id', 'label' => 'employee', 'type' => 'fk', 'lookup' => 'employees', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'logistics_driver_statuses', 'col' => 'col-md-6'],
            ['name' => 'license_number', 'label' => 'logistics_license_number', 'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'license_type', 'label' => 'logistics_license_type', 'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'license_expiry', 'label' => 'logistics_license_expiry', 'type' => 'date', 'col' => 'col-md-4'],
            ['name' => 'branch_id', 'label' => 'branches', 'type' => 'fk', 'lookup' => 'branches', 'col' => 'col-md-6'],
            ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-md-12'],
        ];

        return [
            'title' => __('logistics_drivers'),
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
            'employee_id' => (int) $this->input('employee_id', 0),
            'license_number' => (string) $this->input('license_number', ''),
            'license_type' => (string) $this->input('license_type', ''),
            'license_expiry' => (string) $this->input('license_expiry', ''),
            'status' => (string) $this->input('status', 'active'),
            'branch_id' => (int) $this->input('branch_id', 0),
            'notes' => (string) $this->input('notes', ''),
        ];
    }
}
