<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Pos\Services\PosOfflineDeviceService;

/** Admin UI: list / activate / revoke offline POS devices (company-scoped). */
final class PosDevicesController extends PosBaseController
{
    private const RESOURCE = 'pos/devices';

    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardDevicesAccess();

        $companyId = $this->companyId();
        $branchId = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
        $status = trim((string) ($_GET['status'] ?? ''));
        $service = new PosOfflineDeviceService();

        $this->posView('devices/index', [
            'title' => __('pos_devices'),
            'items' => $service->listForCompany(
                $companyId,
                $branchId > 0 ? $branchId : null,
                $status !== '' ? $status : null
            ),
            'branchOptions' => $service->branchFilterOptions($companyId),
            'filterBranchId' => $branchId,
            'filterStatus' => $status,
            'csrf' => Csrf::token(),
            'activateUrl' => rateb_app_url(self::RESOURCE . '/activate'),
            'revokeUrl' => rateb_app_url(self::RESOURCE . '/revoke'),
            'indexUrl' => rateb_app_url(self::RESOURCE),
            'canManage' => $this->canManageDevices(),
            'flashSuccess' => SessionManager::flash('success'),
            'flashError' => SessionManager::flash('error'),
        ]);
    }

    public function activate(): void
    {
        $this->bootstrapPos();
        $this->guardDevicesAccess();
        if (!$this->canManageDevices()) {
            $this->denyAccess(self::RESOURCE);
        }
        if (!$this->validateCsrfPost()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url(self::RESOURCE));
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $result = (new PosOfflineDeviceService())->activate($id, $this->companyId(), $this->userId());
        if (!empty($result['ok'])) {
            SessionManager::flash('success', __('pos_device_activated'));
        } else {
            SessionManager::flash('error', (string) ($result['error'] ?? __('invalid_request')));
        }
        $this->redirect($this->backUrl());
    }

    public function revoke(): void
    {
        $this->bootstrapPos();
        $this->guardDevicesAccess();
        if (!$this->canManageDevices()) {
            $this->denyAccess(self::RESOURCE);
        }
        if (!$this->validateCsrfPost()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url(self::RESOURCE));
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $result = (new PosOfflineDeviceService())->revoke($id, $this->companyId(), $this->userId());
        if (!empty($result['ok'])) {
            SessionManager::flash('success', __('pos_device_revoked'));
        } else {
            SessionManager::flash('error', (string) ($result['error'] ?? __('invalid_request')));
        }
        $this->redirect($this->backUrl());
    }

    private function guardDevicesAccess(): void
    {
        if ($this->canManageDevices()) {
            return;
        }
        $this->denyAccess(self::RESOURCE);
    }

    private function canManageDevices(): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }
        if (!function_exists('rateb_can')) {
            return false;
        }

        return rateb_can('pos.devices.manage') || rateb_can('pos.settings.manage');
    }

    private function validateCsrfPost(): bool
    {
        return Csrf::validate($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    }

    private function backUrl(): string
    {
        $qs = [];
        $branchId = (int) ($_POST['branch_id'] ?? $_GET['branch_id'] ?? 0);
        $status = trim((string) ($_POST['status'] ?? $_GET['status'] ?? ''));
        if ($branchId > 0) {
            $qs['branch_id'] = $branchId;
        }
        if ($status !== '') {
            $qs['status'] = $status;
        }
        $base = rateb_app_url(self::RESOURCE);
        if ($qs === []) {
            return $base;
        }

        return $base . (str_contains($base, '?') ? '&' : '?') . http_build_query($qs);
    }
}
