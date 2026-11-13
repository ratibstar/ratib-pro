<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/vision.php';

$failures = 0;
function check(bool $ok, string $name): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

putenv('RATEB_VISION_PROVIDER');
check(rateb_vision_provider() === null, 'vision is off unless explicitly selected');

$description = json_encode([
    'summary' => 'A recruitment desk with a navy brand panel.',
    'subjects' => ['reception desk'],
    'colors' => ['navy', 'white'],
    'visible_text' => ['RATEB'],
    'products' => ['recruitment service'],
    'setting' => 'Saudi office lobby',
    'brand_cues' => ['navy panel'],
], JSON_UNESCAPED_UNICODE);
$parsed = rateb_vision_parse(json_encode(['success' => true, 'result' => ['description' => $description]], JSON_UNESCAPED_UNICODE));
check(is_array($parsed) && $parsed['summary'] === 'A recruitment desk with a navy brand panel.' && $parsed['setting'] === 'Saudi office lobby' && $parsed['visible_text'] === ['RATEB'], 'a Cloudflare description JSON becomes a structured analysis');

$prose = rateb_vision_parse(json_encode(['success' => true, 'result' => ['description' => 'An office reception with no readable text.']], JSON_UNESCAPED_UNICODE));
check(is_array($prose) && $prose['summary'] === 'An office reception with no readable text.' && $prose['products'] === [], 'plain description text is kept as the summary');

check(rateb_vision_parse('{"success":false,"errors":[{"message":"quota"}]}') === null, 'a failed Cloudflare response is not an analysis');
check(rateb_vision_parse('{"success":true,"result":{}}') === null, 'an empty vision result is not an analysis');

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL\n";
exit($failures === 0 ? 0 : 1);
