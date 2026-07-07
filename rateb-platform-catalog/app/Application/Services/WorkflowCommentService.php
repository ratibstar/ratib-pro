<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\Policies\WorkflowPolicy;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentWriteRepositoryInterface;

final class WorkflowCommentService
{
    public function __construct(
        private readonly WorkflowCommentReadRepositoryInterface $readRepository,
        private readonly WorkflowCommentWriteRepositoryInterface $writeRepository,
        private readonly ProductReadRepositoryInterface $productReadRepository,
        private readonly WorkflowPolicy $policy,
        private readonly AuditEventService $auditEventService
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForProduct(string $productUuid, int $limit = 100): array
    {
        $this->policy->comment();

        return $this->readRepository->listByEntity('product', $productUuid, $limit);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function addCommentByProductUuid(string $productUuid, array $payload): string
    {
        $this->policy->comment();
        $product = $this->productReadRepository->findWorkflowMeta($productUuid);
        if ($product === null) {
            throw new \RuntimeException('Product not found', 404);
        }

        return $this->recordForProduct(
            $productUuid,
            (int) $product['id'],
            (string) ($payload['workflow_action'] ?? 'submit'),
            isset($payload['from_status']) ? (string) $payload['from_status'] : (string) $product['status'],
            isset($payload['to_status']) ? (string) $payload['to_status'] : null,
            $payload
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function addForProduct(
        string $productUuid,
        int $productId,
        string $workflowAction,
        ?string $fromStatus,
        ?string $toStatus,
        array $payload
    ): string {
        $this->policy->comment();

        return $this->recordForProduct($productUuid, $productId, $workflowAction, $fromStatus, $toStatus, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordForProduct(
        string $productUuid,
        int $productId,
        string $workflowAction,
        ?string $fromStatus,
        ?string $toStatus,
        array $payload
    ): string {
        $comment = trim((string) ($payload['comment'] ?? ''));
        if ($comment === '') {
            throw new \InvalidArgumentException('comment is required');
        }

        $commentUuid = $this->writeRepository->add(
            'product',
            $productId,
            $productUuid,
            $workflowAction,
            $fromStatus,
            $toStatus,
            $comment,
            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null
        );

        $this->auditEventService->record(
            'product',
            $productUuid,
            'workflow_comment',
            null,
            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,
            null,
            ['comment' => $comment, 'workflow_action' => $workflowAction]
        );

        return $commentUuid;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function recordForEntity(
        string $entityType,
        int $entityId,
        string $entityUuid,
        string $workflowAction,
        ?string $fromStatus,
        ?string $toStatus,
        array $payload
    ): string {
        $comment = trim((string) ($payload['comment'] ?? ''));
        if ($comment === '') {
            throw new \InvalidArgumentException('comment is required');
        }

        $commentUuid = $this->writeRepository->add(
            $entityType,
            $entityId,
            $entityUuid,
            $workflowAction,
            $fromStatus,
            $toStatus,
            $comment,
            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null
        );

        $this->auditEventService->record(
            $entityType,
            $entityUuid,
            'workflow_comment',
            null,
            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,
            null,
            ['comment' => $comment, 'workflow_action' => $workflowAction]
        );

        return $commentUuid;
    }
}
