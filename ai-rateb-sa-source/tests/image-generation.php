<?php
declare(strict_types=1);

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$source = (string) file_get_contents(dirname(__DIR__) . '/app/ai/image.php');

check(!str_contains($source, 'image_paused'), 'image generation is not short-circuited by a pause');
check(!str_contains($source, "\$_FILES['poster']"), 'a browser-drawn poster is never stored as an AI image');
$page = (string) file_get_contents(dirname(__DIR__) . '/campaign.php');
check(str_contains($page, 'id="imageForm"') && str_contains($page, 'name="ratio"'), 'the campaign page offers the AI image form');

preg_match_all("/'(?:1:1|4:5|9:16)' => \[(\d+), (\d+)\]|default => \[(\d+), (\d+)\]/", $source, $sizes, PREG_SET_ORDER);
$sizesOk = count($sizes) === 3;
foreach ($sizes as $size) {
    $dims = array_values(array_filter(array_slice($size, 1), static fn(string $v): bool => $v !== ''));
    $sizesOk = $sizesOk && (int) $dims[0] % 64 === 0 && (int) $dims[1] % 64 === 0 && (int) $dims[0] * (int) $dims[1] <= 576 * 576;
}
check($sizesOk, 'every image size fits the Horde no-upfront-kudos limit of 576x576');

if (preg_match('/function ai_image_url_allowed\(string \$url\): bool\s*\{.*?\n\}/s', $source, $match) === 1) {
    eval($match[0]);
}
check(function_exists('ai_image_url_allowed'), 'the result host check can be loaded');
if (function_exists('ai_image_url_allowed')) {
    check(ai_image_url_allowed('https://abc123.r2.cloudflarestorage.com/stable-horde/x.webp'), 'a Horde R2 result is trusted');
    check(!ai_image_url_allowed('https://evil.example/out.png'), 'an unknown https host is rejected');
    check(!ai_image_url_allowed('http://abc123.r2.cloudflarestorage.com/x.webp'), 'a plain http result is rejected');
    check(!ai_image_url_allowed('https://r2.cloudflarestorage.com.evil.example/x.webp'), 'a lookalike host is rejected');
    check(!ai_image_url_allowed(''), 'an empty result url is rejected');
}

check(str_contains($source, "if (in_array(\$submit['http'], [401, 403], true)) {"), 'only a rejected key falls back to an anonymous submit');
check(preg_match("/if \(\\\$imageUrl === ''\) \{\s*ai_image_pending/", $source) === 1, 'a missing result url keeps the job pending');
check(preg_match("/if \(!ai_image_url_allowed\(\\\$imageUrl\)\) \{\s*ai_image_release\(\\\$userId, \\\$jobId, \\\$usageId, 'failed'\)/", $source) === 1, 'an untrusted result url fails and releases the reservation');
check(str_contains($source, "'job_id' => \$jobId") && str_contains($source, "'prompt_sha256' => hash('sha256', \$prompt)"), 'the submitted prompt is logged against the Horde job id without a secret');
check(str_contains($source, "(\$bound['campaign_id'] ?? 0) !== (int) \$campaign['id']"), 'a Horde result cannot be stored under a different campaign');
check(!str_contains($source, 'rateb_image_fallback_plan($facts)'), 'the endpoint never guesses a fallback subject when understanding fails');

echo $failures === 0 ? 'RESULT=PASS' . PHP_EOL : 'RESULT=FAIL failures=' . $failures . PHP_EOL;
exit($failures === 0 ? 0 : 1);
