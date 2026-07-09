<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Pos\Services\PosCashDrawerService;

final class PosCashDrawersController extends PosBaseController
{
    private const RESOURCE = 'pos/cash-drawers';

    private PosCashDrawerService $service;

    public function __construct()
    {
        $this->service = new PosCashDrawerService();
    }

    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView(self::RESOURCE);
        $this->posView('cash-drawers/index', [
            'title' => __('pos_cash_drawers'),
            'items' => $this->service->listForCompany($this->companyId()),
            'fields' => $this->indexFields(),
            'routePrefix' => rateb_app_route(self::RESOURCE),
            'csrf' => Csrf::token(),
            'permissionResource' => self::RESOURCE,
            'createEnabled' => false,
            'actionsEnabled' => false,
            'viewEnabled' => true,
            'bulkEnabled' => false,
            'exportEnabled' => false,
        ], 'pos-pages-shell');
    }

    public function show(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosView(self::RESOURCE);
        $id = (int) ($params['id'] ?? 0);
        $drawer = $this->service->find($id, $this->companyId());
        if (!$drawer) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_app_url(self::RESOURCE));
        }
        $canManage = function_exists('rateb_can') && (rateb_can('pos.cash_drawer.manage') || rateb_is_super_admin());
        $this->posView('cash-drawers/show', [
            'title' => __('pos_cash_drawers'),
            'drawer' => $drawer,
            'events' => $this->service->eventsForDrawer($id, $this->companyId()),
            'csrf' => Csrf::token(),
            'canManage' => $canManage && ($drawer['status'] ?? '') === 'open',
        ], 'pos-pages-shell');
    }

    public function storeEvent(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosPermission('pos.cash_drawer.manage', self::RESOURCE);
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url(self::RESOURCE));
        }
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->service->recordManualEvent(
                $id,
                $this->companyId(),
                $this->userId(),
                (string) $this->input('event_type', ''),
                (float) $this->input('amount', 0),
                trim((string) $this->input('notes', ''))
            );
            SessionManager::flash('success', __('saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url('pos/cash-drawers/' . $id));
    }

    /** @return array<int, array<string, mixed>> */
    private function indexFields(): array
    {
        return [
            ['name' => 'terminal_id', 'label' => 'pos_terminals', 'type' => 'fk', 'lookup' => 'pos_terminals'],
            ['name' => 'shift_id', 'label' => 'pos_shifts', 'type' => 'id'],
            ['name' => 'status', 'label' => 'status'],
            ['name' => 'expected_balance', 'label' => 'pos_expected_balance'],
            ['name' => 'counted_balance', 'label' => 'pos_counted_balance'],
            ['name' => 'variance', 'label' => 'pos_variance'],
        ];
    }
}
