<?php
declare(strict_types=1);

/**
 * Proves Agnes-only free jobs skip RATEB credits, while other providers keep billing.
 */
require_once __DIR__ . '/../app/video/engine.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$envNames = [
    'RUNWAY_API_KEY', 'RATEB_VIDEO_PROVIDER', 'RATEB_VIDEO_FREE_ONLY',
    'AGNES_API_KEY', 'RATEB_AGNES_FREE_ENTITLEMENT', 'RATEB_AGNES_VIDEO_MODEL',
    'REPLICATE_API_TOKEN', 'REPLICATE_API_KEY', 'RATEB_REPLICATE_FREE_ENTITLEMENT',
    'RUNWARE_API_KEY', 'RATEB_RUNWARE_FREE_ENTITLEMENT',
    'RATEB_PRIVATE_VIDEO_URL', 'RATEB_PRIVATE_VIDEO_TOKEN',
];
// Prime CLI secrets prepend once, then override so empty getenv cannot reload live keys.
rateb_video_free_only_mode();
$clearEnv = static function () use ($envNames): void {
    foreach ($envNames as $env) {
        putenv($env);
        unset($_ENV[$env], $_SERVER[$env]);
    }
    putenv('RATEB_VIDEO_FREE_ONLY=0');
    putenv('RATEB_VIDEO_PROVIDER=runway');
    putenv('RATEB_AGNES_FREE_ENTITLEMENT=0');
    putenv('RATEB_REPLICATE_FREE_ENTITLEMENT=0');
    putenv('RATEB_RUNWARE_FREE_ENTITLEMENT=0');
    putenv('AGNES_API_KEY=__UNSET__');
};
$clearEnv();

check(rateb_video_free_agnes_no_credit() === false, 'no_credit false when free-only unset');

putenv('RATEB_VIDEO_FREE_ONLY=1');
putenv('RATEB_VIDEO_PROVIDER=free_chain');
putenv('RATEB_AGNES_FREE_ENTITLEMENT=0');
putenv('AGNES_API_KEY=__UNSET__');
check(rateb_video_free_agnes_no_credit() === false, 'no_credit false without Agnes key+entitlement');

putenv('AGNES_API_KEY=agnes-test-key-not-real');
putenv('RATEB_AGNES_FREE_ENTITLEMENT=1');
check(rateb_video_free_agnes_no_credit() === true, 'no_credit true for sole verified Agnes');
check(rateb_video_provider() instanceof FreeOnlineVideoManager, 'free-only still selects free_chain manager');

putenv('RATEB_REPLICATE_FREE_ENTITLEMENT=1');
putenv('REPLICATE_API_TOKEN=r8_test_token_not_real');
check(rateb_video_free_agnes_no_credit() === false, 'no_credit false when another free provider is also eligible');

putenv('RATEB_REPLICATE_FREE_ENTITLEMENT');
putenv('REPLICATE_API_TOKEN');
putenv('RATEB_RUNWARE_FREE_ENTITLEMENT=1');
putenv('RUNWARE_API_KEY=rw_test_key_not_real');
check(rateb_video_free_agnes_no_credit() === false, 'no_credit false when Runware free entitlement is also eligible');

putenv('RATEB_RUNWARE_FREE_ENTITLEMENT');
putenv('RUNWARE_API_KEY');
check(rateb_video_free_agnes_no_credit() === true, 'no_credit returns true again for Agnes-only');

putenv('RATEB_VIDEO_FREE_ONLY=0');
putenv('RATEB_VIDEO_PROVIDER=runway');
putenv('RUNWAY_API_KEY=rw_runway_test');
putenv('RATEB_AGNES_FREE_ENTITLEMENT=1');
check(rateb_video_free_only_mode() === false, 'runway selection is not free-only mode');
check(rateb_video_free_agnes_no_credit() === false, 'no_credit false for Runway even if Agnes entitlement remains set');
check(rateb_video_provider() instanceof RunwayVideoProvider, 'runway selection retains Runway provider');

putenv('RATEB_VIDEO_FREE_ONLY=0');
putenv('RATEB_VIDEO_PROVIDER=private');
putenv('RATEB_PRIVATE_VIDEO_URL=http://127.0.0.1:8091');
putenv('RATEB_PRIVATE_VIDEO_TOKEN=private-test-token');
check(rateb_video_free_agnes_no_credit() === false, 'no_credit false for private provider');
check(rateb_video_provider() instanceof PrivateVideoProvider, 'private selection retains private provider');

$engine = (string) file_get_contents(__DIR__ . '/../app/video/engine.php');
check(str_contains($engine, 'rateb_video_free_agnes_no_credit()'), 'video_start gates credits through Agnes no-credit helper');
check(str_contains($engine, '$usageId > 0'), 'video_fail refunds only when a paid usage reservation exists');
check(str_contains($engine, 'rateb_usage_begin($pdo, (int) $campaign[\'user_id\'], (int) $campaign[\'id\'], \'video\', $seconds)'), 'non-free path still calls rateb_usage_begin');

$plans = (string) file_get_contents(__DIR__ . '/../app/plans.php');
$start = strpos($plans, 'function rateb_usage_begin');
$end = strpos($plans, 'function rateb_usage_finish', $start === false ? 0 : $start);
$body = ($start !== false && $end !== false) ? substr($plans, $start, $end - $start) : '';
check($body !== '' && str_contains($body, 'rateb_reservation_charge'), 'rateb_usage_begin still charges credits for normal jobs');
check(!str_contains($body, 'rateb_video_free_agnes_no_credit'), 'rateb_usage_begin itself is unchanged (bypass stays in video_start)');

$clearEnv();

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL\n";
exit($failures === 0 ? 0 : 1);
