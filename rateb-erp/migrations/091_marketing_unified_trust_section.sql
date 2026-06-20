-- Unified marketing home: trust + pricing intro (UNHEX Arabic — CI/phpMyAdmin safe)
SET NAMES utf8mb4;

INSERT INTO rateb_cms_sections (page_slug, section_key, title_en, title_ar, body_en, body_ar, sort_order)
VALUES ('home', 'trust', 'Built for regulated operations', '',
 'Tenant isolation, audit trails, and encrypted infrastructure — not dashboards alone.', '', 35)
ON DUPLICATE KEY UPDATE
    title_en = VALUES(title_en),
    body_en = VALUES(body_en),
    sort_order = VALUES(sort_order);

UPDATE rateb_cms_sections SET
    title_ar = CONVERT(UNHEX('D985D8B5D985D98520D984D984D8B9D985D984D98AD8A7D8AA20D8A7D984D985D986D8B8D985D8A9') USING utf8mb4),
    body_ar = CONVERT(UNHEX('D8B9D8B2D98420D8A7D984D985D8B3D8AAD8A3D8ACD8B1D98AD98620D988D8B3D8ACD984D8A7D8AA20D8A7D984D8AAD8AFD982D98AD98220D988D8A8D986D98AD8A920D985D8B4D981D8B1D8A920E2809420D984D8A720D984D988D8ADD8A7D8AA20D981D982D8B72E') USING utf8mb4)
WHERE page_slug = 'home' AND section_key = 'trust';

INSERT INTO rateb_cms_blocks (section_id, block_type, title_en, title_ar, content_en, content_ar, icon, sort_order)
SELECT s.id, 'trust', t.en, '', t.desc_en, '', t.icon, t.ord
FROM rateb_cms_sections s
JOIN (
    SELECT 1 ord, 'RBAC & tenancy' en, 'Role matrices per branch with least-privilege access.' desc_en, 'fa-shield-halved' icon
    UNION ALL
    SELECT 2 ord, 'Audit trails' en, 'Append-only workflow history with actor attribution.' desc_en, 'fa-clipboard-list' icon
    UNION ALL
    SELECT 3 ord, 'Encrypted transit' en, 'TLS 1.3 to the edge with tenant-scoped storage.' desc_en, 'fa-lock' icon
    UNION ALL
    SELECT 4 ord, 'SLA visibility' en, 'Stage clocks and escalation before commitments slip.' desc_en, 'fa-gauge-high' icon
) t ON s.page_slug = 'home' AND s.section_key = 'trust'
WHERE NOT EXISTS (
    SELECT 1 FROM rateb_cms_blocks b
    WHERE b.section_id = s.id AND b.block_type = 'trust' LIMIT 1
);

UPDATE rateb_cms_blocks b INNER JOIN rateb_cms_sections s ON s.id = b.section_id SET
    b.title_ar = CONVERT(UNHEX('D8B5D984D8A7D8ADD98AD8A7D8AA20D988D8B9D8B2D984') USING utf8mb4),
    b.content_ar = CONVERT(UNHEX('D985D8B5D981D988D981D8A7D8AA20D8A3D8AFD988D8A7D8B120D984D983D98420D981D8B1D8B920D985D8B920D8A3D982D98420D8B5D984D8A7D8ADD98AD8A7D8AA20D985D985D983D986D8A92E') USING utf8mb4)
WHERE s.page_slug = 'home' AND s.section_key = 'trust' AND b.block_type = 'trust' AND b.title_en = 'RBAC & tenancy';

UPDATE rateb_cms_blocks b INNER JOIN rateb_cms_sections s ON s.id = b.section_id SET
    b.title_ar = CONVERT(UNHEX('D8B3D8ACD984D8A7D8AA20D8AAD8AFD982D98AD982') USING utf8mb4),
    b.content_ar = CONVERT(UNHEX('D8B3D8ACD98420D8BAD98AD8B120D982D8A7D8A8D98420D984D984D8ADD8B0D98120D985D8B920D8AAD8B9D8B1D98AD98120D8A7D984D985D986D981D991D8B02E') USING utf8mb4)
WHERE s.page_slug = 'home' AND s.section_key = 'trust' AND b.block_type = 'trust' AND b.title_en = 'Audit trails';

UPDATE rateb_cms_blocks b INNER JOIN rateb_cms_sections s ON s.id = b.section_id SET
    b.title_ar = CONVERT(UNHEX('D8A7D8AAD8B5D8A7D984D8A7D8AA20D985D8B4D981D8B1D8A9') USING utf8mb4),
    b.content_ar = CONVERT(UNHEX('544C5320312E3320D985D8B920D8AAD8AED8B2D98AD98620D985D8B9D8B2D988D98420D984D983D98420D985D8B3D8AAD8A3D8ACD8B12E') USING utf8mb4)
WHERE s.page_slug = 'home' AND s.section_key = 'trust' AND b.block_type = 'trust' AND b.title_en = 'Encrypted transit';

UPDATE rateb_cms_blocks b INNER JOIN rateb_cms_sections s ON s.id = b.section_id SET
    b.title_ar = CONVERT(UNHEX('D8B1D8A4D98AD8A920534C41') USING utf8mb4),
    b.content_ar = CONVERT(UNHEX('D8B3D8A7D8B9D8A7D8AA20D8A7D984D985D8B1D8A7D8ADD98420D988D8A7D984D8AAD8B5D8B9D98AD8AF20D982D8A8D98420D8AAD8ACD8A7D988D8B220D8A7D984D8A7D984D8AAD8B2D8A7D985D8A7D8AA2E') USING utf8mb4)
WHERE s.page_slug = 'home' AND s.section_key = 'trust' AND b.block_type = 'trust' AND b.title_en = 'SLA visibility';

INSERT INTO rateb_cms_sections (page_slug, section_key, title_en, title_ar, body_en, body_ar, sort_order)
VALUES ('pricing', 'intro', 'Plans that scale with your footprint', '',
 'Transparent tiers for evaluation, production, and enterprise teams.', '', 1)
ON DUPLICATE KEY UPDATE
    title_en = VALUES(title_en),
    body_en = VALUES(body_en);

UPDATE rateb_cms_sections SET
    title_ar = CONVERT(UNHEX('D8A8D8A7D982D8A7D8AA20D8AAD986D985D98820D985D8B920D8A7D8ADD8AAD98AD8A7D8ACD983') USING utf8mb4),
    body_ar = CONVERT(UNHEX('D985D8B3D8AAD988D98AD8A7D8AA20D988D8A7D8B6D8ADD8A920D984D984D8AAD982D98AD98AD98520D988D8A7D984D8A5D986D8AAD8A7D8AC20D988D8A7D984D985D8A4D8B3D8B3D8A7D8AA2E') USING utf8mb4)
WHERE page_slug = 'pricing' AND section_key = 'intro';
