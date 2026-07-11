<?php

declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Services\DeviceTrustService;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;

/**
 * Phase P2 — company admin UI for offline device trust.
 */
final class OfflineDevicesController extends Controller
{
    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) (TenantContext::companyId() ?? 0);
        $devices = [];
        if ($companyId > 0) {
            $filters = [];
            if (isset($_GET['branch_id']) && (int) $_GET['branch_id'] > 0) {
                $filters['branch_id'] = (int) $_GET['branch_id'];
            }
            if (isset($_GET['user_id']) && (int) $_GET['user_id'] > 0) {
                $filters['user_id'] = (int) $_GET['user_id'];
            }
            if (isset($_GET['status']) && trim((string) $_GET['status']) !== '') {
                $filters['status'] = trim((string) $_GET['status']);
            }
            $devices = (new DeviceTrustService())->listDevices($companyId, $filters);
        }

        $this->view('company/security/offline-devices', [
            'title' => __('offline_devices'),
            'devices' => $devices,
            'csrf' => Csrf::token(),
            'canManage' => $this->canManage(),
            'authUnlock' => (new OfflineFeatureFlagService())->isAuthUnlockEnabled(),
            'filters' => [
                'branch_id' => (int) ($_GET['branch_id'] ?? 0),
                'user_id' => (int) ($_GET['user_id'] ?? 0),
                'status' => (string) ($_GET['status'] ?? ''),
            ],
        ]);
    }

    public function revoke(): void
    {
        $this->mutate(function (int $companyId, int $userId, array $post): array {
            return (new DeviceTrustService())->revoke(
                $companyId,
                (string) ($post['device_id'] ?? ''),
                $userId,
                isset($post['reason']) ? (string) $post['reason'] : null
            );
        });
    }

    public function rename(): void
    {
        $this->mutate(function (int $companyId, int $userId, array $post): array {
            return (new DeviceTrustService())->rename(
                $companyId,
                (string) ($post['device_id'] ?? ''),
                (string) ($post['nickname'] ?? ''),
                $userId
            );
        });
    }

    public function forceLogout(): void
    {
        $this->mutate(function (int $companyId, int $userId, array $post): array {
            return (new DeviceTrustService())->forceLogout(
                $companyId,
                (string) ($post['device_id'] ?? ''),
                $userId
            );
        });
    }

    public function restore(): void
    {
        $this->mutate(function (int $companyId, int $userId, array $post): array {
            return (new DeviceTrustService())->restore(
                $companyId,
                (string) ($post['device_id'] ?? '')
            );
        });
    }

    /** @param callable(int, int, array<string, mixed>): array<string, mixed> $action */
    private function mutate(callable $action): void
    {
        rateb_bootstrap_ops_tenant();
        if (!$this->canManage()) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('security/offline-devices'));
            return;
        }
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            SessionManager::flash('error', __('csrf_invalid'));
            $this->redirect(rateb_app_url('security/offline-devices'));
            return;
        }
        $companyId = (int) (TenantContext::companyId() ?? 0);
        $userId = (int) (SessionManager::get('rateb_user_id', 0) ?? 0);
        if ($companyId < 1 || $userId < 1) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('security/offline-devices'));
            return;
        }
        $result = $action($companyId, $userId, $_POST);
        if (!empty($result['ok'])) {
            SessionManager::flash('success', __('saved_ok'));
        } else {
            SessionManager::flash('error', (string) ($result['error'] ?? __('access_denied')));
        }
        $this->redirect(rateb_app_url('security/offline-devices'));
    }

    private function canManage(): bool
    {
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }
        if (function_exists('rateb_can')) {
            try {
                return rateb_can('offline.devices.manage');
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }
}
