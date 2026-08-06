<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Logistics\Services\LogisticsExpenseService;
use Rateb\App\Logistics\Services\LogisticsFormLookupService;

final class LogisticsExpensesController extends LogisticsBaseController
{
    private const RESOURCE = 'logistics/expenses';

    private LogisticsExpenseService $service;

    public function __construct()
    {
        $this->service = new LogisticsExpenseService();
    }

    public function index(): void
    {
        $this->bootstrapLogistics();
        $this->guardView(self::RESOURCE);
        $canManage = function_exists('rateb_can_manage_entity') && rateb_can_manage_entity(self::RESOURCE);
        $this->logisticsView('expenses/index', [
            'title' => __('logistics_expenses'),
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
        $this->logisticsView('expenses/form', $this->formData([]));
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
        $this->logisticsView('expenses/form', $this->formData($item));
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

    public function post(array $params): void
    {
        $this->bootstrapLogistics();
        $this->guardManage(self::RESOURCE);
        $id = (int) ($params['id'] ?? 0);
        $this->requireCsrf(rateb_app_url(self::RESOURCE . '/' . $id . '/edit'));
        try {
            $this->service->post($id, $this->companyId());
            SessionManager::flash('success', __('logistics_expense_posted'));
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
            ['name' => 'expense_type', 'label' => 'logistics_expense_type'],
            ['name' => 'amount', 'label' => 'amount'],
            ['name' => 'currency', 'label' => 'currency'],
            ['name' => 'expense_date', 'label' => 'date'],
            ['name' => 'status', 'label' => 'status'],
            ['name' => 'journal_entry_id', 'label' => 'logistics_journal_entry'],
        ];
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function formData(array $item): array
    {
        $fields = [
            ['name' => 'expense_type', 'label' => 'logistics_expense_type', 'type' => 'select', 'lookup' => 'logistics_expense_types', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'amount', 'label' => 'amount', 'type' => 'number', 'required' => true, 'col' => 'col-md-4'],
            ['name' => 'currency', 'label' => 'currency', 'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'expense_date', 'label' => 'date', 'type' => 'date', 'col' => 'col-md-4'],
            ['name' => 'trip_id', 'label' => 'logistics_trips', 'type' => 'fk', 'lookup' => 'logistics_trips', 'col' => 'col-md-4'],
            ['name' => 'vehicle_id', 'label' => 'logistics_fleet', 'type' => 'fk', 'lookup' => 'logistics_vehicles', 'col' => 'col-md-4'],
            ['name' => 'driver_id', 'label' => 'logistics_drivers', 'type' => 'fk', 'lookup' => 'logistics_drivers', 'col' => 'col-md-4'],
            ['name' => 'branch_id', 'label' => 'branches', 'type' => 'fk', 'lookup' => 'branches', 'col' => 'col-md-4'],
            ['name' => 'description', 'label' => 'description', 'type' => 'textarea', 'col' => 'col-md-12'],
        ];

        return [
            'title' => __('logistics_expenses'),
            'item' => $item,
            'fields' => $fields,
            'lookups' => (new LogisticsFormLookupService())->forFields($fields, $this->companyId()),
            'routePrefix' => rateb_app_route(self::RESOURCE),
            'csrf' => Csrf::token(),
            'canPost' => (string) ($item['status'] ?? '') === 'draft' && (int) ($item['id'] ?? 0) > 0,
            'isLocked' => (string) ($item['status'] ?? '') === 'posted',
        ];
    }

    /** @return array<string, mixed> */
    private function collect(): array
    {
        return [
            'expense_type' => (string) $this->input('expense_type', 'other'),
            'amount' => $this->input('amount', 0),
            'currency' => (string) $this->input('currency', 'SAR'),
            'expense_date' => (string) $this->input('expense_date', date('Y-m-d')),
            'trip_id' => (int) $this->input('trip_id', 0),
            'vehicle_id' => (int) $this->input('vehicle_id', 0),
            'driver_id' => (int) $this->input('driver_id', 0),
            'branch_id' => (int) $this->input('branch_id', 0),
            'description' => (string) $this->input('description', ''),
        ];
    }
}
