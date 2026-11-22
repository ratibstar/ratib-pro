<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/public-gate.php';

$fail = 0;
$assert = static function (bool $ok, string $label) use (&$fail): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
};

foreach (['/contact', '/privacy', '/terms', '/pricing'] as $path) {
    $assert(rateb_public_gate_is_marketing_path($path), $path . ' is marketing');
}

$js = (string) file_get_contents($root . '/public/assets/js/app.js');
$assert(str_contains($js, "gallery_note: 'Examples of the formats RATEB AI helps you prepare.'"), 'EN gallery note updated');
$assert(str_contains($js, "gallery_note: 'أمثلة على الصيغ التي يجهّزها RATEB AI.'"), 'AR gallery note updated');
$assert(!str_contains($js, 'Video generation is not available yet.'), 'EN no longer claims video unavailable');
$assert(!str_contains($js, 'توليد الفيديو غير متاح حالياً.'), 'AR no longer claims video unavailable');
$assert(str_contains($js, "nav_contact: 'تواصل'"), 'AR contact nav key');

$htaccess = (string) file_get_contents($root . '/.htaccess');
$assert(str_contains($htaccess, 'RewriteRule ^contact/?$ contact.php'), 'contact rewrite');
$assert(str_contains($htaccess, 'RewriteRule ^privacy/?$ privacy.php'), 'privacy rewrite');
$assert(str_contains($htaccess, 'RewriteRule ^terms/?$ terms.php'), 'terms rewrite');
$assert(str_contains($htaccess, 'RewriteRule ^pricing/?$ pricing.php'), 'pricing rewrite');

foreach (['contact.php', 'privacy.php', 'terms.php', 'pricing.php'] as $file) {
    $assert(is_file($root . '/' . $file), $file . ' exists');
}

$index = (string) file_get_contents($root . '/index.php');
$assert(str_contains($index, 'rateb_site_footer()'), 'homepage has footer');

$payments = (string) file_get_contents($root . '/admin/payments.php');
$assert(str_contains($payments, '/admin/refunds.php'), 'payments note points to refunds');
$assert(!str_contains($payments, 'There is no refund record in this database.'), 'old no-refund claim removed');

$subs = (string) file_get_contents($root . '/admin/subscriptions.php');
$assert(str_contains($subs, '111'), 'subscriptions note uses live starter price');
$assert(!str_contains($subs, '99 و 249') && !str_contains($subs, '99 and 249'), 'legacy 99/249 checkout note removed');

exit($fail > 0 ? 1 : 0);
