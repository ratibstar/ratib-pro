-- Unified marketing home: enterprise trust highlights (ported from /home ideas, bilingual)
SET NAMES utf8mb4;

INSERT INTO rateb_cms_sections (page_slug, section_key, title_en, title_ar, body_en, body_ar, sort_order)
VALUES (
    'home',
    'trust',
    'Built for regulated operations',
    'مصمم للعمليات المنظمة',
    'Tenant isolation, audit trails, and encrypted infrastructure — not dashboards alone.',
    'عزل المستأجرين وسجلات التدقيق وبنية مشفرة — لا لوحات فقط.',
    35
)
ON DUPLICATE KEY UPDATE
    title_en = VALUES(title_en),
    title_ar = VALUES(title_ar),
    body_en = VALUES(body_en),
    body_ar = VALUES(body_ar),
    sort_order = VALUES(sort_order);

INSERT INTO rateb_cms_blocks (section_id, block_type, title_en, title_ar, content_en, content_ar, icon, sort_order)
SELECT s.id, 'trust', t.en, t.ar, t.desc_en, t.desc_ar, t.icon, t.ord
FROM rateb_cms_sections s
JOIN (
    SELECT 1 ord,
        'RBAC & tenancy' en, 'صلاحيات وعزل' ar,
        'Role matrices per branch with least-privilege access.' desc_en,
        'مصفوفات أدوار لكل فرع مع أقل صلاحيات ممكنة.' desc_ar,
        'fa-shield-halved' icon
    UNION ALL
    SELECT 2,
        'Audit trails', 'سجلات تدقيق',
        'Append-only workflow history with actor attribution.',
        'سجل غير قابل للحذف مع تعريف المنفّذ.',
        'fa-clipboard-list'
    UNION ALL
    SELECT 3,
        'Encrypted transit', 'اتصالات مشفرة',
        'TLS 1.3 to the edge with tenant-scoped storage.',
        'TLS 1.3 مع تخزين معزول لكل مستأجر.',
        'fa-lock'
    UNION ALL
    SELECT 4,
        'SLA visibility', 'رؤية SLA',
        'Stage clocks and escalation before commitments slip.',
        'ساعات المراحل والتصعيد قبل تجاوز الالتزامات.',
        'fa-gauge-high'
) t ON s.page_slug = 'home' AND s.section_key = 'trust'
WHERE NOT EXISTS (
    SELECT 1 FROM rateb_cms_blocks b
    WHERE b.section_id = s.id AND b.block_type = 'trust' LIMIT 1
);

INSERT INTO rateb_cms_sections (page_slug, section_key, title_en, title_ar, body_en, body_ar, sort_order)
VALUES (
    'pricing',
    'intro',
    'Plans that scale with your footprint',
    'باقات تنمو مع احتياجك',
    'Transparent tiers for evaluation, production, and enterprise teams.',
    'مستويات واضحة للتقييم والإنتاج والمؤسسات.',
    1
)
ON DUPLICATE KEY UPDATE
    title_en = VALUES(title_en),
    title_ar = VALUES(title_ar),
    body_en = VALUES(body_en),
    body_ar = VALUES(body_ar);
