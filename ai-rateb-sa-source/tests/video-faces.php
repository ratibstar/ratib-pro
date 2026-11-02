<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/video/faces.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

echo "MOCK detection and tracking\n";
$lost = false;
try {
    rateb_video_face_tracks([
        ['n' => 0, 'w' => 720, 'h' => 1280, 'faces' => [['x' => 100, 'y' => 80, 'w' => 40, 'h' => 40]]],
        ['n' => 5, 'w' => 720, 'h' => 1280, 'faces' => []],
    ]);
} catch (RuntimeException $error) {
    $lost = $error->getMessage() === 'face_protection';
}
check($lost, 'a face that disappears fails closed');

$jump = false;
try {
    rateb_video_face_tracks([
        ['n' => 0, 'w' => 100, 'h' => 100, 'faces' => [['x' => 10, 'y' => 10, 'w' => 20, 'h' => 20]]],
        ['n' => 5, 'w' => 100, 'h' => 100, 'faces' => [['x' => 80, 'y' => 10, 'w' => 20, 'h' => 20]]],
    ]);
} catch (RuntimeException $error) {
    $jump = $error->getMessage() === 'face_protection';
}
check($jump, 'a face that jumps farther than the tracker allows fails closed');

$tracks = rateb_video_face_tracks([
    ['n' => 0, 'w' => 720, 'h' => 1280, 'faces' => [['x' => 100, 'y' => 80, 'w' => 40, 'h' => 48]]],
    ['n' => 10, 'w' => 720, 'h' => 1280, 'faces' => [['x' => 120, 'y' => 90, 'w' => 44, 'h' => 50]]],
]);
$filter = rateb_video_face_blur_filter($tracks, 720, 1280);
check(count($tracks) === 1 && str_contains($filter, 'boxblur') && str_contains($filter, '100') && str_contains($filter, '120') && str_contains($filter, 'overlay'), 'blur filter follows the sampled face positions');
check(rateb_video_face_blur_filter([], 720, 1280) === '', 'no detected face adds no blur filter');

echo "MOCK clip mark and final composition gate\n";
$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rateb-face-test-' . getmypid();
mkdir($dir);
$clip = $dir . DIRECTORY_SEPARATOR . 'clip.mp4';
file_put_contents($clip, 'clip');
$blocked = false;
try {
    rateb_video_face_marks_ready([$clip]);
} catch (RuntimeException $error) {
    $blocked = $error->getMessage() === 'face_protection';
}
check($blocked, 'composition refuses a clip without a protection mark');
rateb_video_face_mark($clip, 1, 'command');
$opened = false;
try {
    rateb_video_face_marks_ready([$clip]);
    $opened = true;
} catch (RuntimeException) {
    $opened = false;
}
check($opened, 'composition accepts a clip only after protection is marked');
$missingDetector = false;
try {
    putenv('RATEB_FACE_DETECT_CMD');
    putenv('RATEB_FACE_CASCADE');
    rateb_video_protect_file($clip);
} catch (RuntimeException $error) {
    $missingDetector = $error->getMessage() === 'face_protection';
}
check($missingDetector, 'clip processing fails when no face detector is available');
unlink($clip);
unlink($clip . '.face.json');
rmdir($dir);

echo "REAL detector\n";
putenv('RATEB_FACE_DETECT_CMD');
putenv('RATEB_FACE_CASCADE');
if (rateb_video_face_detector() === null) {
    echo "SKIP real Haar detection is not installed here; no real frames were detected\n";
} else {
    echo "FAIL real detector resolved but this test did not supply a labeled face video\n";
    $failures++;
}

exit($failures === 0 ? 0 : 1);
