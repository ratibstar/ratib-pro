<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\DTO\LocaleContext;
use Rateb\PlatformCatalog\Application\Events\CompletenessRecalculated;
use Rateb\PlatformCatalog\Application\Events\EventDispatcher;
use Rateb\PlatformCatalog\Application\Policies\CategorySchemaPolicy;
use Rateb\PlatformCatalog\Application\Policies\CompletenessPolicy;
use Rateb\PlatformCatalog\Application\Policies\WorkflowPolicy;
use Rateb\PlatformCatalog\Application\Services\AuditEventService;
use Rateb\PlatformCatalog\Application\Services\CategorySchemaService;
use Rateb\PlatformCatalog\Application\Services\CompletenessService;
use Rateb\PlatformCatalog\Application\Services\ProductVersionConflictException;
use Rateb\PlatformCatalog\Application\Services\RbacService;
use Rateb\PlatformCatalog\Application\Policies\TestPolicyGuard;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\AuditEventWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategorySchemaReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CategorySchemaWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessDataReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessRuleReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\CompletenessRuleWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductAttributeWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductCompletenessReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductCompletenessWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductReadRepositoryInterface;
use Rateb\PlatformCatalog\Tests\Support\EmptyProductSeoReadRepository;
use Rateb\PlatformCatalog\Tests\Support\EmptyProductSnapshotGraphReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductRelationWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductSnapshotRestoreRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductTranslationWriteRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSnapshotRestoreRepository;

catalog_test('Enterprise: restore source applies product translations attributes relations snapshot', static function (): void {
    $source = file_get_contents(dirname(__DIR__, 3) . '/app/Infrastructure/Persistence/Repositories/MysqlProductSnapshotRestoreRepository.php');
    catalog_assert_true(is_string($source) && $source !== '');
    catalog_assert_true(str_contains($source, 'applyProductUpdate'));
    catalog_assert_true(str_contains($source, 'translationWriter->upsertForProduct'));
    catalog_assert_true(str_contains($source, 'attributeWriter->replaceForProduct'));
    catalog_assert_true(str_contains($source, 'relationWriter->replaceForProduct'));
    catalog_assert_true(str_contains($source, "INSERT INTO product_versions"));
});

catalog_test('Enterprise: completeness scores SEO, images, variants, translations, category schema', static function (): void {
    $events = new EventDispatcher();
    $dispatched = false;
    $events->listen('CompletenessRecalculated', static function (CompletenessRecalculated $event) use (&$dispatched): void {
        $dispatched = true;
        catalog_assert_same('prod-enterprise', $event->payload()['product_uuid']);
    });

    $guard = new TestPolicyGuard();
    $service = new CompletenessService(
        new class implements CompletenessRuleReadRepositoryInterface {
            public function listActive(?string $entityType = 'product'): array
            {
                return [
                    ['code' => 'name_default', 'locale' => 'en', 'required_fields' => ['name'], 'is_blocking' => false, 'weight' => 1],
                    ['code' => 'seo_default', 'locale' => null, 'required_fields' => ['seo_title'], 'is_blocking' => false, 'weight' => 1],
                    ['code' => 'images_default', 'locale' => null, 'required_fields' => ['alt_text'], 'is_blocking' => false, 'weight' => 1],
                    ['code' => 'variants_default', 'locale' => null, 'required_fields' => ['name'], 'is_blocking' => false, 'weight' => 1],
                    ['code' => 'category_schema_default', 'locale' => null, 'required_fields' => [], 'is_blocking' => true, 'weight' => 2],
                ];
            }

            public function listAll(): array
            {
                return $this->listActive();
            }

            public function findByCode(string $code): ?array
            {
                return null;
            }
        },
        new class implements CompletenessRuleWriteRepositoryInterface {
            public function updateByCode(string $code, array $data): bool
            {
                return true;
            }
        },
        new class implements ProductCompletenessReadRepositoryInterface {
            public function listByProductUuid(string $productUuid): array
            {
                return [];
            }

            public function findByProductAndLocale(string $productUuid, string $locale): ?array
            {
                return null;
            }
        },
        new class implements ProductCompletenessWriteRepositoryInterface {
            public function upsert(int $productId, string $locale, float $score, bool $blockingFailed, array $failedRules): void
            {
            }
        },
        new class implements ProductReadRepositoryInterface {
            public function findByUuid(string $uuid, LocaleContext $locale): ?array
            {
                return null;
            }

            public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
            {
                return [];
            }

            public function listFiltered(LocaleContext $locale, \Rateb\PlatformCatalog\Application\DTO\ProductListFilter $filter, int $limit = 100, int $offset = 0): array
            {
                return [];
            }

            public function listByFamilyUuid(string $familyUuid, LocaleContext $locale, int $limit = 100, int $offset = 0): array
            {
                return [];
            }

            public function findLockVersion(string $uuid): ?int
            {
                return 1;
            }

            public function findWorkflowMeta(string $uuid): ?array
            {
                return ['id' => 1, 'category_id' => 5];
            }
        },
        new class implements CompletenessDataReadRepositoryInterface {
            public function listProductTranslationsByLocale(string $productUuid): array
            {
                return ['en' => ['language_code' => 'en', 'name' => 'Name', 'description' => 'Desc']];
            }

            public function listSeoTranslationsByLocale(string $productUuid): array
            {
                return ['en' => ['seo_title' => 'Title', 'seo_description' => 'Desc']];
            }

            public function listImageTranslationsByLocale(string $productUuid): array
            {
                return ['en' => [['alt_text' => 'Alt']]];
            }

            public function listVariantTranslationsByLocale(string $productUuid): array
            {
                return ['en' => [['name' => 'Variant']]];
            }

            public function listAttributeValuesByLocale(string $productUuid): array
            {
                return [['attribute_code' => 'color', 'language_code' => 'en', 'value_text' => 'red']];
            }
        },
        new CategorySchemaService(
            new class implements CategorySchemaReadRepositoryInterface {
                public function listRequiredForCategory(int $categoryId): array
                {
                    return [];
                }

                public function listResolvedSchemaForCategory(int $categoryId): array
                {
                    return [];
                }

                public function findCategoryIdByUuid(string $categoryUuid): ?int
                {
                    return null;
                }
            },
            new class implements CategorySchemaWriteRepositoryInterface {
                public function replaceForCategory(int $categoryId, array $items, ?int $actorId = null): void
                {
                }
            },
            new class implements CompletenessDataReadRepositoryInterface {
                public function listProductTranslationsByLocale(string $productUuid): array
                {
                    return [];
                }

                public function listSeoTranslationsByLocale(string $productUuid): array
                {
                    return [];
                }

                public function listImageTranslationsByLocale(string $productUuid): array
                {
                    return [];
                }

                public function listVariantTranslationsByLocale(string $productUuid): array
                {
                    return [];
                }

                public function listAttributeValuesByLocale(string $productUuid): array
                {
                    return [];
                }
            },
            new CategorySchemaPolicy($guard),
            new AuditEventService(new class implements AuditEventWriteRepositoryInterface {
                public function append(
                    string $entityType,
                    string $entityUuid,
                    ?int $entityVersion,
                    string $action,
                    ?int $actorId,
                    string $actorType = 'platform_user',
                    ?array $before = null,
                    ?array $after = null,
                    ?string $ipAddress = null
                ): string {
                    return 'a';
                }
            })
        ),
        new CompletenessPolicy($guard),
        new \Rateb\PlatformCatalog\Application\Services\LocaleResolverService(),
        $events,
        new AuditEventService(new class implements AuditEventWriteRepositoryInterface {
            public function append(
                string $entityType,
                string $entityUuid,
                ?int $entityVersion,
                string $action,
                ?int $actorId,
                string $actorType = 'platform_user',
                ?array $before = null,
                ?array $after = null,
                ?string $ipAddress = null
            ): string {
                return 'a';
            }
        })
    );

    $scores = $service->recalculateAndStore('prod-enterprise', 1, 5);
    catalog_assert_true($dispatched);
    catalog_assert_true(count($scores) >= 1);
    foreach ($scores as $score) {
        catalog_assert_true((float) $score['score'] >= 0);
    }
});

catalog_test('Enterprise: RBAC denies unauthenticated workflow permission', static function (): void {
    unset($_SERVER['HTTP_X_PLATFORM_USER_ID'], $_SESSION['platform_user_id']);

    $rbacRepo = new class implements RbacReadRepositoryInterface {
        public function listPermissionSlugsForUser(int $userId): array
        {
            return ['catalog.workflow.submit'];
        }

        public function userIsActive(int $userId): bool
        {
            return true;
        }

        public function findActiveUserIdByUuid(string $uuid): ?int
        {
            return null;
        }

        public function findActiveUserIdByEmail(string $email): ?int
        {
            return null;
        }
    };
    $rbac = new RbacService($rbacRepo);

    $guard = buildSessionRbacPolicyGuard($rbac, $rbacRepo);
    catalog_assert_false($guard->allows('catalog.workflow.publish'));

    try {
        (new WorkflowPolicy($guard))->publish();
        throw new RuntimeException('Expected forbidden');
    } catch (RuntimeException $e) {
        catalog_assert_same(403, $e->getCode());
    }
});

catalog_test('Enterprise: RBAC resolves user role permission chain', static function (): void {
    $rbac = new RbacService(new class implements RbacReadRepositoryInterface {
        public function listPermissionSlugsForUser(int $userId): array
        {
            return $userId === 42 ? ['catalog.workflow.publish'] : [];
        }

        public function userIsActive(int $userId): bool
        {
            return $userId === 42;
        }

        public function findActiveUserIdByUuid(string $uuid): ?int
        {
            return null;
        }

        public function findActiveUserIdByEmail(string $email): ?int
        {
            return null;
        }
    });

    catalog_assert_true($rbac->userHasPermission(42, 'catalog.workflow.publish'));
    catalog_assert_false($rbac->userHasPermission(99, 'catalog.workflow.publish'));
});

catalog_test('Enterprise: change request apply rejects stale product version', static function (): void {
    $write = new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChangeRequestWriteRepositoryInterface {
        public function create(int $productId, string $requestType, array $proposedChanges, int $currentVersion, ?int $submittedBy, array $items): string
        {
            return '';
        }

        public function assignReviewer(string $uuid, int $reviewerId): bool
        {
            return true;
        }

        public function approve(string $uuid, ?int $reviewedBy, ?string $note): bool
        {
            return true;
        }

        public function reject(string $uuid, ?int $reviewedBy, ?string $note): bool
        {
            return true;
        }

        public function markApplied(string $uuid): bool
        {
            return true;
        }

        public function applyApproved(
            string $uuid,
            string $productUuid,
            int $expectedLockVersion,
            int $expectedCurrentVersion,
            array $productData,
            array $translations,
            ?array $seoData,
            array $versionSnapshot,
            ?int $actorId
        ): array {
            throw new RuntimeException('stale_change_request_version', 409);
        }
    };

    $service = new \Rateb\PlatformCatalog\Application\Services\ChangeRequestService(
        new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ChangeRequestReadRepositoryInterface {
            public function findByUuid(string $uuid): ?array
            {
                return [
                    'id' => 1,
                    'uuid' => $uuid,
                    'product_uuid' => 'p1',
                    'status' => 'approved',
                    'current_version' => 3,
                    'proposed_changes' => ['sku' => 'X'],
                ];
            }

            public function list(?string $status = null, int $limit = 100, int $offset = 0): array
            {
                return [];
            }

            public function listItems(int $changeRequestId): array
            {
                return [];
            }
        },
        $write,
        new class implements ProductReadRepositoryInterface {
            public function findByUuid(string $uuid, LocaleContext $locale): ?array
            {
                return null;
            }

            public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
            {
                return [];
            }

            public function listFiltered(LocaleContext $locale, \Rateb\PlatformCatalog\Application\DTO\ProductListFilter $filter, int $limit = 100, int $offset = 0): array
            {
                return [];
            }

            public function listByFamilyUuid(string $familyUuid, LocaleContext $locale, int $limit = 100, int $offset = 0): array
            {
                return [];
            }

            public function findLockVersion(string $uuid): ?int
            {
                return 1;
            }

            public function findWorkflowMeta(string $uuid): ?array
            {
                return null;
            }
        },
        new \Rateb\PlatformCatalog\Application\Services\ProductSnapshotBuilder(
            new class implements ProductReadRepositoryInterface {
                public function findByUuid(string $uuid, LocaleContext $locale): ?array
                {
                    return [];
                }

                public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
                {
                    return [];
                }

                public function listFiltered(LocaleContext $locale, \Rateb\PlatformCatalog\Application\DTO\ProductListFilter $filter, int $limit = 100, int $offset = 0): array
                {
                    return [];
                }

                public function listByFamilyUuid(string $familyUuid, LocaleContext $locale, int $limit = 100, int $offset = 0): array
                {
                    return [];
                }

                public function findLockVersion(string $uuid): ?int
                {
                    return 1;
                }

                public function findWorkflowMeta(string $uuid): ?array
                {
                    return null;
                }
            },
            new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductTranslationReadRepositoryInterface {
                public function listForProduct(string $productUuid, LocaleContext $locale): array
                {
                    return [];
                }
            },
            new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductAttributeReadRepositoryInterface {
                public function findByUuid(string $uuid, LocaleContext $locale): ?array
                {
                    return null;
                }

                public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
                {
                    return [];
                }

                public function listByProductUuid(string $productUuid, LocaleContext $locale): array
                {
                    return [];
                }

                public function listTranslationsGrouped(array $productAttributeIds): array
                {
                    return [];
                }
            },
            new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\ProductRelationReadRepositoryInterface {
                public function findByUuid(string $uuid, LocaleContext $locale): ?array
                {
                    return null;
                }

                public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
                {
                    return [];
                }

                public function listByProductUuid(string $productUuid, LocaleContext $locale): array
                {
                    return [];
                }
            },
            new EmptyProductSeoReadRepository(),
            new class implements CompletenessDataReadRepositoryInterface {
                public function listProductTranslationsByLocale(string $productUuid): array
                {
                    return [];
                }

                public function listSeoTranslationsByLocale(string $productUuid): array
                {
                    return [];
                }

                public function listImageTranslationsByLocale(string $productUuid): array
                {
                    return [];
                }

                public function listVariantTranslationsByLocale(string $productUuid): array
                {
                    return [];
                }

                public function listAttributeValuesByLocale(string $productUuid): array
                {
                    return [];
                }
            },
            new EmptyProductSnapshotGraphReadRepository()
        ),
        new \Rateb\PlatformCatalog\Application\Services\WorkflowCommentService(
            new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentReadRepositoryInterface {
                public function listByEntity(string $entityType, string $entityUuid, int $limit = 100): array
                {
                    return [];
                }
            },
            new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentWriteRepositoryInterface {
                public function add(
                    string $entityType,
                    int $entityId,
                    string $entityUuid,
                    string $workflowAction,
                    ?string $fromStatus,
                    ?string $toStatus,
                    string $comment,
                    ?int $commentedBy
                ): string {
                    return '';
                }
            },
            new class implements ProductReadRepositoryInterface {
                public function findByUuid(string $uuid, LocaleContext $locale): ?array
                {
                    return null;
                }

                public function list(LocaleContext $locale, int $limit = 100, int $offset = 0): array
                {
                    return [];
                }

                public function listFiltered(LocaleContext $locale, \Rateb\PlatformCatalog\Application\DTO\ProductListFilter $filter, int $limit = 100, int $offset = 0): array
                {
                    return [];
                }

                public function listByFamilyUuid(string $familyUuid, LocaleContext $locale, int $limit = 100, int $offset = 0): array
                {
                    return [];
                }

                public function findLockVersion(string $uuid): ?int
                {
                    return 1;
                }

                public function findWorkflowMeta(string $uuid): ?array
                {
                    return null;
                }
            },
            new WorkflowPolicy(new TestPolicyGuard()),
            new AuditEventService(new class implements AuditEventWriteRepositoryInterface {
                public function append(
                    string $entityType,
                    string $entityUuid,
                    ?int $entityVersion,
                    string $action,
                    ?int $actorId,
                    string $actorType = 'platform_user',
                    ?array $before = null,
                    ?array $after = null,
                    ?string $ipAddress = null
                ): string {
                    return 'a';
                }
            })
        ),
        new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\WorkflowCommentReadRepositoryInterface {
            public function listByEntity(string $entityType, string $entityUuid, int $limit = 100): array
            {
                return [];
            }
        },
        new \Rateb\PlatformCatalog\Application\Policies\ChangeRequestPolicy(new TestPolicyGuard()),
        new \Rateb\PlatformCatalog\Application\Services\ConcurrencyService(),
        new AuditEventService(new class implements AuditEventWriteRepositoryInterface {
            public function append(
                string $entityType,
                string $entityUuid,
                ?int $entityVersion,
                string $action,
                ?int $actorId,
                string $actorType = 'platform_user',
                ?array $before = null,
                ?array $after = null,
                ?string $ipAddress = null
            ): string {
                return '';
            }
        }),
        new \Rateb\PlatformCatalog\Application\Services\LocaleResolverService(),
        new EventDispatcher()
    );

    try {
        $service->apply('cr-1', ['lock_version' => 1]);
        throw new RuntimeException('Expected stale version');
    } catch (RuntimeException $e) {
        catalog_assert_same(409, $e->getCode());
        catalog_assert_same('stale_change_request_version', $e->getMessage());
    }
});
