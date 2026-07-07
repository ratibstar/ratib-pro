<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M010WorkflowVersioning extends AbstractMigration
{
    public function name(): string
    {
        return '010_workflow_versioning';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS workflow_states (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(30) NOT NULL,
                name VARCHAR(80) NOT NULL,
                is_terminal TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_workflow_states_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS workflow_transitions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                from_state VARCHAR(30) NOT NULL,
                to_state VARCHAR(30) NOT NULL,
                action VARCHAR(30) NOT NULL,
                requires_permission VARCHAR(80) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_workflow_transitions (from_state, action),
                KEY idx_workflow_transitions_to (to_state)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_workflow_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                product_uuid CHAR(36) NOT NULL,
                from_status VARCHAR(30) NULL,
                to_status VARCHAR(30) NOT NULL,
                action VARCHAR(30) NOT NULL,
                actor_id BIGINT UNSIGNED NULL,
                comment TEXT NULL,
                entity_version INT UNSIGNED NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_product_workflow_history_uuid (uuid),
                KEY idx_product_workflow_history_product (product_id),
                KEY idx_product_workflow_history_uuid (product_uuid),
                CONSTRAINT fk_product_workflow_history_product FOREIGN KEY (product_id) REFERENCES products (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS workflow_comments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id BIGINT UNSIGNED NOT NULL,
                entity_uuid CHAR(36) NOT NULL,
                workflow_action ENUM(
                    "submit", "approve", "reject", "publish", "unpublish", "archive", "restore"
                ) NOT NULL,
                from_status VARCHAR(30) NULL,
                to_status VARCHAR(30) NULL,
                comment TEXT NOT NULL,
                commented_by BIGINT UNSIGNED NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                UNIQUE KEY uk_workflow_comments_uuid (uuid),
                KEY idx_workflow_comments_entity (entity_type, entity_id),
                KEY idx_workflow_comments_entity_uuid (entity_uuid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_versions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                version_number INT UNSIGNED NOT NULL,
                change_type ENUM("create", "update", "publish", "archive", "restore") NOT NULL,
                change_summary VARCHAR(500) NULL,
                snapshot_json JSON NOT NULL,
                entity_version INT UNSIGNED NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_versions_uuid (uuid),
                UNIQUE KEY uk_product_versions_product_version (product_id, version_number),
                KEY idx_product_versions_product (product_id),
                CONSTRAINT fk_product_versions_product FOREIGN KEY (product_id) REFERENCES products (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS change_requests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                request_type ENUM("create", "update", "delete") NOT NULL DEFAULT "update",
                status ENUM("pending", "in_review", "approved", "rejected", "applied", "cancelled") NOT NULL DEFAULT "pending",
                proposed_changes JSON NOT NULL,
                current_version INT UNSIGNED NOT NULL,
                submitted_by BIGINT UNSIGNED NULL,
                reviewer_id BIGINT UNSIGNED NULL,
                reviewed_by BIGINT UNSIGNED NULL,
                reviewed_at DATETIME(6) NULL,
                applied_at DATETIME(6) NULL,
                review_note TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                UNIQUE KEY uk_change_requests_uuid (uuid),
                KEY idx_change_requests_product_status (product_id, status),
                KEY idx_change_requests_status (status),
                CONSTRAINT fk_change_requests_product FOREIGN KEY (product_id) REFERENCES products (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS change_request_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                change_request_id BIGINT UNSIGNED NOT NULL,
                field_path VARCHAR(150) NOT NULL,
                old_value JSON NULL,
                new_value JSON NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_change_request_items_uuid (uuid),
                KEY idx_change_request_items_request (change_request_id),
                CONSTRAINT fk_change_request_items_request FOREIGN KEY (change_request_id) REFERENCES change_requests (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS category_attribute_schemas (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                category_id BIGINT UNSIGNED NOT NULL,
                attribute_id BIGINT UNSIGNED NOT NULL,
                is_required TINYINT(1) NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                inheritance ENUM("none", "inherit", "inherit_required") NOT NULL DEFAULT "none",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                UNIQUE KEY uk_category_attribute_schemas_uuid (uuid),
                UNIQUE KEY uk_category_attribute_schemas (category_id, attribute_id),
                KEY idx_category_attribute_schemas_category (category_id),
                CONSTRAINT fk_category_attribute_schemas_category FOREIGN KEY (category_id) REFERENCES categories (id),
                CONSTRAINT fk_category_attribute_schemas_attribute FOREIGN KEY (attribute_id) REFERENCES attributes (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS completeness_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(80) NOT NULL,
                entity_type VARCHAR(50) NOT NULL DEFAULT "product",
                locale VARCHAR(10) NULL,
                required_fields JSON NOT NULL,
                is_blocking TINYINT(1) NOT NULL DEFAULT 1,
                weight DECIMAL(5,2) NOT NULL DEFAULT 1.00,
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                UNIQUE KEY uk_completeness_rules_code (code),
                KEY idx_completeness_rules_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_completeness_scores (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id BIGINT UNSIGNED NOT NULL,
                locale VARCHAR(10) NOT NULL,
                score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                blocking_failed TINYINT(1) NOT NULL DEFAULT 0,
                failed_rules JSON NULL,
                computed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_product_completeness_scores (product_id, locale),
                KEY idx_product_completeness_scores_blocking (blocking_failed),
                CONSTRAINT fk_product_completeness_scores_product FOREIGN KEY (product_id) REFERENCES products (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->seedWorkflow();
        $this->seedCompletenessRules();
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS product_completeness_scores');
        $this->exec('DROP TABLE IF EXISTS completeness_rules');
        $this->exec('DROP TABLE IF EXISTS category_attribute_schemas');
        $this->exec('DROP TABLE IF EXISTS change_request_items');
        $this->exec('DROP TABLE IF EXISTS change_requests');
        $this->exec('DROP TABLE IF EXISTS product_versions');
        $this->exec('DROP TABLE IF EXISTS workflow_comments');
        $this->exec('DROP TABLE IF EXISTS product_workflow_history');
        $this->exec('DROP TABLE IF EXISTS workflow_transitions');
        $this->exec('DROP TABLE IF EXISTS workflow_states');
    }

    private function seedWorkflow(): void
    {
        $states = [
            ['draft', 'Draft', 0, 1],
            ['pending_review', 'Pending Review', 0, 2],
            ['approved', 'Approved', 0, 3],
            ['published', 'Published', 0, 4],
            ['archived', 'Archived', 1, 5],
            ['rejected', 'Rejected', 0, 6],
        ];
        foreach ($states as [$code, $name, $terminal, $sort]) {
            $this->exec(
                'INSERT IGNORE INTO workflow_states (code, name, is_terminal, sort_order) VALUES ('
                . $this->pdo->quote($code) . ', '
                . $this->pdo->quote($name) . ', '
                . (int) $terminal . ', '
                . (int) $sort . ')'
            );
        }

        $transitions = [
            ['draft', 'pending_review', 'submit', 'catalog.workflow.submit'],
            ['pending_review', 'approved', 'approve', 'catalog.workflow.approve'],
            ['pending_review', 'rejected', 'reject', 'catalog.workflow.approve'],
            ['rejected', 'draft', 'restore', 'catalog.workflow.submit'],
            ['approved', 'published', 'publish', 'catalog.workflow.publish'],
            ['published', 'archived', 'archive', 'catalog.workflow.publish'],
            ['archived', 'draft', 'restore', 'catalog.workflow.publish'],
        ];
        foreach ($transitions as [$from, $to, $action, $perm]) {
            $this->exec(
                'INSERT IGNORE INTO workflow_transitions (from_state, to_state, action, requires_permission) VALUES ('
                . $this->pdo->quote($from) . ', '
                . $this->pdo->quote($to) . ', '
                . $this->pdo->quote($action) . ', '
                . $this->pdo->quote($perm) . ')'
            );
        }
    }

    private function seedCompletenessRules(): void
    {
        $rules = [
            ['name_default', 'product', 'en', '["name"]', 1, 2.00],
            ['name_ar', 'product', 'ar', '["name"]', 1, 2.00],
            ['description_default', 'product', null, '["short_description","description"]', 0, 1.00],
        ];
        foreach ($rules as [$code, $entity, $locale, $fields, $blocking, $weight]) {
            $localeSql = $locale === null ? 'NULL' : $this->pdo->quote($locale);
            $this->exec(
                'INSERT IGNORE INTO completeness_rules (code, entity_type, locale, required_fields, is_blocking, weight) VALUES ('
                . $this->pdo->quote($code) . ', '
                . $this->pdo->quote($entity) . ', '
                . $localeSql . ', '
                . $this->pdo->quote($fields) . ', '
                . (int) $blocking . ', '
                . $weight . ')'
            );
        }
    }
}
