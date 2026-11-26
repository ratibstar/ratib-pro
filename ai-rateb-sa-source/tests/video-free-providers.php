<?php
declare(strict_types=1);

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
    'AGNES_API_KEY', 'RATEB_AGNES_FREE_ENTITLEMENT',
    'REPLICATE_API_TOKEN', 'REPLICATE_API_KEY', 'RATEB_REPLICATE_FREE_ENTITLEMENT',
    'RUNWARE_API_KEY', 'RATEB_RUNWARE_FREE_ENTITLEMENT',
    'RATEB_PRIVATE_VIDEO_URL', 'RATEB_PRIVATE_VIDEO_TOKEN',
];
// Prime CLI secrets prepend once, then override so later empty getenv() cannot reload live keys.
rateb_video_free_only_mode();
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


check(rateb_video_free_only_mode() === false, 'free-only off when unset');
check(rateb_video_provider() instanceof RunwayVideoProvider, 'default provider remains runway when free-only off');

putenv('RATEB_VIDEO_FREE_ONLY=1');
putenv('RATEB_VIDEO_PROVIDER=free_chain');
putenv('RATEB_AGNES_FREE_ENTITLEMENT=0');
putenv('AGNES_API_KEY=__UNSET__');
$manager = rateb_video_provider();
check($manager instanceof FreeOnlineVideoManager, 'free-only selects free_chain manager');
check($manager->configured() === false, 'manager unconfigured without verified free providers');
check($manager->hasFreeAvailable() === false, 'no FREE_AVAILABLE without keys+entitlement');

$statuses = rateb_video_free_provider_statuses(false);
check(count($statuses) === 3, 'status list has agnes, replicate, runware');
check(($statuses[0]['provider'] ?? '') === 'agnes', 'agnes is first in failover order');
check(($statuses[0]['free_available'] ?? true) === false, 'agnes not free without entitlement');
check(($statuses[0]['quota_status'] ?? '') === 'UNKNOWN', 'agnes quota unknown without verification');

putenv('AGNES_API_KEY=agnes-test-key-not-real');
putenv('RATEB_AGNES_FREE_ENTITLEMENT=0');
$agnes = rateb_video_free_provider_status('agnes', false);
check($agnes['configured'] === true && $agnes['free_available'] === false, 'agnes key alone is not FREE_AVAILABLE');

putenv('RATEB_AGNES_FREE_ENTITLEMENT=1');
$agnesOk = rateb_video_free_provider_status('agnes', false);
check($agnesOk['free_available'] === true && $agnesOk['eligible'] === true, 'agnes FREE_AVAILABLE only with key+entitlement flag');

$manager2 = rateb_video_provider();
check($manager2 instanceof FreeOnlineVideoManager && $manager2->hasFreeAvailable() === true, 'manager becomes configured when a free provider is verified');

putenv('RATEB_AGNES_FREE_ENTITLEMENT=0');
putenv('AGNES_API_KEY=__UNSET__');
putenv('RATEB_VIDEO_FREE_ONLY=0');
check(rateb_video_message('no_free_video_provider', false) !== '', 'no_free_video_provider message exists');
check(str_contains((string) file_get_contents(__DIR__ . '/../app/video/engine.php'), 'no_free_video_provider'), 'engine blocks free-only without providers');
check(str_contains((string) file_get_contents(__DIR__ . '/../app/video/engine.php'), 'freeOnlyJob'), 'engine disables local fallback for free-only jobs');
check(!str_contains((string) file_get_contents(__DIR__ . '/../app/video/free-manager.php'), 'RunwayVideoProvider'), 'free manager does not call Runway');

$agnesProvider = new AgnesVideoProvider();
check($agnesProvider->modelId() === 'agnes-video-2.5-flash', 'default Agnes model is 2.5-flash not retired v2.0');
$replicate = new ReplicateVideoProvider();
check($replicate->modelId() === 'minimax/video-01', 'default Replicate model is minimax/video-01');

$httpsPassthrough = rateb_video_agnes_https_image('https://ai.rateb.sa/public/assets/free-refs/c89/scene1-ddc205b8701c7aa9.jpg');
check($httpsPassthrough === 'https://ai.rateb.sa/public/assets/free-refs/c89/scene1-ddc205b8701c7aa9.jpg', 'Agnes helper keeps https URLs');
$rejected = false;
try {
    rateb_video_agnes_https_image('data:text/plain;base64,YQ==');
} catch (RuntimeException $e) {
    $rejected = $e->getMessage() === 'rejected_image';
}
check($rejected, 'Agnes helper rejects non-image data URIs');
$tinyJpeg = base64_encode(hex2bin('ffd8ffe000104a46494600010100000100010000ffdb004300080606070605080707070909080a0c140d0c0b0b0c1912130f141d1a1f1e1d1a1c1c20242e2720222c231c1c2837292c30313434341f27393d38323c2e333432ffdb0043010909090c0b0c180d0d1832211c213232323232323232323232323232323232323232323232323232323232323232323232323232323232323232323232323232ffc00011080001000103011100021100031101ffc4001500010100000000000000000000000000000000ffc40014100100000000000000000000000000000000ffda000c0301000210031000003f00bf80ffd9') ?: '');
$staged = rateb_video_agnes_https_image('data:image/jpeg;base64,' . $tinyJpeg);
check(str_starts_with($staged, 'https://') && str_contains($staged, '/public/assets/video-refs/'), 'Agnes helper stages data URI to public https path');
$stagedPath = dirname(__DIR__) . '/public/assets/video-refs/' . basename(parse_url($staged, PHP_URL_PATH) ?: '');
check(is_file($stagedPath), 'staged Agnes ref file exists on disk');
@unlink($stagedPath);

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL\n";
exit($failures === 0 ? 0 : 1);
