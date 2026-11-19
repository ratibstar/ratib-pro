<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/plans.php';

$failures = 0;
function check(bool $ok, string $label): void
{
    global $failures;
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $failures++;
    }
}

$strategy = json_encode([
    'positioning' => 'منصة RATEB AI تقدم حلولًا ذكية للعمليات الرقمية.',
    'core_message' => 'اكتشف إمكانيات الذكاء الاصطناعي مع RATEB AI.',
    'audience' => 'الكل',
    'channels' => 'لم يُحدد.',
    'tone' => 'لم يُحدد.',
    'creative_direction' => 'لم يُحدد.',
    'call_to_action' => 'لم يُحدد.',
], JSON_UNESCAPED_UNICODE);
$campaign = [
    'id' => 80,
    'title' => 'منصة RATEB AI وابغا لها اعلان',
    'product_name' => 'منصة RATEB AI وابغا لها اعلان',
    'description' => 'عندي منصة RATEB AI وابغا لها اعلان',
    'price' => '',
    'target_customer' => 'الكل',
    'objective' => 'عملاء',
];
$outputs = [
    ['output_type' => 'social_posts', 'approval_status' => 'draft', 'content' => '{"snapchat":"a","instagram":"b","x":"c"}'],
    ['output_type' => 'headline', 'approval_status' => 'draft', 'content' => 'منصة RATEB AI تقدم حلولًا ذكية. اكتشف إمكانيات الذكاء الاصطناعي الآن!'],
    ['output_type' => 'strategy', 'approval_status' => 'approved', 'content' => $strategy],
    ['output_type' => 'call_to_action', 'approval_status' => 'draft', 'content' => 'لم يُحدد.'],
    ['output_type' => 'short_ad', 'approval_status' => 'approved', 'content' => 'اكتشف رتب للذكاء الاصطناعي الآن.'],
    ['output_type' => 'short_ad', 'approval_status' => 'draft', 'content' => 'نسخة أقدم'],
];
$facts = rateb_image_brief($campaign, $outputs);
$brief = rateb_image_brief_text($facts);

check(!str_contains($brief, 'لم يُحدد') && !str_contains($brief, 'الكل'), 'placeholders never reach the brief');
check(($facts['product'] ?? '') === $campaign['product_name'], 'the product comes from the campaign');
check(!isset($facts['title']), 'a title equal to the product is not repeated');
check(($facts['objective'] ?? '') === 'عملاء', 'the marketing goal is kept');
check(($facts['short_ad'] ?? '') === 'اكتشف رتب للذكاء الاصطناعي الآن.', 'the approved copy wins over an older draft');
check(str_contains($facts['headline'] ?? '', 'حلولًا ذكية'), 'a draft headline is used when none is approved');
check(($facts['platforms'] ?? '') === 'snapchat, instagram, x', 'ad platforms come from the social posts');
check(!isset($facts['creative_direction'], $facts['channels'], $facts['tone'], $facts['audience']), 'empty strategy fields are dropped');
check(rateb_image_brief_sufficient($facts), 'campaign 80 has enough to describe the product');
check(!rateb_image_brief_sufficient(rateb_image_brief(['product_name' => 'لم يُحدد', 'title' => '', 'description' => 'الكل'], [])), 'a placeholder-only brief is insufficient');
check(rateb_image_brand_words($facts) === ['rateb'], 'the Latin brand word is found so it is never drawn');

$plan = rateb_image_plan_parse('```json {"sufficient":true,"category":"digital_service","subject":"RATEB laptop showing AI generated video ads with the text RATEB AI","visual_elements":["smartphone with vertical video ad","logo of the brand","منصة","grid of ad thumbnails"],"setting":"modern desk","mood":"innovative","avoid":["picture frame","landscape"]} ```', ['rateb']);
check(is_array($plan) && $plan['sufficient'] === true, 'a fenced JSON plan is accepted');
check(is_array($plan) && stripos($plan['subject'], 'rateb') === false && stripos($plan['subject'], 'text') === false, 'brand words and text requests are removed from the subject');
check(is_array($plan) && $plan['elements'] === ['smartphone with vertical video ad', 'brand', 'grid of ad thumbnails'], 'arabic and logo requests are removed from elements');
$leftover = rateb_image_plan_parse('{"subject":"tablet showing an analytics dashboard","avoid":["or text","mobile phone","physical product or"]}');
check(is_array($leftover) && $leftover['avoid'] === ['mobile phone', 'physical product'], 'connector words left after removing text requests are dropped');
check(rateb_image_plan_parse('{"sufficient":false}') === ['sufficient' => false], 'the model can say the brief is insufficient');
check(rateb_image_plan_parse('no json here') === null, 'a non-JSON reply is unusable');
check(rateb_image_plan_parse('{"subject":"منتج"}') === null, 'an Arabic subject is unusable');

$fallback = rateb_image_fallback_plan($facts);
check(is_array($fallback) && $fallback['category'] === 'digital_service' && str_contains($fallback['subject'], 'advertisement'), 'the fallback for campaign 80 is an AI ad platform, not a generic product');
check(rateb_image_fallback_plan(['product' => 'Dates gift box'])['category'] === 'food', 'an English dates product falls back to food');
check(rateb_image_fallback_plan(['product' => 'خدمات متنوعة']) === null, 'an unknown business is not guessed');

$prompt = rateb_image_prompt_compose($plan, $facts, '9:16', 1);
[$positive, $negative] = explode(' ### ', $prompt, 2) + ['', ''];
check(str_starts_with($positive, $plan['subject']), 'the prompt leads with the campaign subject');
check(str_contains($positive, 'plain uncluttered space at the top and bottom'), 'a story image keeps space for the Arabic text');
check(str_contains($positive, 'three-quarter angle'), 'the variant picks the shot');
check(!str_contains($positive, 'ratio') && !str_contains($positive, ' not '), 'no ratio label or negation in the positive prompt');
check(preg_match('/\p{Arabic}/u', $prompt) === 0, 'the prompt never asks for Arabic script');
check(str_contains($negative, 'text') && str_contains($negative, 'arabic script') && str_contains($negative, 'logo') && str_contains($negative, 'landscape'), 'the negative prompt blocks text, logos, and the plan avoid list');
$repeat = ['subject' => 'computer screen displaying AI analytics dashboard', 'elements' => ['AI analytics dashboard', 'performance graphs'], 'category' => 'digital_service'];
check(substr_count(rateb_image_prompt_compose($repeat, $facts, '1:1', 0), 'AI analytics dashboard') === 1, 'an element that repeats the subject is dropped');
check(rateb_image_prompt_compose($plan, $facts, '1:1', 0) !== rateb_image_prompt_compose($plan, $facts, '1:1', 2), 'each new image gets its own shot');

echo $failures === 0 ? 'RESULT=PASS' : 'RESULT=FAIL (' . $failures . ')', PHP_EOL;
exit($failures === 0 ? 0 : 1);
