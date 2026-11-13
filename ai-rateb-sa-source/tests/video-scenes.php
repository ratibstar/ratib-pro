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

$notes = json_encode([[
    'kind' => 'image',
    'name' => 'counter.jpg',
    'analysis' => 'ready',
    'summary' => 'A service counter with a green sign',
    'subjects' => ['counter', 'sign'],
    'colors' => ['green'],
    'visible_text' => ['Open'],
    'products' => ['dates'],
    'setting' => 'shop interior',
    'brand_cues' => ['green sign'],
]], JSON_UNESCAPED_UNICODE);
$images = rateb_video_apply_vision([
    ['id' => 4, 'source' => 'ai', 'original_name' => 'counter.jpg'],
    ['id' => 9, 'source' => 'upload', 'original_name' => 'counter.jpg'],
], $notes);
check((string) $images[0]['source'] === 'ai' && !isset($images[0]['vision']), 'an AI still is not given the upload analysis');
check((string) $images[1]['source'] === 'upload' && ($images[1]['vision']['summary'] ?? '') === 'A service counter with a green sign', 'the upload keeps its source and receives the analysis');

$scenes = rateb_video_storyboard(
    ['id' => 3, 'product_name' => 'Dates'],
    ['creative_direction' => 'Warm light'],
    ['اطلب الخدمة اليوم'],
    [$images[1]],
    5,
    ['primary_color' => '#0f766e']
);
check(count($scenes) === 1, 'one analyzed upload plans one scene');
$prompt = (string) $scenes[0]['prompt'];
check(str_contains($prompt, 'service counter') && str_contains($prompt, 'green') && str_contains($prompt, 'Open'), 'the scene prompt uses the upload summary, colors, and visible text');
check(str_contains($prompt, 'dates') && str_contains($prompt, 'shop interior') && str_contains($prompt, 'green sign'), 'the scene prompt uses products, setting, and brand cues');
check((string) $scenes[0]['voiceover'] === 'اطلب الخدمة اليوم', 'the voiceover stays the approved narration');
check($scenes[0]['shows_saudi_employees'] === false && str_contains((string) $scenes[0]['scene_request'], 'not claimed'), 'the plan requests Saudi employees without claiming the output shows them');
check((string) $scenes[0]['reference_source'] === 'upload' && (int) $scenes[0]['image_id'] === 9, 'the upload remains the visual reference');

$plain = rateb_video_storyboard(['id' => 1, 'product_name' => 'Dates'], [], ['Approved line'], [['id' => 2, 'source' => 'ai']], 5);
check($plain[0]['shows_saudi_employees'] === false && !str_contains((string) $plain[0]['prompt'], 'service counter'), 'a still without upload analysis adds no invented image facts');

exit($failures === 0 ? 0 : 1);
