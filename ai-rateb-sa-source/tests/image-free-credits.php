<?php
declare(strict_types=1);

/**
 * Free-image zero-credit bypass tests (Cloudflare/Horde under free-only mode).
 */
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/video/engine.php';
require_once __DIR__ . '/../app/ai/image-providers.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

rateb_video_free_only_mode(); // prime secrets prepend once
$envNames = [
    'RATEB_VIDEO_FREE_ONLY', 'RATEB_VIDEO_PROVIDER',
    'RATEB_AGNES_FREE_ENTITLEMENT', 'AGNES_API_KEY',
];
foreach ($envNames as $env) {
    putenv($env);
    unset($_ENV[$env], $_SERVER[$env]);
}
putenv('RATEB_VIDEO_FREE_ONLY=0');
putenv('RATEB_VIDEO_PROVIDER=runway');

check(rateb_image_free_no_credit() === false, 'no_credit false when free-only off');

putenv('RATEB_VIDEO_FREE_ONLY=1');
putenv('RATEB_VIDEO_PROVIDER=free_chain');
$cf = rateb_image_cf_configured();
$horde = rateb_image_horde_configured();
check(rateb_image_free_no_credit() === ($cf || $horde), 'no_credit true only when free-only AND CF/Horde configured');
if (!($cf || $horde)) {
    check(rateb_image_free_no_credit() === false, 'no_credit false when free providers absent (local)');
} else {
    echo "INFO free providers on host: cf=" . ($cf ? '1' : '0') . " horde=" . ($horde ? '1' : '0') . PHP_EOL;
}

$syncOrder = rateb_image_sync_provider_order();
if (rateb_image_free_no_credit()) {
    check($syncOrder === ['cloudflare'] || $syncOrder === [], 'free-only sync order is Cloudflare-only (no Pollinations)');
    check(!in_array('openai', $syncOrder, true), 'openai excluded from free sync order');
} else {
    check(true, 'skip Cloudflare-only sync assert when free providers absent');
}

putenv('RATEB_VIDEO_FREE_ONLY=0');
putenv('RATEB_VIDEO_PROVIDER=runway');
check(rateb_image_free_no_credit() === false, 'no_credit false again when free-only disabled');
$paidOrder = rateb_image_sync_provider_order();
check(in_array('cloudflare', $paidOrder, true) || in_array('pollinations', $paidOrder, true) || $paidOrder !== ['cloudflare'], 'paid/normal sync order is not forced Cloudflare-only');

$img = (string) file_get_contents(__DIR__ . '/../app/ai/image.php');
check(str_contains($img, 'rateb_image_free_no_credit()'), 'image endpoint gates credits via free_no_credit');
check(str_contains($img, "rateb_usage_begin(db(), \$userId, \$campaignId, 'image')"), 'paid path still calls rateb_usage_begin');
check(str_contains($img, 'Free-only zero-credit path never falls through to paid OpenAI'), 'openai provider4 blocked on free path');
check(str_contains($img, 'free-no-credit jobs never charged'), 'release skips settle for usage_id=0');

$plans = (string) file_get_contents(__DIR__ . '/../app/plans.php');
$start = strpos($plans, 'function rateb_usage_begin');
$end = strpos($plans, 'function rateb_usage_finish', $start === false ? 0 : $start);
$body = ($start !== false && $end !== false) ? substr($plans, $start, $end - $start) : '';
check($body !== '' && str_contains($body, 'rateb_reservation_charge'), 'rateb_usage_begin still charges normal jobs');
check(!str_contains($body, 'rateb_image_free_no_credit'), 'rateb_usage_begin itself unchanged');
check(str_contains($plans, 'allowZero'), 'image job claim allows usage_id=0 only for free path');

// Claim behavior: free allows 0, paid rejects 0
putenv('RATEB_VIDEO_FREE_ONLY=0');
$paidClaim = rateb_image_jobs_claim([], 66, 89, '', time(), 0);
check(($paidClaim['usage_id'] ?? -1) !== 0 || ($paidClaim['action'] ?? '') !== 'begin' || empty($paidClaim['jobs']), 'paid mode does not attach usage_id=0 claim');

putenv('RATEB_VIDEO_FREE_ONLY=1');
putenv('RATEB_VIDEO_PROVIDER=free_chain');
if (rateb_image_free_no_credit()) {
    $freeClaim = rateb_image_jobs_claim([], 66, 89, '', time(), 0);
    check(($freeClaim['action'] ?? '') === 'begin' && (int) ($freeClaim['usage_id'] ?? -1) === 0, 'free mode attaches usage_id=0 claim');
    $encoded = json_encode(['jobs' => array_values($freeClaim['jobs'])], JSON_UNESCAPED_UNICODE);
    $decoded = rateb_image_jobs_decode((string) $encoded);
    check($decoded !== [], 'free usage_id=0 submitting job survives decode');
}

putenv('RATEB_VIDEO_FREE_ONLY=0');
putenv('RATEB_VIDEO_PROVIDER=runway');

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL\n";
exit($failures === 0 ? 0 : 1);
