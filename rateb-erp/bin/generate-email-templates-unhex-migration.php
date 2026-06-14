<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$templates = require $root . '/config/email-templates-ar.php';

$hex = static function (string $text): string {
    return strtoupper(bin2hex($text));
};

$lines = [
    '-- RATEB ERP — Arabic email templates (UNHEX; deploy-safe)',
    'SET NAMES utf8mb4;',
    'SET CHARACTER SET utf8mb4;',
    '',
];

foreach ($templates as $slug => $pair) {
    $subjectHex = $hex($pair[0]);
    $bodyHex = $hex($pair[1]);
    $slugEsc = str_replace("'", "''", $slug);
    $lines[] = "UPDATE rateb_email_templates SET subject = CONVERT(UNHEX('{$subjectHex}') USING utf8mb4), body_html = CONVERT(UNHEX('{$bodyHex}') USING utf8mb4) WHERE slug = '{$slugEsc}';";
}

$out = $root . '/migrations/055_email_templates_arabic_unhex.sql';
file_put_contents($out, implode("\n", $lines) . "\n");
echo "Wrote {$out} (" . count($templates) . " templates)\n";
