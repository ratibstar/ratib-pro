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
putenv('RATEB_VIDEO_PROVIDER');
putenv('RATEB_PRIVATE_VIDEO_URL');
putenv('RATEB_PRIVATE_VIDEO_TOKEN');
$provider = new RunwayVideoProvider();
check($provider->configured() === false, 'missing key is not configured');
check($provider->name() === 'runway', 'provider name');
check(rateb_video_provider() instanceof RunwayVideoProvider, 'unset selection stays on runway');

putenv('RATEB_VIDEO_PROVIDER=runway');
putenv('RUNWAY_API_KEY=present-for-selection-only');
$selected = rateb_video_provider();
check($selected instanceof RunwayVideoProvider && $selected->configured() === true && $selected->name() === 'runway', 'runway selection stays runway when its key is set');
putenv('RUNWAY_API_KEY');

putenv('RATEB_VIDEO_PROVIDER=private');
$private = rateb_video_provider();
check($private instanceof PrivateVideoProvider && $private->configured() === false, 'private provider without url and token is not configured');
putenv('RATEB_PRIVATE_VIDEO_URL=https://video.internal');
check(rateb_video_provider()->configured() === false, 'private url alone is not configured');
putenv('RATEB_PRIVATE_VIDEO_URL');
putenv('RATEB_PRIVATE_VIDEO_TOKEN=secret-token-value');
check(rateb_video_provider()->configured() === false, 'private token alone is not configured');
$offline = rateb_video_provider();
$offlineError = '';
try {
    $offline->generateFromImage('data:image/png;base64,aa', 'motion', 5, '720:1280');
} catch (RuntimeException $error) {
    $offlineError = $error->getMessage();
}
check($offlineError === 'not_configured', 'missing private configuration does not send a generation request');
check(!str_contains($offlineError, 'secret-token-value'), 'generation error does not contain the token');
putenv('RATEB_PRIVATE_VIDEO_URL=https://video.internal');
$ready = rateb_video_provider();
check($ready->configured() === true && $ready->name() === 'private', 'private provider is selected when url and token are set');
check(!str_contains((string) json_encode($ready), 'secret-token-value'), 'selected provider does not expose the token');
putenv('RATEB_PRIVATE_VIDEO_URL=not-a-url');
check(rateb_video_provider()->configured() === false, 'invalid private url is not configured');
putenv('RATEB_VIDEO_PROVIDER');
putenv('RATEB_PRIVATE_VIDEO_URL');
putenv('RATEB_PRIVATE_VIDEO_TOKEN');

$images = [['id' => 4], ['id' => 9]];
$short = rateb_video_storyboard(['id' => 1], ['creative_direction' => 'Quiet premium reveal'], ['headline'], $images, 8);
check(count($short) === 1 && $short[0]['duration'] === 5 && $short[0]['image_id'] === 4, 'plan duration limits scene count');
check(!str_contains($short[0]['prompt'], 'سعر') && str_contains($short[0]['prompt'], 'no text'), 'prompt asks for motion without rendered text');
check(str_contains($short[0]['prompt'], 'Quiet premium reveal'), 'prompt keeps the approved creative direction');
check(!str_contains($short[0]['prompt'], 'logo applied'), 'prompt does not claim a logo was applied');
$full = rateb_video_storyboard(['id' => 1], ['core_message' => 'Message'], ['copy'], $images, 40);
check(count($full) === 8 && $full[7]['image_id'] === 9, 'forty second plan uses eight five-second scenes');
$none = rateb_video_storyboard(['id' => 1], [], [], [], 40);
check($none === [], 'no image produces no storyboard');
check(rateb_video_ratio() === '720:1280', 'primary ratio is vertical 9:16');
check(rateb_video_prompt_jpeg('not-an-image') === null, 'an unreadable image is not sent to the provider');
if (function_exists('imagecreatetruecolor')) {
    $wide = imagecreatetruecolor(2000, 200);
    $wideFill = imagecolorallocate($wide, 40, 90, 160);
    imagefill($wide, 0, 0, $wideFill);
    ob_start();
    imagepng($wide);
    $wideBytes = (string) ob_get_clean();
    imagedestroy($wide);
    $promptJpeg = rateb_video_prompt_jpeg($wideBytes);
    $promptImage = is_string($promptJpeg) ? @imagecreatefromstring($promptJpeg) : false;
    check(is_string($promptJpeg) && str_starts_with($promptJpeg, "\xFF\xD8") && strlen($promptJpeg) < 3300000, 'a wide source becomes a jpeg under the provider data-uri limit');
    check($promptImage !== false && imagesx($promptImage) === 720 && imagesy($promptImage) === 1280, 'prompt image is fitted to the vertical frame');
    if ($promptImage !== false) {
        imagedestroy($promptImage);
    }
}
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
