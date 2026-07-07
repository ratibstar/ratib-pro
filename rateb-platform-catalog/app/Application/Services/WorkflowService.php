<?php



declare(strict_types=1);



namespace Rateb\PlatformCatalog\Application\Services;



use Rateb\PlatformCatalog\Application\Events\EventDispatcher;

use Rateb\PlatformCatalog\Application\Events\ProductApproved;

use Rateb\PlatformCatalog\Application\Events\ProductArchived;

use Rateb\PlatformCatalog\Application\Events\ProductPublished;

use Rateb\PlatformCatalog\Application\Events\ProductRejected;

use Rateb\PlatformCatalog\Application\Events\ProductSubmitted;

use Rateb\PlatformCatalog\Application\Events\VersionCreated;

use Rateb\PlatformCatalog\Application\Policies\WorkflowPolicy;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowReadRepositoryInterface;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductWorkflowWriteRepositoryInterface;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowReadRepositoryInterface;



final class WorkflowService

{

    public function __construct(

        private readonly WorkflowReadRepositoryInterface $workflowReadRepository,

        private readonly ProductWorkflowWriteRepositoryInterface $workflowWriteRepository,

        private readonly ProductWorkflowReadRepositoryInterface $workflowReadHistoryRepository,

        private readonly ProductReadRepositoryInterface $productReadRepository,

        private readonly WorkflowCommentService $commentService,

        private readonly CompletenessService $completenessService,

        private readonly ProductSnapshotBuilder $snapshotBuilder,

        private readonly WorkflowPolicy $policy,

        private readonly ConcurrencyService $concurrencyService,

        private readonly AuditEventService $auditEventService,

        private readonly EventDispatcher $events

    ) {

    }



    /**

     * @param array<string, mixed> $payload

     * @return array<string, mixed>

     */

    public function submit(string $productUuid, array $payload): array

    {

        return $this->transition($productUuid, 'submit', $payload);

    }



    /**

     * @param array<string, mixed> $payload

     * @return array<string, mixed>

     */

    public function approve(string $productUuid, array $payload): array

    {

        return $this->transition($productUuid, 'approve', $payload);

    }



    /**

     * @param array<string, mixed> $payload

     * @return array<string, mixed>

     */

    public function reject(string $productUuid, array $payload): array

    {

        return $this->transition($productUuid, 'reject', $payload);

    }



    /**

     * @param array<string, mixed> $payload

     * @return array<string, mixed>

     */

    public function publish(string $productUuid, array $payload): array

    {

        return $this->transition($productUuid, 'publish', $payload);

    }



    /**

     * @param array<string, mixed> $payload

     * @return array<string, mixed>

     */

    public function archive(string $productUuid, array $payload): array

    {

        return $this->transition($productUuid, 'archive', $payload);

    }



    /**

     * @param array<string, mixed> $payload

     * @return array<string, mixed>

     */

    public function restore(string $productUuid, array $payload): array

    {

        return $this->transition($productUuid, 'restore', $payload);

    }



    /**

     * @return list<array<string, mixed>>

     */

    public function history(string $productUuid, int $limit = 50): array

    {

        $this->validateProductUuid($productUuid);

        $this->policy->viewHistory();

        $limit = max(1, min(200, $limit));

        $meta = $this->productReadRepository->findWorkflowMeta($productUuid);

        if ($meta === null) {

            throw new \RuntimeException('Product not found', 404);

        }



        return $this->workflowReadHistoryRepository->listHistory($productUuid, $limit);

    }



    private function validateProductUuid(string $productUuid): void

    {

        if ($productUuid === '' || strlen($productUuid) !== 36 || substr_count($productUuid, '-') !== 4) {

            throw new \InvalidArgumentException('Invalid product uuid');

        }

    }



    /**

     * @param array<string, mixed> $payload

     * @return array<string, mixed>

     */

    private function transition(string $productUuid, string $action, array $payload): array

    {

        $product = $this->productReadRepository->findWorkflowMeta($productUuid);

        if ($product === null) {

            throw new \RuntimeException('Product not found', 404);

        }



        $fromStatus = (string) $product['status'];

        $transition = $this->workflowReadRepository->findTransition($fromStatus, $action);

        if ($transition === null) {

            throw new \RuntimeException('Invalid workflow transition', 422);

        }



        $this->policy->assertTransitionPermission($transition['requires_permission'] ?? null);



        $toStatus = (string) $transition['to_state'];

        $evaluation = $this->completenessService->evaluateForTransition(

            $productUuid,

            (int) $product['id'],

            (int) $product['category_id'],

            $fromStatus,

            $toStatus

        );

        if ($evaluation['blocking_failed']) {

            throw new WorkflowGateException(

                'Workflow transition blocked by completeness or category schema rules',

                $evaluation['failed_rules'],

                $evaluation['warnings']

            );

        }



        $lockVersion = $this->concurrencyService->requireLockVersion(

            isset($payload['lock_version']) ? (int) $payload['lock_version'] : null

        );



        $comment = isset($payload['comment']) ? (string) $payload['comment'] : null;

        $actorId = isset($payload['actor_id']) ? (int) $payload['actor_id'] : null;



        $versionSnapshot = null;

        $versionChangeType = null;

        $versionChangeSummary = null;

        if ($action === 'publish' || $action === 'archive') {

            $versionSnapshot = $this->snapshotBuilder->build($productUuid, (int) $product['version_number']);

            $versionChangeType = $action;

            $versionChangeSummary = $comment;

        }



        try {

            $result = $this->workflowWriteRepository->transitionStatus(

                $productUuid,

                $fromStatus,

                $toStatus,

                $action,

                $lockVersion,

                $actorId,

                $comment,

                $versionSnapshot,

                $versionChangeType,

                $versionChangeSummary

            );

        } catch (\RuntimeException $e) {

            if ((int) $e->getCode() === 409) {

                throw new ProductVersionConflictException(

                    (int) ($this->productReadRepository->findLockVersion($productUuid) ?? $lockVersion)

                );

            }

            throw $e;

        }



        if ($comment !== null && trim($comment) !== '') {

            $this->commentService->recordForProduct(

                $productUuid,

                (int) $product['id'],

                $action,

                $fromStatus,

                $toStatus,

                ['comment' => $comment, 'actor_id' => $actorId]

            );

        }



        $this->auditEventService->record(

            'product',

            $productUuid,

            $action,

            (int) $result['version_number'],

            $actorId,

            ['status' => $fromStatus],

            ['status' => $toStatus]

        );



        if ($action === 'publish') {

            $this->events->dispatch(new ProductPublished(

                $productUuid,

                (int) $result['version_number']

            ));

            if ($result['version_uuid'] !== null) {

                $this->events->dispatch(new VersionCreated($productUuid, (int) $result['version_number'], 'publish'));

            }

        }



        $this->dispatchWorkflowEvent($action, $productUuid, $fromStatus);



        return [

            'product_uuid' => $productUuid,

            'action' => $action,

            'from_status' => $fromStatus,

            'to_status' => $toStatus,

            'lock_version' => $result['lock_version'],

            'version_number' => $result['version_number'],

            'history_uuid' => $result['history_uuid'],

            'version_uuid' => $result['version_uuid'] ?? null,

            'warnings' => $evaluation['warnings'],

        ];

    }



    private function dispatchWorkflowEvent(string $action, string $productUuid, string $fromStatus): void

    {

        $event = match ($action) {

            'submit' => new ProductSubmitted($productUuid, $fromStatus),

            'approve' => new ProductApproved($productUuid),

            'reject' => new ProductRejected($productUuid),

            'archive' => new ProductArchived($productUuid),

            default => null,

        };

        if ($event !== null) {

            $this->events->dispatch($event);

        }

    }

}


