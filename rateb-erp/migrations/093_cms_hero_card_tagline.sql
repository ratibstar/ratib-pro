-- Hero side card tagline (bilingual) — editable via rateb_cms_sections.settings_json
SET NAMES utf8mb4;

UPDATE rateb_cms_sections
SET settings_json = JSON_OBJECT(
    'hero_card_en', 'Unified ERP for healthcare and enterprise',
    'hero_card_ar', UNHEX('D986D8B8D8A92045525020D985D988D8ADD20D984D984D982D8B7D8A720D8A7D984D8B5D8AD98620D988D8A7D984D985D8A4D8B3D8B3D8A7D8AA')
)
WHERE page_slug = 'home' AND section_key = 'hero';
