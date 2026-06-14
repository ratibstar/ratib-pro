<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$templates = require $root . '/config/email-templates-ar.php';

$hex = static function (string $text): string {
    return strtoupper(bin2hex($text));
};

$lines = [
    '-- RATEB ERP — RTL-friendly Arabic email subjects (placeholder at end)',
    'SET NAMES utf8mb4;',
    'SET CHARACTER SET utf8mb4;',
    '',
];

$slugs = [
    'approval_request',
    'low_stock_alert',
    'expiry_alert',
    'contract_expiry_alert',
    'maintenance_due_alert',
    'warranty_expiry_alert',
];

foreach ($slugs as $slug) {
    if (!isset($templates[$slug])) {
        continue;
    }
    $pair = $templates[$slug];
    $subjectHex = $hex($pair[0]);
    $bodyHex = $hex($pair[1]);
    $slugEsc = str_replace("'", "''", $slug);
    $lines[] = "UPDATE rateb_email_templates SET subject = CONVERT(UNHEX('{$subjectHex}') USING utf8mb4), body_html = CONVERT(UNHEX('{$bodyHex}') USING utf8mb4) WHERE slug = '{$slugEsc}';";
}

$out = $root . '/migrations/056_email_templates_rtl_subjects.sql';
file_put_contents($out, implode("\n", $lines) . "\n");
echo "Wrote {$out}\n";
