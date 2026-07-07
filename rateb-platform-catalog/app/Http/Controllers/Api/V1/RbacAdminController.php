<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\RbacAdminService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class RbacAdminController
{
    public function __construct(
        private readonly RbacAdminService $rbacAdminService
    ) {
    }

    public function listRoles(): void
    {
        try {
            $items = $this->rbacAdminService->listRoles();
            ApiEnvelope::success($items);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function getUserRoles(array $params): void
    {
        try {
            $result = $this->rbacAdminService->getUserRoles($params['uuid']);
            ApiEnvelope::success($result);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function assignUserRoles(array $params): void
    {
        try {
            $payload = Request::jsonBody();
            $result = $this->rbacAdminService->assignUserRoles($params['uuid'], $payload);
            ApiEnvelope::success($result);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function patchRole(array $params): void
    {
        try {
            $payload = Request::jsonBody();
            $role = $this->rbacAdminService->patchRole($params['uuid'], $payload);
            ApiEnvelope::success($role);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    private function handleError(\RuntimeException $e): void
    {
        $status = (int) $e->getCode();
        if ($status < 400 || $status > 599) {
            $status = 400;
        }
        ApiEnvelope::error([['message' => $e->getMessage()]], $status);
    }
}
