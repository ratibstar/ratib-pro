<?php
declare(strict_types=1);
/** Generate 091_marketing_unified_trust_section.sql — ASCII-only (phpMyAdmin / CI safe). */

function u(string $s): string
{
    if ($s === '') {
        return "''";
    }
    return "CONVERT(UNHEX('" . strtoupper(bin2hex($s)) . "') USING utf8mb4)";
}

$out = [];
$out[] = '-- Unified marketing home: trust + pricing intro (UNHEX Arabic — CI/phpMyAdmin safe)';
$out[] = 'SET NAMES utf8mb4;';
$out[] = '';
$out[] = "INSERT INTO rateb_cms_sections (page_slug, section_key, title_en, title_ar, body_en, body_ar, sort_order)";
$out[] = "VALUES ('home', 'trust', 'Built for regulated operations', '',";
$out[] = " 'Tenant isolation, audit trails, and encrypted infrastructure — not dashboards alone.', '', 35)";
$out[] = 'ON DUPLICATE KEY UPDATE';
$out[] = '    title_en = VALUES(title_en),';
$out[] = '    body_en = VALUES(body_en),';
$out[] = '    sort_order = VALUES(sort_order);';
$out[] = '';
$out[] = 'UPDATE rateb_cms_sections SET';
$out[] = '    title_ar = ' . u('مصمم للعمليات المنظمة') . ',';
$out[] = '    body_ar = ' . u('عزل المستأجرين وسجلات التدقيق وبنية مشفرة — لا لوحات فقط.');
$out[] = "WHERE page_slug = 'home' AND section_key = 'trust';";
$out[] = '';

$blocks = [
    [1, 'RBAC & tenancy', 'صلاحيات وعزل', 'Role matrices per branch with least-privilege access.', 'مصفوفات أدوار لكل فرع مع أقل صلاحيات ممكنة.', 'fa-shield-halved'],
    [2, 'Audit trails', 'سجلات تدقيق', 'Append-only workflow history with actor attribution.', 'سجل غير قابل للحذف مع تعريف المنفّذ.', 'fa-clipboard-list'],
    [3, 'Encrypted transit', 'اتصالات مشفرة', 'TLS 1.3 to the edge with tenant-scoped storage.', 'TLS 1.3 مع تخزين معزول لكل مستأجر.', 'fa-lock'],
    [4, 'SLA visibility', 'رؤية SLA', 'Stage clocks and escalation before commitments slip.', 'ساعات المراحل والتصعيد قبل تجاوز الالتزامات.', 'fa-gauge-high'],
];

$out[] = 'INSERT INTO rateb_cms_blocks (section_id, block_type, title_en, title_ar, content_en, content_ar, icon, sort_order)';
$out[] = 'SELECT s.id, \'trust\', t.en, \'\', t.desc_en, \'\', t.icon, t.ord';
$out[] = 'FROM rateb_cms_sections s';
$out[] = 'JOIN (';
foreach ($blocks as $i => $b) {
    if ($i === 0) {
        $out[] = "    SELECT {$b[0]} ord, '{$b[1]}' en, '{$b[3]}' desc_en, '{$b[5]}' icon";
        continue;
    }
    $out[] = "    UNION ALL";
    $out[] = "    SELECT {$b[0]} ord, '{$b[1]}' en, '{$b[3]}' desc_en, '{$b[5]}' icon";
}
$out[] = ") t ON s.page_slug = 'home' AND s.section_key = 'trust'";
$out[] = 'WHERE NOT EXISTS (';
$out[] = '    SELECT 1 FROM rateb_cms_blocks b';
$out[] = "    WHERE b.section_id = s.id AND b.block_type = 'trust' LIMIT 1";
$out[] = ');';
$out[] = '';

foreach ($blocks as $b) {
    $out[] = 'UPDATE rateb_cms_blocks b INNER JOIN rateb_cms_sections s ON s.id = b.section_id SET';
    $out[] = '    b.title_ar = ' . u($b[2]) . ',';
    $out[] = '    b.content_ar = ' . u($b[4]);
    $out[] = "WHERE s.page_slug = 'home' AND s.section_key = 'trust' AND b.block_type = 'trust' AND b.title_en = '{$b[1]}';";
    $out[] = '';
}

$out[] = "INSERT INTO rateb_cms_sections (page_slug, section_key, title_en, title_ar, body_en, body_ar, sort_order)";
$out[] = "VALUES ('pricing', 'intro', 'Plans that scale with your footprint', '',";
$out[] = " 'Transparent tiers for evaluation, production, and enterprise teams.', '', 1)";
$out[] = 'ON DUPLICATE KEY UPDATE';
$out[] = '    title_en = VALUES(title_en),';
$out[] = '    body_en = VALUES(body_en);';
$out[] = '';
$out[] = 'UPDATE rateb_cms_sections SET';
$out[] = '    title_ar = ' . u('باقات تنمو مع احتياجك') . ',';
$out[] = '    body_ar = ' . u('مستويات واضحة للتقييم والإنتاج والمؤسسات.');
$out[] = "WHERE page_slug = 'pricing' AND section_key = 'intro';";

$dest = dirname(__DIR__) . '/migrations/091_marketing_unified_trust_section.sql';
file_put_contents($dest, implode("\n", $out) . "\n");
echo "Wrote {$dest}\n";
