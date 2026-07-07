<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M011RbacAuditCompleteness extends AbstractMigration
{
    public function name(): string
    {
        return '011_rbac_audit_completeness';
    }

    public function up(): void
    {
        $this->createRbacTables();
        $this->createAuditEvents();
        $this->createProductSeo();
        $this->addAuditColumns();
        $this->seedPermissionsAndRoles();
        $this->seedCompletenessSeoRule();
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS product_seo_translations');
        $this->exec('DROP TABLE IF EXISTS product_seo');
        $this->exec('DROP TABLE IF EXISTS audit_events');
        $this->exec('DROP TABLE IF EXISTS platform_user_permissions');
        $this->exec('DROP TABLE IF EXISTS platform_user_roles');
        $this->exec('DROP TABLE IF EXISTS platform_role_permissions');
        $this->exec('DROP TABLE IF EXISTS platform_permissions');
        $this->exec('DROP TABLE IF EXISTS platform_roles');
        $this->exec('DROP TABLE IF EXISTS platform_users');
    }

    private function createRbacTables(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS platform_users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NULL,
                display_name VARCHAR(150) NULL,
                status ENUM("active", "inactive", "locked") NOT NULL DEFAULT "active",
                last_login_at DATETIME(6) NULL,
                preferred_locale VARCHAR(10) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_platform_users_uuid (uuid),
                UNIQUE KEY uk_platform_users_email (email),
                KEY idx_platform_users_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS platform_roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(100) NOT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_platform_roles_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS platform_permissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(80) NOT NULL,
                description VARCHAR(255) NULL,
                module VARCHAR(50) NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_platform_permissions_slug (slug),
                KEY idx_platform_permissions_module (module)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS platform_role_permissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                role_id BIGINT UNSIGNED NOT NULL,
                permission_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_platform_role_permissions (role_id, permission_id),
                CONSTRAINT fk_platform_role_permissions_role FOREIGN KEY (role_id) REFERENCES platform_roles (id),
                CONSTRAINT fk_platform_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES platform_permissions (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS platform_user_roles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_platform_user_roles (user_id, role_id),
                CONSTRAINT fk_platform_user_roles_user FOREIGN KEY (user_id) REFERENCES platform_users (id),
                CONSTRAINT fk_platform_user_roles_role FOREIGN KEY (role_id) REFERENCES platform_roles (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS platform_user_permissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                permission_id BIGINT UNSIGNED NOT NULL,
                is_granted TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_platform_user_permissions (user_id, permission_id),
                CONSTRAINT fk_platform_user_permissions_user FOREIGN KEY (user_id) REFERENCES platform_users (id),
                CONSTRAINT fk_platform_user_permissions_permission FOREIGN KEY (permission_id) REFERENCES platform_permissions (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function createAuditEvents(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS audit_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_uuid CHAR(36) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_uuid CHAR(36) NOT NULL,
                entity_version INT UNSIGNED NULL,
                action VARCHAR(50) NOT NULL,
                actor_id BIGINT NULL,
                actor_type ENUM("platform_user", "api_key", "system") NOT NULL DEFAULT "platform_user",
                before_json JSON NULL,
                after_json JSON NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_audit_events_uuid (event_uuid),
                KEY idx_audit_events_entity (entity_type, entity_uuid),
                KEY idx_audit_events_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function createProductSeo(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_seo (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_seo_uuid (uuid),
                UNIQUE KEY uk_product_seo_product (product_id),
                CONSTRAINT fk_product_seo_product FOREIGN KEY (product_id) REFERENCES products (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_seo_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_seo_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                slug VARCHAR(255) NULL,
                seo_title VARCHAR(255) NULL,
                seo_description TEXT NULL,
                keywords TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                deleted_at DATETIME(6) NULL,
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_seo_translations_uuid (uuid),
                UNIQUE KEY uk_product_seo_translations_lang (product_seo_id, language_code),
                CONSTRAINT fk_product_seo_translations_seo FOREIGN KEY (product_seo_id) REFERENCES product_seo (id),
                CONSTRAINT fk_product_seo_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function addAuditColumns(): void
    {
        $alters = [
            'workflow_states' => ['created_by', 'updated_by', 'deleted_by', 'updated_at', 'deleted_at'],
            'workflow_transitions' => ['created_by', 'updated_by', 'deleted_by', 'updated_at', 'deleted_at'],
            'product_workflow_history' => ['created_by', 'updated_by', 'deleted_by', 'updated_at', 'deleted_at'],
            'product_versions' => ['updated_by', 'deleted_by', 'updated_at', 'deleted_at'],
            'change_request_items' => ['created_by', 'updated_by', 'deleted_by', 'updated_at', 'deleted_at'],
            'product_completeness_scores' => ['created_by', 'updated_by', 'deleted_by', 'deleted_at'],
            'change_requests' => ['created_by', 'updated_by', 'deleted_by'],
            'workflow_comments' => ['created_by', 'updated_by', 'deleted_by'],
            'completeness_rules' => ['created_by', 'updated_by', 'deleted_by'],
            'category_attribute_schemas' => ['created_by', 'updated_by', 'deleted_by'],
        ];

        foreach ($alters as $table => $columns) {
            foreach ($columns as $column) {
                $type = match ($column) {
                    'created_by', 'updated_by', 'deleted_by' => 'BIGINT UNSIGNED NULL',
                    'updated_at', 'deleted_at' => 'DATETIME(6) NULL',
                    default => 'BIGINT UNSIGNED NULL',
                };
                $this->exec(
                    'ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $type
                );
            }
        }
    }

    private function seedPermissionsAndRoles(): void
    {
        $permissions = require dirname(__DIR__, 4) . '/config/entity-permissions.php';
        foreach ($permissions as $slug => $meta) {
            $this->exec(
                'INSERT IGNORE INTO platform_permissions (slug, description, module) VALUES ('
                . $this->pdo->quote($slug) . ', '
                . $this->pdo->quote((string) ($meta['description'] ?? $slug)) . ', '
                . $this->pdo->quote((string) ($meta['module'] ?? 'catalog')) . ')'
            );
        }

        $this->exec(
            'INSERT IGNORE INTO platform_roles (code, name, is_system, status) VALUES '
            . '("super_admin", "Super Admin", 1, "active"),'
            . '("catalog_manager", "Catalog Manager", 1, "active"),'
            . '("approver", "Approver", 1, "active")'
        );

        $this->exec(
            'INSERT IGNORE INTO platform_role_permissions (role_id, permission_id)
             SELECT r.id, p.id FROM platform_roles r
             CROSS JOIN platform_permissions p
             WHERE r.code = "super_admin" AND p.deleted_at IS NULL'
        );

        $this->exec(
            'INSERT IGNORE INTO platform_users (id, uuid, email, display_name, status, preferred_locale)
             VALUES (1, "00000000-0000-4000-8000-000000000001", "system@rateb.local", "System Admin", "active", "ar")'
        );

        $this->exec(
            'INSERT IGNORE INTO platform_user_roles (user_id, role_id)
             SELECT 1, id FROM platform_roles WHERE code = "super_admin" LIMIT 1'
        );
    }

    private function seedCompletenessSeoRule(): void
    {
        $this->exec(
            'INSERT IGNORE INTO completeness_rules (code, entity_type, locale, required_fields, is_blocking, weight) VALUES ('
            . $this->pdo->quote('seo_default') . ', '
            . $this->pdo->quote('product') . ', NULL, '
            . $this->pdo->quote('["seo_title","seo_description"]') . ', 0, 1.00)'
        );
        $this->exec(
            'INSERT IGNORE INTO completeness_rules (code, entity_type, locale, required_fields, is_blocking, weight) VALUES ('
            . $this->pdo->quote('images_default') . ', '
            . $this->pdo->quote('product') . ', NULL, '
            . $this->pdo->quote('["alt_text"]') . ', 0, 1.00)'
        );
        $this->exec(
            'INSERT IGNORE INTO completeness_rules (code, entity_type, locale, required_fields, is_blocking, weight) VALUES ('
            . $this->pdo->quote('variants_default') . ', '
            . $this->pdo->quote('product') . ', NULL, '
            . $this->pdo->quote('["name"]') . ', 0, 1.00)'
        );
        $this->exec(
            'INSERT IGNORE INTO completeness_rules (code, entity_type, locale, required_fields, is_blocking, weight) VALUES ('
            . $this->pdo->quote('category_schema_default') . ', '
            . $this->pdo->quote('product') . ', NULL, '
            . $this->pdo->quote('[]') . ', 1, 2.00)'
        );
    }
}
