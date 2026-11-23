<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/ai/image-providers.php';

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$order = rateb_image_provider_order();
check($order !== [] && in_array('horde', $order, true), 'provider order includes horde');
check(in_array('pollinations', $order, true) || in_array('cloudflare', $order, true), 'a sync free provider is in the default order');

$limitCard = str_repeat("\xFF\xD8\xFF", 100); // not a real jpeg decode — function may return false
check(rateb_image_provider_mime("\xFF\xD8\xFF\xE0test") === 'image/jpeg', 'jpeg magic is detected');
check(rateb_image_provider_mime("\x89PNG\r\n\x1a\nxxxx") === 'image/png', 'png magic is detected');
check(rateb_image_provider_mime('hello') === null, 'non-image bytes are rejected');

check(function_exists('rateb_image_generate_sync'), 'sync generate helper exists');
check(function_exists('rateb_image_cf_configured'), 'cloudflare config helper exists');

echo $failures === 0 ? 'RESULT=PASS' . PHP_EOL : 'RESULT=FAIL failures=' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
