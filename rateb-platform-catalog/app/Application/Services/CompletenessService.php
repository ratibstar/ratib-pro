<?php



declare(strict_types=1);



namespace Rateb\PlatformCatalog\Application\Services;



use Rateb\PlatformCatalog\Application\DTO\LocaleContext;

use Rateb\PlatformCatalog\Application\Events\CompletenessRecalculated;

use Rateb\PlatformCatalog\Application\Events\EventDispatcher;

use Rateb\PlatformCatalog\Application\Policies\CompletenessPolicy;

use Rateb\PlatformCatalog\Application\Support\CatalogLocales;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessDataReadRepositoryInterface;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessRuleReadRepositoryInterface;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessRuleWriteRepositoryInterface;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductCompletenessReadRepositoryInterface;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductCompletenessWriteRepositoryInterface;

use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;



final class CompletenessService

{

    public function __construct(

        private readonly CompletenessRuleReadRepositoryInterface $ruleReadRepository,

        private readonly CompletenessRuleWriteRepositoryInterface $ruleWriteRepository,

        private readonly ProductCompletenessReadRepositoryInterface $scoreReadRepository,

        private readonly ProductCompletenessWriteRepositoryInterface $scoreWriteRepository,

        private readonly ProductReadRepositoryInterface $productReadRepository,

        private readonly CompletenessDataReadRepositoryInterface $dataReadRepository,

        private readonly CategorySchemaService $categorySchemaService,

        private readonly CompletenessPolicy $policy,

        private readonly LocaleResolverService $localeResolver,

        private readonly EventDispatcher $events,

        private readonly AuditEventService $auditEventService

    ) {

    }



    /**

     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}

     */

    public function getScores(string $productUuid): array

    {

        $this->policy->view();

        $meta = $this->productReadRepository->findWorkflowMeta($productUuid);

        if ($meta === null) {

            throw new \RuntimeException('Product not found', 404);

        }



        $scores = $this->scoreReadRepository->listByProductUuid($productUuid);

        if ($scores === []) {

            $scores = $this->recalculateAndStore($productUuid, (int) $meta['id'], (int) $meta['category_id']);

        }



        return [

            'items' => $scores,

            'meta' => ['product_uuid' => $productUuid],

        ];

    }



    /**

     * @return list<array<string, mixed>>

     */

    public function listRules(): array

    {

        $this->policy->manage();



        return $this->ruleReadRepository->listAll();

    }



    /**

     * @param array<string, mixed> $payload

     * @return array<string, mixed>

     */

    public function updateRule(string $code, array $payload): array

    {

        $this->policy->manage();

        $existing = $this->ruleReadRepository->findByCode($code);

        if ($existing === null) {

            throw new \RuntimeException('Completeness rule not found', 404);

        }



        $this->ruleWriteRepository->updateByCode($code, $payload);

        $updated = $this->ruleReadRepository->findByCode($code);

        $this->auditEventService->record(
            'completeness_rule',
            $code,
            'update',
            null,
            isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,
            $existing,
            (array) $updated
        );

        return (array) $updated;

    }



    /**

     * @return array{blocking_failed: bool, failed_rules: list<string>, warnings: list<string>, scores: list<array<string, mixed>>}

     */

    public function evaluateForTransition(

        string $productUuid,

        int $productId,

        int $categoryId,

        string $fromStatus,

        string $toStatus

    ): array {

        $scores = $this->recalculateAndStore($productUuid, $productId, $categoryId);

        $failedRules = [];

        foreach ($scores as $score) {

            foreach ($score['failed_rules'] as $ruleCode) {

                $failedRules[] = (string) $ruleCode;

            }

        }

        $failedRules = array_values(array_unique($failedRules));

        $blockingFailed = false;

        $warnings = [];



        if ($fromStatus === 'draft' && $toStatus === 'pending_review') {

            foreach ($scores as $score) {

                if ((float) $score['score'] < 100.0) {

                    $warnings[] = 'completeness_below_100:' . $score['locale'];

                }

            }

        }



        if ($fromStatus === 'pending_review' && $toStatus === 'approved') {

            $blockingFailed = $this->hasBlockingFailure($scores);

        }



        if ($fromStatus === 'approved' && $toStatus === 'published') {

            $defaultLocale = defined('RATEB_PLATFORM_CATALOG_DEFAULT_LOCALE')

                ? (string) RATEB_PLATFORM_CATALOG_DEFAULT_LOCALE

                : 'ar';

            $defaultScore = null;

            foreach ($scores as $score) {

                if ($score['locale'] === $defaultLocale) {

                    $defaultScore = $score;

                    break;

                }

            }

            if ($defaultScore !== null && (bool) $defaultScore['blocking_failed']) {

                $blockingFailed = true;

            }



            $schema = $this->categorySchemaService->validateProductAttributes(

                $categoryId,

                $productUuid,

                new LocaleContext($defaultLocale, 'en')

            );

            if (!$schema['valid']) {

                $blockingFailed = true;

                foreach ($schema['missing'] as $missing) {

                    $failedRules[] = 'category_schema:' . ($missing['attribute_code'] ?? 'unknown');

                }

            }

        }



        return [

            'blocking_failed' => $blockingFailed,

            'failed_rules' => $failedRules,

            'warnings' => $warnings,

            'scores' => $scores,

        ];

    }



    /**

     * @return list<array<string, mixed>>

     */

    public function recalculateAndStore(string $productUuid, int $productId, int $categoryId): array

    {

        $rules = $this->ruleReadRepository->listActive('product');

        $translations = $this->dataReadRepository->listProductTranslationsByLocale($productUuid);

        $seo = $this->dataReadRepository->listSeoTranslationsByLocale($productUuid);

        $images = $this->dataReadRepository->listImageTranslationsByLocale($productUuid);

        $variants = $this->dataReadRepository->listVariantTranslationsByLocale($productUuid);

        $attributeValues = $this->dataReadRepository->listAttributeValuesByLocale($productUuid);



        $locales = CatalogLocales::supported();

        $stored = [];

        foreach ($locales as $locale) {

            $result = $this->scoreLocale(

                $rules,

                $translations,

                $seo,

                $images,

                $variants,

                $attributeValues,

                $categoryId,

                $productUuid,

                $locale

            );

            $this->scoreWriteRepository->upsert(

                $productId,

                $locale,

                $result['score'],

                $result['blocking_failed'],

                $result['failed_rules']

            );

            $stored[] = [

                'locale' => $locale,

                'score' => $result['score'],

                'blocking_failed' => $result['blocking_failed'],

                'failed_rules' => $result['failed_rules'],

            ];

        }



        $this->events->dispatch(new CompletenessRecalculated($productUuid, $stored));



        return $stored;

    }



    public function recalculateForProductUuid(string $productUuid): void

    {

        $meta = $this->productReadRepository->findWorkflowMeta($productUuid);

        if ($meta === null) {

            return;

        }



        $this->recalculateAndStore($productUuid, (int) $meta['id'], (int) $meta['category_id']);

    }



    /**

     * @param list<array<string, mixed>> $rules

     * @param array<string, array<string, mixed>> $translations

     * @param array<string, array<string, mixed>> $seo

     * @param array<string, list<array<string, mixed>>> $images

     * @param array<string, list<array<string, mixed>>> $variants

     * @param list<array<string, mixed>> $attributeValues

     * @return array{score: float, blocking_failed: bool, failed_rules: list<string>}

     */

    private function scoreLocale(

        array $rules,

        array $translations,

        array $seo,

        array $images,

        array $variants,

        array $attributeValues,

        int $categoryId,

        string $productUuid,

        string $locale

    ): array {

        $totalWeight = 0.0;

        $earnedWeight = 0.0;

        $failedRules = [];

        $blockingFailed = false;



        foreach ($rules as $rule) {

            $ruleLocale = $rule['locale'] ?? null;

            if ($ruleLocale !== null && $ruleLocale !== '' && $ruleLocale !== $locale) {

                continue;

            }



            $weight = (float) ($rule['weight'] ?? 1.0);

            $totalWeight += $weight;

            $code = (string) ($rule['code'] ?? '');

            $passed = match ($code) {

                'seo_default' => $this->seoRulePassed($seo, $locale, $rule),

                'images_default' => $this->imagesRulePassed($images, $locale, $rule),

                'variants_default' => $this->variantsRulePassed($variants, $locale, $rule),

                'category_schema_default' => $this->categorySchemaRulePassed($categoryId, $productUuid, $locale, $attributeValues),

                default => $this->translationRulePassed($translations, $locale, $rule),

            };



            if ($passed) {

                $earnedWeight += $weight;

            } else {

                $failedRules[] = $code !== '' ? $code : 'unknown_rule';

                if ((bool) ($rule['is_blocking'] ?? false)) {

                    $blockingFailed = true;

                }

            }

        }



        $score = $totalWeight > 0 ? round(($earnedWeight / $totalWeight) * 100, 2) : 100.0;



        return [

            'score' => $score,

            'blocking_failed' => $blockingFailed,

            'failed_rules' => $failedRules,

        ];

    }



    /**

     * @param array<string, array<string, mixed>> $translations

     * @param array<string, mixed> $rule

     */

    private function translationRulePassed(array $translations, string $locale, array $rule): bool

    {

        $fields = is_array($rule['required_fields']) ? $rule['required_fields'] : [];

        $translation = $translations[$locale] ?? [];

        foreach ($fields as $field) {

            $value = $translation[(string) $field] ?? null;

            if ($value === null || trim((string) $value) === '') {

                return false;

            }

        }



        return true;

    }



    /**

     * @param array<string, array<string, mixed>> $seo

     * @param array<string, mixed> $rule

     */

    private function seoRulePassed(array $seo, string $locale, array $rule): bool

    {

        $fields = is_array($rule['required_fields']) ? $rule['required_fields'] : ['seo_title', 'seo_description'];

        $row = $seo[$locale] ?? [];

        foreach ($fields as $field) {

            $value = $row[(string) $field] ?? null;

            if ($value === null || trim((string) $value) === '') {

                return false;

            }

        }



        return true;

    }



    /**

     * @param array<string, list<array<string, mixed>>> $images

     * @param array<string, mixed> $rule

     */

    private function imagesRulePassed(array $images, string $locale, array $rule): bool

    {

        $fields = is_array($rule['required_fields']) ? $rule['required_fields'] : ['alt_text'];

        $rows = $images[$locale] ?? [];

        if ($rows === []) {

            return false;

        }

        foreach ($rows as $row) {

            $ok = true;

            foreach ($fields as $field) {

                $value = $row[(string) $field] ?? null;

                if ($value === null || trim((string) $value) === '') {

                    $ok = false;

                    break;

                }

            }

            if ($ok) {

                return true;

            }

        }



        return false;

    }



    /**

     * @param array<string, list<array<string, mixed>>> $variants

     * @param array<string, mixed> $rule

     */

    private function variantsRulePassed(array $variants, string $locale, array $rule): bool

    {

        $fields = is_array($rule['required_fields']) ? $rule['required_fields'] : ['name'];

        $rows = $variants[$locale] ?? [];

        if ($rows === []) {

            return false;

        }

        foreach ($rows as $row) {

            $ok = true;

            foreach ($fields as $field) {

                $value = $row[(string) $field] ?? null;

                if ($value === null || trim((string) $value) === '') {

                    $ok = false;

                    break;

                }

            }

            if ($ok) {

                return true;

            }

        }



        return false;

    }



    /**

     * @param list<array<string, mixed>> $attributeValues

     */

    private function categorySchemaRulePassed(

        int $categoryId,

        string $productUuid,

        string $locale,

        array $attributeValues

    ): bool {

        $validation = $this->categorySchemaService->validateProductAttributes(

            $categoryId,

            $productUuid,

            new LocaleContext($locale, 'en'),

            $attributeValues

        );



        return $validation['valid'];

    }



    /**

     * @param list<array<string, mixed>> $scores

     */

    private function hasBlockingFailure(array $scores): bool

    {

        foreach ($scores as $score) {

            if ((bool) ($score['blocking_failed'] ?? false)) {

                return true;

            }

        }



        return false;

    }

}


