<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Integration/Support/Phase28DbHarness.php';

use Rateb\PlatformCatalog\Application\CatalogServiceProvider;
use Rateb\PlatformCatalog\Application\Services\ScheduledPublishService;
use Rateb\PlatformCatalog\Application\Services\WorkflowService;
use Rateb\PlatformCatalog\Application\Support\SystemActorContext;
use Rateb\PlatformCatalog\Core\Container;

catalog_test('Integration: scheduled publish executes full workflow pipeline', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        throw new RuntimeException('Integration DB required for scheduled publish E2E test');
    }

    $product = phase28_ensure_approved_product($pdo);
    $uuid = (string) $product['uuid'];
    $productId = (int) $product['id'];
    $originalStatus = (string) ($product['_original_status'] ?? $product['status'] ?? 'approved');

    $pdo->prepare(
        "UPDATE products SET status = 'approved', publish_at = '2000-01-01 00:00:00.000000' WHERE uuid = :uuid"
    )->execute(['uuid' => $uuid]);

    $pdo->prepare('DELETE FROM job_queue WHERE idempotency_key = :key')->execute([
        'key' => 'search_reindex:' . $uuid,
    ]);
    $pdo->prepare(
        'DELETE FROM search_index_queue WHERE entity_type = "product" AND entity_uuid = :uuid'
    )->execute(['uuid' => $uuid]);

    $before = phase28_find_product($pdo, $uuid);
    if ($before === null) {
        throw new RuntimeException('Product not found for scheduled publish E2E test');
    }

    $versionBefore = (int) $before['version_number'];
    $historyBefore = phase28_count_workflow_history($pdo, $uuid, 'publish');
    $auditBefore = phase28_count_audit($pdo, $uuid, 'scheduled_publish');
    $searchQueueBefore = phase28_count_search_index_queue($pdo, $uuid);
    $jobsBefore = phase28_count_job_queue_reindex($pdo, $uuid);
    $versionsBefore = phase28_count_versions($pdo, $productId);

    $container = new Container();
    CatalogServiceProvider::register($container);
    $container->get(ScheduledPublishService::class)->processDue();

    $after = phase28_find_product($pdo, $uuid);
    if ($after === null) {
        throw new RuntimeException('Product missing after scheduled publish');
    }

    catalog_assert_same('published', (string) $after['status'], 'Product status after scheduled publish');

    $publishAtRow = $pdo->prepare('SELECT publish_at FROM products WHERE uuid = :uuid LIMIT 1');
    $publishAtRow->execute(['uuid' => $uuid]);
    $publishAt = $publishAtRow->fetch(PDO::FETCH_ASSOC);
    catalog_assert_true(
        !is_array($publishAt) || ($publishAt['publish_at'] ?? null) === null,
        'publish_at must be cleared'
    );

    catalog_assert_true((int) $after['version_number'] > $versionBefore, 'version_number must increase');
    catalog_assert_true(phase28_count_versions($pdo, $productId) > $versionsBefore, 'product_versions row must be created');
    catalog_assert_true(phase28_count_workflow_history($pdo, $uuid, 'publish') > $historyBefore, 'workflow history publish row must be created');
    catalog_assert_true(phase28_count_audit($pdo, $uuid, 'scheduled_publish') > $auditBefore, 'scheduled_publish audit event must be created');
    catalog_assert_true(phase28_count_search_index_queue($pdo, $uuid) > $searchQueueBefore, 'search_index_queue entries must be enqueued');
    catalog_assert_true(phase28_count_job_queue_reindex($pdo, $uuid) > $jobsBefore, 'search_reindex job must be enqueued');
    catalog_assert_true(phase28_count_completeness_scores($pdo, $productId) > 0, 'completeness scores must exist');

    $historyStmt = $pdo->prepare(
        'SELECT comment FROM product_workflow_history
         WHERE product_uuid = :uuid AND action = :action
         ORDER BY id DESC LIMIT 1'
    );
    $historyStmt->execute(['uuid' => $uuid, 'action' => 'publish']);
    $historyRow = $historyStmt->fetch(PDO::FETCH_ASSOC);
    catalog_assert_true(is_array($historyRow));
    catalog_assert_same('Scheduled publish', (string) ($historyRow['comment'] ?? ''));

    $historyItems = [];
    SystemActorContext::runAsSystem(static function () use ($container, $uuid, &$historyItems): void {
        $historyItems = $container->get(WorkflowService::class)->history($uuid, 10);
    });
    catalog_assert_true($historyItems !== []);

    $pdo->prepare('UPDATE products SET status = :status, publish_at = NULL WHERE uuid = :uuid')->execute([
        'status' => $originalStatus,
        'uuid' => $uuid,
    ]);
});

catalog_test('Integration: workflow history endpoint data is readable from service', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        throw new RuntimeException('Integration DB required for workflow history integration test');
    }

    $row = $pdo->query(
        'SELECT uuid FROM products WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new RuntimeException('No product available for workflow history integration test');
    }

    $container = new Container();
    CatalogServiceProvider::register($container);
    $historyItems = [];
    SystemActorContext::runAsSystem(static function () use ($container, &$historyItems, $row): void {
        $historyItems = $container->get(WorkflowService::class)->history((string) $row['uuid'], 5);
    });

    catalog_assert_true(is_array($historyItems));
});
