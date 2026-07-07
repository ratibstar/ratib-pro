<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Api\V1;

use Rateb\PlatformCatalog\Application\Services\ProductVersionConflictException;
use Rateb\PlatformCatalog\Application\Services\WorkflowGateException;
use Rateb\PlatformCatalog\Application\Services\WorkflowCommentService;
use Rateb\PlatformCatalog\Application\Services\WorkflowService;
use Rateb\PlatformCatalog\Http\Responses\ApiEnvelope;
use Rateb\PlatformCatalog\Support\Request;

final class WorkflowController
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly WorkflowCommentService $commentService
    ) {
    }

    /** @param array<string, string> $params */
    public function submit(array $params): void
    {
        $this->action($params, 'submit');
    }

    /** @param array<string, string> $params */
    public function approve(array $params): void
    {
        $this->action($params, 'approve');
    }

    /** @param array<string, string> $params */
    public function reject(array $params): void
    {
        $this->action($params, 'reject');
    }

    /** @param array<string, string> $params */
    public function publish(array $params): void
    {
        $this->action($params, 'publish');
    }

    /** @param array<string, string> $params */
    public function archive(array $params): void
    {
        $this->action($params, 'archive');
    }

    /** @param array<string, string> $params */
    public function restore(array $params): void
    {
        $this->action($params, 'restore');
    }

    /** @param array<string, string> $params */
    public function listHistory(array $params): void
    {
        try {
            $limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 50;
            $items = $this->workflowService->history($params['uuid'], $limit);
            ApiEnvelope::success($items);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function listComments(array $params): void
    {
        try {
            $limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 100;
            $items = $this->commentService->listForProduct($params['uuid'], $limit);
            ApiEnvelope::success($items);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    public function storeComment(array $params): void
    {
        try {
            $payload = Request::jsonBody();
            $uuid = $this->commentService->addCommentByProductUuid($params['uuid'], $payload);
            ApiEnvelope::success(['uuid' => $uuid], [], [], 201);
        } catch (\InvalidArgumentException $e) {
            ApiEnvelope::error([['message' => $e->getMessage()]], 422);
        } catch (\RuntimeException $e) {
            $this->handleError($e);
        }
    }

    /** @param array<string, string> $params */
    private function action(array $params, string $action): void
    {
        try {
            $payload = Request::jsonBody();
            $result = match ($action) {
                'submit' => $this->workflowService->submit($params['uuid'], $payload),
                'approve' => $this->workflowService->approve($params['uuid'], $payload),
                'reject' => $this->workflowService->reject($params['uuid'], $payload),
                'publish' => $this->workflowService->publish($params['uuid'], $payload),
                'archive' => $this->workflowService->archive($params['uuid'], $payload),
                'restore' => $this->workflowService->restore($params['uuid'], $payload),
                default => throw new \RuntimeException('Unsupported action', 422),
            };
            ApiEnvelope::success($result, ['warnings' => $result['warnings'] ?? []]);
        } catch (WorkflowGateException $e) {
            ApiEnvelope::error([
                [
                    'error' => 'workflow_gate_blocked',
                    'message' => $e->getMessage(),
                    'failed_rules' => $e->failedRules(),
                    'warnings' => $e->warnings(),
                ],
            ], 422);
        } catch (ProductVersionConflictException $e) {
            ApiEnvelope::error(
                [['error' => 'version_conflict', 'current_lock_version' => $e->currentLockVersion]],
                409
            );
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
