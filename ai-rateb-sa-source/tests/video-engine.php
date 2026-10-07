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

putenv('RUNWAY_API_KEY');
$provider = new RunwayVideoProvider();
check($provider->configured() === false, 'missing key is not configured');
check($provider->name() === 'runway', 'provider name');

$images = [['id' => 4], ['id' => 9]];
$short = rateb_video_storyboard(['id' => 1], ['creative_direction' => 'Quiet premium reveal'], ['headline'], $images, 8);
check(count($short) === 1 && $short[0]['duration'] === 5 && $short[0]['image_id'] === 4, 'plan duration limits scene count');
check(!str_contains($short[0]['prompt'], 'سعر') && str_contains($short[0]['prompt'], 'no text'), 'prompt asks for motion without rendered text');
$full = rateb_video_storyboard(['id' => 1], ['core_message' => 'Message'], ['copy'], $images, 40);
check(count($full) === 8 && $full[7]['image_id'] === 9, 'forty second plan uses eight five-second scenes');
$none = rateb_video_storyboard(['id' => 1], [], [], [], 40);
check($none === [], 'no image produces no storyboard');
check(rateb_video_ratio() === '720:1280', 'primary ratio is vertical 9:16');
check(rateb_video_message('not_configured', true) === 'توليد الفيديو غير مُعد على الخادم.', 'arabic missing-provider message');
check(rateb_video_message('not_configured', false) === 'Video generation is not configured.', 'english missing-provider message');
check(str_contains(file_get_contents(__DIR__ . '/../app/vision.php'), 'return null;'), 'vision stays disabled without a provider');

$status = (new ReflectionMethod(RunwayVideoProvider::class, 'getTaskStatus'));
check($status->getNumberOfParameters() === 1, 'status operation exists');
foreach (['generateFromImage', 'generateFromText', 'generateFromVideo', 'downloadResult'] as $method) {
    check(method_exists(RunwayVideoProvider::class, $method), $method);
}

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL\n";
exit($failures === 0 ? 0 : 1);
