<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\ChangeRequestService;
use Rateb\PlatformCatalog\Application\Services\ProductVersionConflictException;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class ChangeRequestController
{
    public function __construct(
        private readonly ChangeRequestService $changeRequestService
    ) {
    }

    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        unset($params);
        try {
            $status = isset($_GET['status']) ? (string) $_GET['status'] : null;
            $limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 100;
            $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
            $result = $this->changeRequestService->list($status, $limit, $offset);
            ApiEnvelope::success($result['items'], $result['meta']);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function show(array $params): void
    {
        try {
            $item = $this->changeRequestService->show($params['uuid']);
            ApiEnvelope::success($item);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function store(array $params = []): void
    {
        unset($params);
        try {
            $payload = Request::jsonBody();
            $item = $this->changeRequestService->create($payload);
            ApiEnvelope::success($item, [], [], 201);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function assignReviewer(array $params): void
    {
        try {
            $payload = Request::jsonBody();
            $this->changeRequestService->assignReviewer($params['uuid'], $payload);
            ApiEnvelope::success(['uuid' => $params['uuid'], 'assigned' => true]);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function approve(array $params): void
    {
        $this->reviewAction($params, 'approve');
    }

    /** @param array<string, string> $params */
    public function reject(array $params): void
    {
        $this->reviewAction($params, 'reject');
    }

    /** @param array<string, string> $params */
    public function apply(array $params): void
    {
        try {
            $payload = Request::jsonBody();
            $result = $this->changeRequestService->apply($params['uuid'], $payload);
            ApiEnvelope::success($result);
        } catch (ProductVersionConflictException $e) {
            ApiEnvelope::error(
                [['error' => 'version_conflict', 'current_lock_version' => $e->currentLockVersion]],
                409
            );
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    private function reviewAction(array $params, string $action): void
    {
        try {
            $payload = Request::jsonBody();
            if ($action === 'approve') {
                $this->changeRequestService->approve($params['uuid'], $payload);
            } else {
                $this->changeRequestService->reject($params['uuid'], $payload);
            }
            ApiEnvelope::success(['uuid' => $params['uuid'], 'status' => $action === 'approve' ? 'approved' : 'rejected']);
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
