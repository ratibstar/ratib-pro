<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/migrations/platform_settings_schema.php';

$fail = 0;
$assert = static function (bool $ok, string $label) use (&$fail): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
};

$catalog = rateb_feature_catalog();
$assert(isset($catalog['feature_ai_image']), 'catalog has AI image');
$assert(isset($catalog['feature_ai_voice']), 'catalog has AI voice');
$assert(isset($catalog['feature_ai_video']), 'catalog has AI video');
$assert(isset($catalog['feature_ai_strategy']), 'catalog has AI strategy');
$assert(isset($catalog['feature_ai_copy']), 'catalog has AI copy');
$assert(rateb_feature_enabled('feature_ai_image', null) === true, 'default AI image on without DB');
$assert(rateb_feature_enabled('feature_missing_key', null) === false, 'unknown feature off');
$assert(count(rateb_feature_catalog()) === 5, 'exactly five product features');

$campaign = file_get_contents($root . '/campaign.php') ?: '';
$assert(str_contains($campaign, 'rateb_features(db())'), 'campaign loads feature flags');
$assert(str_contains($campaign, 'if ($featStrategy):'), 'campaign hides strategy panel');
$assert(str_contains($campaign, 'if ($featVoice):'), 'campaign hides voice panel');
$assert(str_contains($campaign, 'if ($featAiImage && $key === \'images\''), 'campaign gates AI image stage action');
$assert(str_contains($campaign, 'optional_label') && str_contains($campaign, 'اختياري'), 'campaign marks images as optional');
$assert(str_contains($campaign, '$stages[\'images\']') && str_contains($campaign, "'optional' => true"), 'images stage flagged optional');
$assert(!preg_match('/\$requiredPrior = 1 \+ \(\$featStrategy \? 1 : 0\) \+ \(\$featCopy \? 1 : 0\) \+ 1 \+/', $campaign), 'images not required in progress');

$settings = file_get_contents($root . '/admin/settings.php') ?: '';
$assert(str_contains($settings, 'feature_settings'), 'admin can save feature settings');
$assert(str_contains($settings, 'Save feature visibility') || str_contains($settings, 'حفظ ظهور الميزات'), 'admin feature panel label');

foreach ([
    'app/ai/image.php' => 'feature_ai_image',
    'app/ai/voice.php' => 'feature_ai_voice',
    'app/ai/video.php' => 'feature_ai_video',
    'app/ai/voices.php' => 'feature_ai_voice',
    'app/ai/video-approve.php' => 'feature_ai_video',
] as $file => $key) {
    $src = file_get_contents($root . '/' . $file) ?: '';
    $assert(str_contains($src, "rateb_feature_require('{$key}')"), $file . ' requires ' . $key);
}

$gen = file_get_contents($root . '/app/ai/generate.php') ?: '';
$assert(str_contains($gen, "rateb_feature_require('feature_ai_strategy')"), 'generate gates strategy');
$assert(str_contains($gen, "rateb_feature_require('feature_ai_copy')"), 'generate gates copy');

exit($fail > 0 ? 1 : 0);
