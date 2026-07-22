-- RATEB Contact Center — 018 knowledge base (Phase 10F)

CREATE TABLE IF NOT EXISTS rcc_kb_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(128) NOT NULL,
    name_ar VARCHAR(128) NULL,
    parent_id INT UNSIGNED NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_kb_cat_code (tenant_id, code),
    CONSTRAINT fk_rcc_kb_cat_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_kb_articles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    slug VARCHAR(128) NOT NULL,
    title VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NULL,
    body TEXT NOT NULL,
    body_ar TEXT NULL,
    visibility ENUM('internal','agents','public') NOT NULL DEFAULT 'internal',
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    author_user_id INT UNSIGNED NULL,
    view_count INT UNSIGNED NOT NULL DEFAULT 0,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_kb_article_slug (tenant_id, slug),
    KEY idx_rcc_kb_articles_cat (tenant_id, category_id, status),
    FULLTEXT KEY ft_rcc_kb_articles (title, body),
    CONSTRAINT fk_rcc_kb_articles_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_kb_tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    article_id INT UNSIGNED NOT NULL,
    tag VARCHAR(64) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rcc_kb_tag (tenant_id, article_id, tag),
    CONSTRAINT fk_rcc_kb_tags_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_kb_tags_article FOREIGN KEY (article_id) REFERENCES rcc_kb_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rcc_kb_feedback (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    article_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    agent_id INT UNSIGNED NULL,
    is_helpful TINYINT(1) NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rcc_kb_feedback_article (tenant_id, article_id),
    CONSTRAINT fk_rcc_kb_feedback_tenant FOREIGN KEY (tenant_id) REFERENCES rcc_tenants (id) ON DELETE CASCADE,
    CONSTRAINT fk_rcc_kb_feedback_article FOREIGN KEY (article_id) REFERENCES rcc_kb_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rcc_permissions (slug, name, module) VALUES
('rcc.kb.view', 'View Knowledge Base', 'kb'),
('rcc.kb.author', 'Author Articles', 'kb'),
('rcc.kb.publish', 'Publish Articles', 'kb'),
('rcc.kb.admin', 'KB Administration', 'kb');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 1, id FROM rcc_permissions WHERE slug LIKE 'rcc.kb.%';

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 2, id FROM rcc_permissions WHERE slug IN ('rcc.kb.view', 'rcc.kb.author');

INSERT IGNORE INTO rcc_role_permissions (role_id, permission_id)
SELECT 3, id FROM rcc_permissions WHERE slug IN ('rcc.kb.view');
