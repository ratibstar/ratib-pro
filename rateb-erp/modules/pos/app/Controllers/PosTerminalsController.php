<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Pos\Services\PosFormLookupService;
use Rateb\App\Pos\Services\PosTerminalService;

final class PosTerminalsController extends PosBaseController
{
    private const RESOURCE = 'pos/terminals';

    private PosTerminalService $service;

    public function __construct()
    {
        $this->service = new PosTerminalService();
    }

    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView(self::RESOURCE);
        $companyId = $this->companyId();
        $canManage = function_exists('rateb_can_manage_entity') && rateb_can_manage_entity(self::RESOURCE);
        $this->posView('terminals/index', [
            'title' => __('pos_terminals'),
            'items' => $this->service->listForCompany($companyId),
            'fields' => $this->indexFields(),
            'routePrefix' => rateb_app_route(self::RESOURCE),
            'csrf' => Csrf::token(),
            'permissionResource' => self::RESOURCE,
            'createEnabled' => $canManage,
            'actionsEnabled' => $canManage,
            'bulkEnabled' => false,
            'exportEnabled' => false,
        ], 'pos-pages-shell');
    }

    public function create(): void
    {
        $this->bootstrapPos();
        $this->guardPosManage(self::RESOURCE);
        $this->posView('terminals/form', $this->formData([]));
    }

    public function edit(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosManage(self::RESOURCE);
        $id = (int) ($params['id'] ?? 0);
        $item = $this->service->find($id, $this->companyId());
        if (!$item) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_app_url(self::RESOURCE));
        }
        $this->posView('terminals/form', $this->formData($item));
    }

    public function store(): void
    {
        $this->bootstrapPos();
        $this->guardPosManage(self::RESOURCE);
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url(self::RESOURCE . '/create'));
        }
        try {
            $id = $this->service->create($this->companyId(), $this->collectTerminalData());
            SessionManager::flash('success', __('saved'));
            $this->redirect(rateb_app_url(self::RESOURCE . '/' . $id . '/edit'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_app_url(self::RESOURCE . '/create'));
        }
    }

    public function update(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosManage(self::RESOURCE);
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url(self::RESOURCE));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->service->update($id, $this->companyId(), $this->collectTerminalData());
            SessionManager::flash('success', __('saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url(self::RESOURCE . '/' . $id . '/edit'));
    }

    public function destroy(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosManage(self::RESOURCE);
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url(self::RESOURCE));
        }
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
            ['name' => 'branch_id', 'label' => 'branches', 'type' => 'fk', 'lookup' => 'branches'],
            ['name' => 'warehouse_id', 'label' => 'warehouses', 'type' => 'fk', 'lookup' => 'warehouses'],
            ['name' => 'status', 'label' => 'status'],
        ];
    }

    /** @param array<string, mixed> $item @return array<string, mixed> */
    private function formData(array $item): array
    {
        $fields = [
            ['name' => 'code', 'label' => 'code', 'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true, 'col' => 'col-md-8'],
            ['name' => 'branch_id', 'label' => 'branches', 'type' => 'fk', 'lookup' => 'branches', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'warehouse_id', 'label' => 'warehouses', 'type' => 'fk', 'lookup' => 'warehouses', 'col' => 'col-md-6'],
            ['name' => 'status', 'label' => 'status', 'type' => 'select', 'lookup' => 'warehouse_statuses', 'col' => 'col-md-4'],
        ];
        $lookups = (new PosFormLookupService())->forFields($fields);
        return [
            'title' => __('pos_terminals'),
            'item' => $item,
            'fields' => $fields,
            'lookups' => $lookups,
            'routePrefix' => rateb_app_route(self::RESOURCE),
            'csrf' => Csrf::token(),
        ];
    }

    /** @return array<string, mixed> */
    private function collectTerminalData(): array
    {
        return [
            'code' => trim((string) $this->input('code', '')),
            'name' => trim((string) $this->input('name', '')),
            'branch_id' => (int) $this->input('branch_id', 0),
            'warehouse_id' => (int) $this->input('warehouse_id', 0) ?: null,
            'status' => (string) $this->input('status', 'active'),
        ];
    }
}
