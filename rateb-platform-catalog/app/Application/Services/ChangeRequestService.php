<?php



declare(strict_types=1);



namespace Rateb\PlatformCatalog\Application\Services;



use Rateb\PlatformCatalog\Application\DTO\LocaleContext;

use Rateb\PlatformCatalog\Application\Events\ChangeRequestApplied;

use Rateb\PlatformCatalog\Application\Events\ChangeRequestCreated;

use Rateb\PlatformCatalog\Application\Events\EventDispatcher;

use Rateb\PlatformCatalog\Application\Events\ProductUpdated;

use Rateb\PlatformCatalog\Application\Mappers\ProductMapper;

use Rateb\PlatformCatalog\Application\Policies\ChangeRequestPolicy;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChangeRequestReadRepositoryInterface;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChangeRequestWriteRepositoryInterface;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentReadRepositoryInterface;



final class ChangeRequestService

{

    public function __construct(

        private readonly ChangeRequestReadRepositoryInterface $readRepository,

        private readonly ChangeRequestWriteRepositoryInterface $writeRepository,

        private readonly ProductReadRepositoryInterface $productReadRepository,

        private readonly ProductSnapshotBuilder $snapshotBuilder,

        private readonly WorkflowCommentService $commentService,

        private readonly WorkflowCommentReadRepositoryInterface $commentReadRepository,

        private readonly ChangeRequestPolicy $policy,

        private readonly ConcurrencyService $concurrencyService,

        private readonly AuditEventService $auditEventService,

        private readonly LocaleResolverService $localeResolver,

        private readonly EventDispatcher $events

    ) {

    }



    /**

     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}

     */

    public function list(?string $status, int $limit, int $offset): array

    {

        $this->policy->list();



        $items = $this->readRepository->list($status, $limit, $offset);



        return [

            'items' => $items,

            'meta' => ['count' => count($items), 'limit' => $limit, 'offset' => $offset],

        ];

    }



    /**

     * @return array<string, mixed>

     */

    public function show(string $uuid): array

    {

        $this->policy->list();

        $request = $this->readRepository->findByUuid($uuid);

        if ($request === null) {

            throw new \RuntimeException('Change request not found', 404);

        }

        $request['items'] = $this->readRepository->listItems((int) $request['id']);

        $request['comments'] = $this->commentReadRepository->listByEntity(

            'change_request',

            $uuid

        );



        return $request;

    }



    /**

     * @param array<string, mixed> $payload

     * @return array<string, mixed>

     */

    public function create(array $payload): array

    {

        $this->policy->create();

        $productUuid = (string) ($payload['product_uuid'] ?? '');

        if ($productUuid === '') {

            throw new \InvalidArgumentException('product_uuid is required');

        }



        $product = $this->productReadRepository->findWorkflowMeta($productUuid);

        if ($product === null) {

            throw new \RuntimeException('Product not found', 404);

        }



        $locale = $this->localeResolver->resolveFromRequest();

        $currentRow = $this->productReadRepository->findByUuid($productUuid, $locale);

        $currentProduct = $currentRow !== null ? ProductMapper::toProductDto($currentRow)->toArray() : [];



        $proposed = is_array($payload['proposed_changes'] ?? null) ? $payload['proposed_changes'] : [];

        $items = $this->buildItems($proposed, $currentProduct);

        $uuid = $this->writeRepository->create(

            (int) $product['id'],

            (string) ($payload['request_type'] ?? 'update'),

            $proposed,

            (int) $product['version_number'],

            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,

            $items

        );



        $this->events->dispatch(new ChangeRequestCreated($uuid, $productUuid));

        $this->auditEventService->record(

            'change_request',

            $uuid,

            'submit',

            (int) $product['version_number'],

            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,

            null,

            ['product_uuid' => $productUuid]

        );



        $created = $this->readRepository->findByUuid($uuid);



        return (array) $created;

    }



    /**

     * @param array<string, mixed> $payload

     */

    public function assignReviewer(string $uuid, array $payload): bool

    {

        $this->policy->review();

        $reviewerId = (int) ($payload['reviewer_id'] ?? 0);

        if ($reviewerId <= 0) {

            throw new \InvalidArgumentException('reviewer_id is required');

        }



        $updated = $this->writeRepository->assignReviewer($uuid, $reviewerId);

        if (!$updated) {

            throw new \RuntimeException('Change request not found or not assignable', 404);

        }



        return true;

    }



    /**

     * @param array<string, mixed> $payload

     */

    public function approve(string $uuid, array $payload): bool

    {

        $this->policy->review();

        $note = isset($payload['review_note']) ? (string) $payload['review_note'] : null;

        $updated = $this->writeRepository->approve(

            $uuid,

            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,

            $note

        );

        if (!$updated) {

            throw new \RuntimeException('Change request not found or not approvable', 404);

        }



        $this->addReviewComment($uuid, 'approve', $note, $payload);

        $this->auditEventService->record(
            'change_request',
            $uuid,
            'approve',
            null,
            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,
            ['status' => 'pending_review'],
            ['status' => 'approved']
        );

        return true;

    }



    /**

     * @param array<string, mixed> $payload

     */

    public function reject(string $uuid, array $payload): bool

    {

        $this->policy->review();

        $note = isset($payload['review_note']) ? (string) $payload['review_note'] : null;

        $updated = $this->writeRepository->reject(

            $uuid,

            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,

            $note

        );

        if (!$updated) {

            throw new \RuntimeException('Change request not found or not rejectable', 404);

        }



        $this->addReviewComment($uuid, 'reject', $note, $payload);

        $this->auditEventService->record(
            'change_request',
            $uuid,
            'reject',
            null,
            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,
            ['status' => 'pending_review'],
            ['status' => 'rejected']
        );

        return true;

    }



    /**

     * @param array<string, mixed> $payload

     * @return array<string, mixed>

     */

    public function apply(string $uuid, array $payload): array

    {

        $this->policy->apply();

        $request = $this->readRepository->findByUuid($uuid);

        if ($request === null) {

            throw new \RuntimeException('Change request not found', 404);

        }

        if ((string) $request['status'] !== 'approved') {

            throw new \RuntimeException('Change request must be approved before apply', 422);

        }



        $productUuid = (string) $request['product_uuid'];

        $changes = is_array($request['proposed_changes']) ? $request['proposed_changes'] : [];

        $productData = is_array($changes['product'] ?? null) ? $changes['product'] : $changes;

        $translations = is_array($changes['translations'] ?? null) ? $changes['translations'] : [];

        $seoData = is_array($changes['seo'] ?? null) ? $changes['seo'] : null;



        $lockVersion = $this->concurrencyService->requireLockVersion(

            isset($payload['lock_version']) ? (int) $payload['lock_version'] : null

        );

        $expectedCurrentVersion = (int) $request['current_version'];

        $actorId = isset($payload['actor_id']) ? (int) $payload['actor_id'] : null;

        $versionSnapshot = $this->snapshotBuilder->build($productUuid, $expectedCurrentVersion);



        try {

            $result = $this->writeRepository->applyApproved(

                $uuid,

                $productUuid,

                $lockVersion,

                $expectedCurrentVersion,

                $productData,

                $translations,

                $seoData,

                $versionSnapshot,

                $actorId

            );

        } catch (\RuntimeException $e) {

            if ((int) $e->getCode() === 409) {

                if ($e->getMessage() === 'stale_change_request_version') {

                    throw $e;

                }

                throw new ProductVersionConflictException(

                    (int) ($this->productReadRepository->findLockVersion($productUuid) ?? $lockVersion)

                );

            }

            throw $e;

        }



        $this->auditEventService->record(

            'change_request',

            $uuid,

            'apply',

            (int) $result['version_number'],

            $actorId,

            ['current_version' => $expectedCurrentVersion],

            ['version_number' => (int) $result['version_number']]

        );



        $locale = $this->localeResolver->resolveFromRequest();

        $this->events->dispatch(new ChangeRequestApplied($uuid, $productUuid, (int) $result['version_number']));

        $this->events->dispatch(new ProductUpdated($productUuid, $locale->locale));



        return [

            'change_request_uuid' => $uuid,

            'product_uuid' => $productUuid,

            'applied' => true,

            'version_number' => (int) $result['version_number'],

            'lock_version' => (int) $result['lock_version'],

            'version_uuid' => $result['version_uuid'],

        ];

    }



    /**

     * @param array<string, mixed> $proposed

     * @param array<string, mixed> $currentProduct

     * @return list<array{field_path: string, old_value: mixed, new_value: mixed}>

     */

    private function buildItems(array $proposed, array $currentProduct): array

    {

        $items = [];

        foreach ($proposed as $key => $value) {

            if ($key === 'translations' && is_array($value)) {

                continue;

            }

            if ($key === 'seo' && is_array($value)) {

                continue;

            }

            $items[] = [

                'field_path' => (string) $key,

                'old_value' => $currentProduct[$key] ?? null,

                'new_value' => $value,

            ];

        }



        return $items;

    }



    /**

     * @param array<string, mixed> $payload

     */

    private function addReviewComment(string $uuid, string $action, ?string $note, array $payload): void

    {

        if ($note === null || trim($note) === '') {

            return;

        }



        $request = $this->readRepository->findByUuid($uuid);

        if ($request === null) {

            return;

        }



        $this->commentService->recordForEntity(

            'change_request',

            (int) $request['id'],

            $uuid,

            $action,

            (string) $request['status'],

            $action === 'approve' ? 'approved' : 'rejected',

            ['comment' => $note, 'actor_id' => $payload['actor_id'] ?? null]

        );

    }

}


