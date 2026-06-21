-- Hero side card tagline (bilingual) — UNHEX Arabic for phpMyAdmin / CI safe
SET NAMES utf8mb4;

UPDATE rateb_cms_sections
SET settings_json = JSON_SET(
    COALESCE(settings_json, JSON_OBJECT()),
    '$.hero_card_en', 'Unified ERP for healthcare and enterprise',
    '$.hero_card_ar', CONVERT(UNHEX('D986D8B8D8A7D9852045525020D985D988D8ADD8AF20D984D984D982D8B7D8A7D8B920D8A7D984D8B5D8ADD98A20D988D8A7D984D985D8A4D8B3D8B3D8A7D8AA') USING utf8mb4)
)
WHERE page_slug = 'home' AND section_key = 'hero';
