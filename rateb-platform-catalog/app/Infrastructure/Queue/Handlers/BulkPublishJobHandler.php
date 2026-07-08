<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Queue\Handlers;

use Rateb\PlatformCatalog\Application\Services\WorkflowService;
use Rateb\PlatformCatalog\Application\Support\SystemActorContext;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Contracts\JobHandlerInterface;
use Rateb\PlatformCatalog\Infrastructure\Queue\Job;

final class BulkPublishJobHandler implements JobHandlerInterface
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly ProductReadRepositoryInterface $productReadRepository
    ) {
    }

    public function supports(string $jobType): bool
    {
        return $jobType === 'bulk_publish';
    }

    public function handle(Job $job): void
    {
        $productUuids = $job->payload['product_uuids'] ?? [];
        if (!is_array($productUuids)) {
            throw new \InvalidArgumentException('product_uuids array is required');
        }

        foreach ($productUuids as $productUuid) {
            if (!is_string($productUuid) || trim($productUuid) === '') {
                continue;
            }

            $meta = $this->productReadRepository->findWorkflowMeta($productUuid);
            if ($meta === null) {
                throw new \RuntimeException('Product not found: ' . $productUuid, 404);
            }

            $lockVersion = isset($meta['lock_version']) ? (int) $meta['lock_version'] : null;
            if ($lockVersion === null) {
                throw new \RuntimeException('Missing lock_version for product: ' . $productUuid, 500);
            }

            SystemActorContext::runAsSystem(function () use ($productUuid, $lockVersion): void {
                $this->workflowService->publish($productUuid, [
                    'actor_id' => SystemActorContext::SYSTEM_USER_ID,
                    'lock_version' => $lockVersion,
                ]);
            });
        }
    }
}

