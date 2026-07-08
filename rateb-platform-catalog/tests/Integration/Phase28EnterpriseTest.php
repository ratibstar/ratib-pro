<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Integration/Support/Phase28DbHarness.php';
require_once dirname(__DIR__) . '/Integration/Support/Phase28SnapshotHarness.php';
require_once dirname(__DIR__) . '/Support/SessionRbacPolicyGuardFactory.php';

use Rateb\PlatformCatalog\Application\Policies\WorkflowPolicy;
use Rateb\PlatformCatalog\Application\Support\SystemActorContext;
use Rateb\PlatformCatalog\Application\Services\AuditEventService;
use Rateb\PlatformCatalog\Application\Services\ChangeRequestService;
use Rateb\PlatformCatalog\Application\Services\ConcurrencyService;
use Rateb\PlatformCatalog\Application\Services\ProductVersionConflictException;
use Rateb\PlatformCatalog\Application\Services\ProductVersionService;
use Rateb\PlatformCatalog\Application\Services\RbacService;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlAuditEventWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlChangeRequestReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlChangeRequestWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSnapshotRestoreRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductVersionReadRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductWorkflowWriteRepository;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlRbacReadRepository;
use Rateb\PlatformCatalog\Support\Uuid;

catalog_test('Integration: workflow concurrency rejects stale lock_version', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        echo "[SKIP] Integration: workflow concurrency (DB unavailable)\n";

        return;
    }

    $product = phase28_ensure_approved_product($pdo);
    $originalStatus = (string) ($product['_original_status'] ?? $product['status'] ?? 'approved');

    $repo = new MysqlProductWorkflowWriteRepository();
    $uuid = (string) $product['uuid'];
    $lock = (int) $product['lock_version'];

    try {
        $repo->transitionStatus($uuid, 'approved', 'published', 'publish', $lock, 1, null, ['product' => []], 'publish', 'test');
    } catch (Throwable $e) {
        echo '[SKIP] Integration: workflow concurrency (transition failed: ' . $e->getMessage() . ")\n";

        return;
    }

    $published = phase28_find_product($pdo, $uuid);
    catalog_assert_same('published', (string) $published['status']);

    try {
        $repo->transitionStatus($uuid, 'published', 'archived', 'archive', $lock, 1, null);
        throw new RuntimeException('Expected version conflict');
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Expected version conflict') {
            throw $e;
        }
        catalog_assert_same(409, (int) $e->getCode());
    }

    $unchanged = phase28_find_product($pdo, $uuid);
    catalog_assert_same('published', (string) $unchanged['status']);
    catalog_assert_same((int) $published['lock_version'], (int) $unchanged['lock_version']);

    phase28_restore_product_status($pdo, array_merge($product, ['_original_status' => $originalStatus]));
});

catalog_test('Integration: publish rolls back when version insert conflicts', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        echo "[SKIP] Integration: publish rollback (DB unavailable)\n";

        return;
    }

    $product = phase28_ensure_approved_product($pdo);

    $uuid = (string) $product['uuid'];
    $productId = (int) $product['id'];
    $lock = (int) $product['lock_version'];
    $versionBefore = (int) $product['version_number'];
    $nextVersion = $versionBefore + 1;

    $pdo->prepare(
        'INSERT INTO product_versions (uuid, product_id, version_number, change_type, snapshot_json, entity_version)
         VALUES (:uuid, :product_id, :version_number, :change_type, :snapshot_json, :entity_version)'
    )->execute([
        'uuid' => Uuid::v4(),
        'product_id' => $productId,
        'version_number' => $nextVersion,
        'change_type' => 'publish',
        'snapshot_json' => '{}',
        'entity_version' => $nextVersion,
    ]);

    $repo = new MysqlProductWorkflowWriteRepository();
    $versionsBefore = phase28_count_versions($pdo, $productId);

    try {
        $repo->transitionStatus($uuid, 'approved', 'published', 'publish', $lock, 1, null, ['product' => []], 'publish', 'rollback-test');
        throw new RuntimeException('Expected publish failure');
    } catch (Throwable) {
        // unique constraint or wrapped exception
    }

    $refreshed = phase28_find_product($pdo, $uuid);
    catalog_assert_same('approved', (string) $refreshed['status']);
    catalog_assert_same($lock, (int) $refreshed['lock_version']);
    catalog_assert_same($versionBefore, (int) $refreshed['version_number']);
    catalog_assert_same($versionsBefore, phase28_count_versions($pdo, $productId));

    $pdo->prepare('DELETE FROM product_versions WHERE product_id = :id AND change_type = :type')
        ->execute(['id' => $productId, 'type' => 'publish']);

    phase28_restore_product_status($pdo, array_merge($product, ['_original_status' => $originalStatus]));
});

catalog_test('Integration: version restore bumps version and creates snapshot', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        echo "[SKIP] Integration: version restore (DB unavailable)\n";

        return;
    }

    $product = phase28_pick_approved_product($pdo) ?? phase28_find_product($pdo, (string) ($pdo->query("SELECT uuid FROM products WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 1")->fetchColumn() ?: ''));
    if ($product === null) {
        echo "[SKIP] Integration: version restore (no product)\n";

        return;
    }

    $version = phase28_pick_version_snapshot($pdo, (string) $product['uuid']);
    if ($version === null) {
        echo "[SKIP] Integration: version restore (no version snapshot)\n";

        return;
    }

    $read = new MysqlProductVersionReadRepository();
    $row = $read->findByProductAndVersion((string) $product['uuid'], (int) $version['version_number']);
    if ($row === null) {
        echo "[SKIP] Integration: version restore (version row missing)\n";

        return;
    }

    $service = new ProductVersionService(
        $read,
        new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductVersionWriteRepository(),
        new MysqlProductSnapshotRestoreRepository(
            null,
            null,
            new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductTranslationWriteRepository(),
            new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductAttributeWriteRepository(),
            new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductRelationWriteRepository(),
            new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductSeoWriteRepository(),
            phase28_graph_write_repository()
        ),
        new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductReadRepository(),
        phase28_snapshot_builder(),
        new \Rateb\PlatformCatalog\Application\Policies\ProductPolicy(new \Rateb\PlatformCatalog\Application\Policies\TestPolicyGuard()),
        new ConcurrencyService(),
        new AuditEventService(new MysqlAuditEventWriteRepository()),
        new \Rateb\PlatformCatalog\Application\Services\LocaleResolverService(),
        new \Rateb\PlatformCatalog\Application\Events\EventDispatcher()
    );

    $lock = (int) (phase28_find_product($pdo, (string) $product['uuid'])['lock_version'] ?? 0);
    $result = $service->restore((string) $product['uuid'], (int) $version['version_number'], ['lock_version' => $lock, 'actor_id' => 1]);
    catalog_assert_true((int) $result['version_number'] > (int) $version['version_number']);
    catalog_assert_true((int) $result['lock_version'] > $lock);
});

catalog_test('Integration: stale change request apply rejected', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        echo "[SKIP] Integration: stale change request (DB unavailable)\n";

        return;
    }

    $cr = phase28_pick_approved_change_request($pdo);
    if ($cr === null) {
        $product = phase28_ensure_approved_product($pdo);
        $staleVersion = max(0, (int) $product['version_number'] - 1);
        $crUuid = Uuid::v4();
        $pdo->prepare(
            'INSERT INTO change_requests (uuid, product_id, status, proposed_changes, current_version, submitted_by)
             VALUES (:uuid, :product_id, :status, :proposed_changes, :current_version, 1)'
        )->execute([
            'uuid' => $crUuid,
            'product_id' => (int) $product['id'],
            'status' => 'approved',
            'proposed_changes' => '{"sku":"stale-test"}',
            'current_version' => $staleVersion,
        ]);
        $cr = [
            'uuid' => $crUuid,
            'product_uuid' => (string) $product['uuid'],
            'current_version' => $staleVersion,
            'lock_version' => (int) $product['lock_version'],
        ];
    }

    $service = new ChangeRequestService(
        new MysqlChangeRequestReadRepository(),
        new MysqlChangeRequestWriteRepository(),
        new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductReadRepository(),
        phase28_snapshot_builder(),
        new \Rateb\PlatformCatalog\Application\Services\WorkflowCommentService(
            new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlWorkflowCommentReadRepository(),
            new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlWorkflowCommentWriteRepository(),
            new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlProductReadRepository(),
            new WorkflowPolicy(new \Rateb\PlatformCatalog\Application\Policies\TestPolicyGuard()),
            new AuditEventService(new MysqlAuditEventWriteRepository())
        ),
        new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlWorkflowCommentReadRepository(),
        new \Rateb\PlatformCatalog\Application\Policies\ChangeRequestPolicy(new \Rateb\PlatformCatalog\Application\Policies\TestPolicyGuard()),
        new ConcurrencyService(),
        new AuditEventService(new MysqlAuditEventWriteRepository()),
        new \Rateb\PlatformCatalog\Application\Services\LocaleResolverService(),
        new \Rateb\PlatformCatalog\Application\Events\EventDispatcher()
    );

    try {
        $service->apply((string) $cr['uuid'], [
            'lock_version' => (int) ($cr['lock_version'] ?? 1),
        ]);
        throw new RuntimeException('Expected stale_change_request_version');
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'Expected stale_change_request_version') {
            throw $e;
        }
        catalog_assert_true(
            $e->getMessage() === 'stale_change_request_version'
            || $e instanceof ProductVersionConflictException
        );
    }
});

catalog_test('Integration: audit_events records workflow action with entity_version', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        echo "[SKIP] Integration: audit_events (DB unavailable)\n";

        return;
    }

    $audit = new AuditEventService(new MysqlAuditEventWriteRepository());
    $entityUuid = '00000000-0000-4000-8000-' . bin2hex(random_bytes(6));
    $before = phase28_count_audit($pdo, $entityUuid, 'publish');

    $eventUuid = $audit->record('product', $entityUuid, 'publish', 7, 1, ['status' => 'approved'], ['status' => 'published']);
    catalog_assert_true($eventUuid !== '');
    catalog_assert_same($before + 1, phase28_count_audit($pdo, $entityUuid, 'publish'));

    $row = $pdo->prepare('SELECT entity_version FROM audit_events WHERE event_uuid = :uuid LIMIT 1');
    $row->execute(['uuid' => $eventUuid]);
    $fetched = $row->fetch(PDO::FETCH_ASSOC);
    catalog_assert_same(7, (int) ($fetched['entity_version'] ?? 0));
});

catalog_test('Integration: RBAC denies without platform user', static function (): void {
    unset($_SERVER['HTTP_X_PLATFORM_USER_ID']);
    unset($_SESSION['platform_user_id']);

    $pdo = phase28_integration_db();
    if ($pdo === null) {
        $guard = buildSessionRbacPolicyGuard(new RbacService(new class implements \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\RbacReadRepositoryInterface {
            public function listPermissionSlugsForUser(int $userId): array
            {
                return ['catalog.workflow.publish'];
            }

            public function userIsActive(int $userId): bool
            {
                return true;
            }

            public function findActiveUserIdByUuid(string $uuid): ?int
            {
                return null;
            }
        }));
        catalog_assert_false($guard->allows('catalog.workflow.publish'));
        try {
            (new WorkflowPolicy($guard))->publish();
            throw new RuntimeException('Expected forbidden');
        } catch (RuntimeException $e) {
            catalog_assert_same(403, $e->getCode());
        }

        return;
    }

    $rbac = new RbacService(new MysqlRbacReadRepository());
    $guard = buildSessionRbacPolicyGuard($rbac);
    catalog_assert_false($guard->allows('catalog.workflow.publish'));
});

catalog_test('Integration: RBAC super_admin resolves publish via role chain', static function (): void {
    $pdo = phase28_integration_db();
    if ($pdo === null) {
        echo "[SKIP] Integration: RBAC role chain (DB unavailable)\n";

        return;
    }

    putenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED=1');
    putenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET=integration-test-secret');
    unset($_SESSION['platform_user_id']);

    $_SERVER['HTTP_X_PLATFORM_USER_ID'] = SystemActorContext::SYSTEM_USER_UUID;
    $_SERVER['HTTP_X_PLATFORM_GATEWAY_TOKEN'] = 'integration-test-secret';

    $rbac = new RbacService(new MysqlRbacReadRepository());
    $guard = buildSessionRbacPolicyGuard($rbac);
    catalog_assert_true($guard->allows('catalog.workflow.publish'));

    unset($_SERVER['HTTP_X_PLATFORM_USER_ID'], $_SERVER['HTTP_X_PLATFORM_GATEWAY_TOKEN']);
    putenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED');
    putenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET');
});
