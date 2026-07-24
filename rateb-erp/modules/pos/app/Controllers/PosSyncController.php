<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Pos\Services\PosOfflineSyncService;

final class PosSyncController extends PosBaseController
{
    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/sync');
        $companyId = $this->companyId();
        $service = new PosOfflineSyncService();
        $cid = $companyId > 0 ? $companyId : null;
        $this->posView('sync/index', [
            'title' => __('pos_sync'),
            'status' => $service->status($cid),
            'items' => $service->recentQueue(50, $cid),
            'conflicts' => $service->openConflicts(50, $cid),
            'csrf' => Csrf::token(),
            'syncApi' => [
                'process' => rateb_app_url('pos/api/sync/process'),
                'resolveConflict' => rateb_app_url('pos/api/sync/conflicts/{id}/resolve'),
            ],
        ]);
    }

    public function process(): void
    {
        $this->bootstrapPos();
        $this->guardPosManage('pos/sync');
        $this->requireSessionCsrfOrAbort();

        $companyId = $this->companyId();
        $service = new PosOfflineSyncService();
        $result = $service->processPending($companyId > 0 ? $companyId : null, 50);

        SessionManager::flash('success', __('pos_sync_process_done', [
            'synced' => (string) ($result['synced'] ?? 0),
            'failed' => (string) ($result['failed'] ?? 0),
        ]));
        $this->redirect($this->syncRedirectUrl());
    }

    public function resolveConflict(): void
    {
        $this->bootstrapPos();
        $this->guardPosManage('pos/sync');
        $this->requireSessionCsrfOrAbort();

        $conflictId = (int) ($_POST['conflict_id'] ?? 0);
        $resolution = trim((string) ($_POST['resolution'] ?? ''));
        $companyId = $this->companyId();
        $service = new PosOfflineSyncService();
        $result = $service->resolveConflict(
            $conflictId,
            $resolution,
            $this->userId(),
            $companyId > 0 ? $companyId : null
        );

        if (!empty($result['ok'])) {
            SessionManager::flash('success', __('pos_sync_conflict_resolved'));
        } else {
            SessionManager::flash('error', (string) ($result['error'] ?? __('invalid_request')));
        }

        $this->redirect($this->syncRedirectUrl());
    }

    private function syncRedirectUrl(): string
    {
        $url = rateb_app_url('pos/sync');
        $companyId = (int) ($_GET['company_id'] ?? $_POST['company_id'] ?? 0);
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }
        if ($companyId > 0) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'company_id=' . $companyId;
        }

        return $url;
    }
}
