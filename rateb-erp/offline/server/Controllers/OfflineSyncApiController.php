<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Services\OfflineAuthorizationService;
use Rateb\App\Offline\Services\OfflineBranchGuard;
use Rateb\App\Offline\Services\OfflineFeatureFlagService;
use Rateb\App\Offline\Services\OfflinePushAckContract;
use Rateb\App\Offline\Services\OfflineSyncService;

/**
 * Additive Offline Sync API — /api/v1/offline/*
 * Phase 2A.1: hardened ack contract, auth, branch, authz.
 */
final class OfflineSyncApiController extends Controller
{
    public function status(): void
    {
        $this->requireAuthOrAbort();
        $service = new OfflineSyncService();
        $this->json([
            'ok' => true,
            'status' => $service->status($this->companyId()),
        ]);
    }

    public function push(): void
    {
        $this->requireAuthOrAbort();
        if (!$this->flagsEnabled()) {
            $this->json([
                'ok' => false,
                'error' => ['message' => 'offline_disabled', 'code' => 'offline_disabled'],
                'result' => [
                    'accepted' => 0,
                    'duplicate' => 0,
                    'conflict' => 0,
                    'rejected' => 0,
                    'clearable_keys' => [],
                    'errors' => ['offline_disabled' => true],
                ],
            ], 403);
            return;
        }
        $this->requireCsrfOrAbort();
        $body = $this->jsonBody();
        $branchCheck = (new OfflineBranchGuard())->validate(
            isset($body['branch_id']) ? (int) $body['branch_id'] : null
        );
        if (!$branchCheck['ok']) {
            $this->json([
                'ok' => false,
                'error' => ['message' => 'Branch scope violation', 'code' => 'branch_denied'],
                'result' => [
                    'accepted' => 0,
                    'duplicate' => 0,
                    'conflict' => 0,
                    'rejected' => 0,
                    'clearable_keys' => [],
                    'errors' => ['branch_denied' => true],
                ],
            ], 403);
            return;
        }

        $items = $body['items'] ?? $body;
        if (!is_array($items)) {
            $items = [];
        }
        if ($items !== [] && !array_is_list($items)) {
            $items = [$items];
        }

        $service = new OfflineSyncService();
        $result = $service->pushQueue($items, [
            'company_id' => $this->companyId(),
            'branch_id' => (int) ($branchCheck['branch_id'] ?? 0),
            'device_id' => (string) ($body['device_id'] ?? ''),
            'user_id' => $this->userId(),
        ]);

        $ack = (new OfflinePushAckContract())->evaluate($result);
        $result['clearable_keys'] = $ack['clearable_keys'];

        $this->json([
            'ok' => $ack['ok'],
            'result' => $result,
        ], $ack['http_status']);
    }

    public function process(): void
    {
        $this->requireAuthOrAbort();
        $this->requireSyncManageOrAbort();
        if (!$this->flagsEnabled()) {
            $this->json([
                'ok' => false,
                'error' => ['message' => 'offline_disabled', 'code' => 'offline_disabled'],
            ], 403);
            return;
        }
        $this->requireCsrfOrAbort();
        $service = new OfflineSyncService();
        $this->json([
            'ok' => true,
            'result' => $service->processPending($this->companyId(), 50),
        ]);
    }

    public function conflicts(): void
    {
        $this->requireAuthOrAbort();
        $service = new OfflineSyncService();
        $this->json([
            'ok' => true,
            'conflicts' => $service->openConflicts(50, $this->companyId()),
        ]);
    }

    public function resolveConflict(array $params): void
    {
        $this->requireAuthOrAbort();
        $this->requireSyncManageOrAbort();
        if (!$this->flagsEnabled()) {
            $this->json([
                'ok' => false,
                'error' => ['message' => 'offline_disabled', 'code' => 'offline_disabled'],
            ], 403);
            return;
        }
        $this->requireCsrfOrAbort();
        $id = (int) ($params['id'] ?? 0);
        $body = $this->jsonBody();
        $resolution = trim((string) ($body['resolution'] ?? $_POST['resolution'] ?? ''));
        $service = new OfflineSyncService();
        $this->json($service->resolveConflict($id, $resolution, $this->userId(), $this->companyId()));
    }

    public function delta(array $params): void
    {
        $this->requireAuthOrAbort();
        $entity = trim((string) ($params['entity'] ?? ''));
        $branchId = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : null;
        if ($branchId !== null && $branchId > 0) {
            $branchCheck = (new OfflineBranchGuard())->validate($branchId);
            if (!$branchCheck['ok']) {
                $this->json([
                    'ok' => false,
                    'error' => ['message' => 'Branch scope violation', 'code' => 'branch_denied'],
                ], 403);
                return;
            }
        }
        $service = new OfflineSyncService();
        $this->json([
            'ok' => true,
            'delta' => $service->delta($entity, $this->companyId(), $branchId),
        ]);
    }

    private function flagsEnabled(): bool
    {
        return (new OfflineFeatureFlagService())->isMasterEnabled();
    }

    private function companyId(): int
    {
        $fromTenant = (int) (TenantContext::companyId() ?? 0);
        if ($fromTenant > 0) {
            return $fromTenant;
        }

        return (int) (SessionManager::get('rateb_company_id') ?? 0);
    }

    private function userId(): int
    {
        $apiUser = (int) (TenantContext::apiUserId() ?? 0);
        if ($apiUser > 0) {
            return $apiUser;
        }

        return (int) (SessionManager::get('rateb_user_id') ?? 0);
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $raw = (string) file_get_contents('php://input');
        if ($raw === '') {
            return $_POST;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function requireAuthOrAbort(): void
    {
        if ((new OfflineAuthorizationService())->isAuthenticatedCompany() || $this->companyId() > 0) {
            return;
        }
        Response::json([
            'ok' => false,
            'error' => ['message' => 'Unauthorized', 'code' => 'unauthorized'],
        ], 401);
        exit;
    }

    private function requireSyncManageOrAbort(): void
    {
        if ((new OfflineAuthorizationService())->canManageSync()) {
            return;
        }
        Response::json([
            'ok' => false,
            'error' => ['message' => 'Forbidden', 'code' => 'forbidden'],
        ], 403);
        exit;
    }

    private function requireCsrfOrAbort(): void
    {
        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (stripos($auth, 'Bearer ') === 0) {
            return;
        }
        $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
        if ($token !== '' && Csrf::validate($token)) {
            return;
        }
        if (SessionManager::get('rateb_user_id') || $this->companyId() > 0) {
            Response::json(['ok' => false, 'error' => ['message' => 'csrf_invalid', 'code' => 'csrf_invalid']], 419);
            exit;
        }
    }
}
