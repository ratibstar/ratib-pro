<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations\MigrationRunner;

catalog_test('MigrationRunner normalizes SQL filenames', static function (): void {
    catalog_assert_same('001_catalog_migrations', MigrationRunner::normalizeMigrationKey('001_catalog_migrations.sql'));
    catalog_assert_same('002_reference_data', MigrationRunner::normalizeMigrationKey('002_reference_data'));
});

catalog_test('MigrationRunner normalizes PHP migration names', static function (): void {
    catalog_assert_same('003_taxonomy', MigrationRunner::normalizeMigrationKey('003_taxonomy.php'));
    catalog_assert_same('006_product_relationships', MigrationRunner::normalizeMigrationKey('006_product_relationships'));
    catalog_assert_same('007_media_assets', MigrationRunner::normalizeMigrationKey('007_media_assets'));
    catalog_assert_same('008_queue_search', MigrationRunner::normalizeMigrationKey('008_queue_search'));
    catalog_assert_same('009_queue_worker_reliability', MigrationRunner::normalizeMigrationKey('009_queue_worker_reliability'));
    catalog_assert_same('010_workflow_versioning', MigrationRunner::normalizeMigrationKey('010_workflow_versioning.php'));
    catalog_assert_same('011_rbac_audit_completeness', MigrationRunner::normalizeMigrationKey('011_rbac_audit_completeness.php'));
});
