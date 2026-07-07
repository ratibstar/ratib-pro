<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Policies\CategorySchemaPolicy;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategorySchemaReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategorySchemaWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessDataReadRepositoryInterface;

final class CategorySchemaService
{
    public function __construct(
        private readonly CategorySchemaReadRepositoryInterface $schemaReadRepository,
        private readonly CategorySchemaWriteRepositoryInterface $schemaWriteRepository,
        private readonly CompletenessDataReadRepositoryInterface $completenessDataReadRepository,
        private readonly CategorySchemaPolicy $policy,
        private readonly AuditEventService $auditEventService
    ) {
    }

    /**
     * @return array{category_uuid: string, items: list<array<string, mixed>>}
     */
    public function getSchemaForCategoryUuid(string $categoryUuid): array
    {
        $this->policy->view();
        $categoryId = $this->schemaReadRepository->findCategoryIdByUuid($categoryUuid);
        if ($categoryId === null) {
            throw new \RuntimeException('Category not found', 404);
        }

        return [
            'category_uuid' => $categoryUuid,
            'items' => $this->schemaReadRepository->listResolvedSchemaForCategory($categoryId),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{category_uuid: string, items: list<array<string, mixed>>}
     */
    public function replaceSchemaForCategoryUuid(string $categoryUuid, array $payload): array
    {
        $this->policy->manage();
        $categoryId = $this->schemaReadRepository->findCategoryIdByUuid($categoryUuid);
        if ($categoryId === null) {
            throw new \RuntimeException('Category not found', 404);
        }

        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $actorId = isset($payload['actor_id']) ? (int) $payload['actor_id'] : null;
        $before = $this->schemaReadRepository->listResolvedSchemaForCategory($categoryId);
        $this->schemaWriteRepository->replaceForCategory($categoryId, $items, $actorId);
        $after = $this->schemaReadRepository->listResolvedSchemaForCategory($categoryId);

        $this->auditEventService->record(
            'category',
            $categoryUuid,
            'attribute_schema_replace',
            null,
            $actorId,
            ['items' => $before],
            ['items' => $after]
        );

        return [
            'category_uuid' => $categoryUuid,
            'items' => $after,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRequiredForCategory(int $categoryId): array
    {
        return $this->schemaReadRepository->listRequiredForCategory($categoryId);
    }

    /**
     * @param list<array<string, mixed>>|null $attributeValueRows
     * @return array{valid: bool, missing: list<array<string, mixed>>}
     */
    public function validateProductAttributes(
        int $categoryId,
        string $productUuid,
        LocaleContext $locale,
        ?array $attributeValueRows = null
    ): array {
        $required = $this->schemaReadRepository->listRequiredForCategory($categoryId);
        if ($required === []) {
            return ['valid' => true, 'missing' => []];
        }

        $rows = $attributeValueRows ?? $this->completenessDataReadRepository->listAttributeValuesByLocale($productUuid);
        $present = [];
        foreach ($rows as $attribute) {
            if (($attribute['language_code'] ?? '') !== $locale->locale) {
                continue;
            }
            $code = (string) ($attribute['attribute_code'] ?? '');
            $value = $attribute['value_text'] ?? null;
            if ($code !== '' && $value !== null && trim((string) $value) !== '') {
                $present[$code] = true;
            }
        }

        $missing = [];
        foreach ($required as $rule) {
            $code = (string) ($rule['attribute_code'] ?? '');
            if ($code === '' || isset($present[$code])) {
                continue;
            }
            $missing[] = $rule;
        }

        return ['valid' => $missing === [], 'missing' => $missing];
    }
}
