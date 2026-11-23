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
$campaignBrief = [
    'product' => 'منصة RATEB AI وابغا لها اعلان',
    'description' => 'عندي منصة RATEB AI وابغا لها اعلان',
    'audience' => 'الكل',
    'objective' => 'عملاء',
    'core_message' => 'عندي منصة RATEB AI وابغا لها اعلان',
    'channels' => '',
    'brand_tone' => '',
];
$outputs = [
    ['output_type' => 'social_posts', 'approval_status' => 'draft', 'content' => '{"snapchat":"a","instagram":"b","x":"c"}'],
    ['output_type' => 'headline', 'approval_status' => 'draft', 'content' => 'منصة RATEB AI تقدم حلولًا ذكية. اكتشف إمكانيات الذكاء الاصطناعي الآن!'],
    ['output_type' => 'strategy', 'approval_status' => 'approved', 'content' => $strategy],
    ['output_type' => 'call_to_action', 'approval_status' => 'draft', 'content' => 'لم يُحدد.'],
    ['output_type' => 'short_ad', 'approval_status' => 'approved', 'content' => 'اكتشف رتب للذكاء الاصطناعي الآن.'],
    ['output_type' => 'short_ad', 'approval_status' => 'draft', 'content' => 'نسخة أقدم'],
];
$facts = rateb_image_brief($campaign, $campaignBrief, $outputs);
$brief = rateb_image_brief_text($facts);

check(!str_contains($brief, 'لم يُحدد') && !str_contains($brief, 'الكل'), 'placeholders never reach the brief');
check(($facts['product'] ?? '') === $campaign['product_name'], 'the product comes from the campaign');
check(!isset($facts['title']), 'a title equal to the product is not repeated');
check(($facts['objective'] ?? '') === 'عملاء', 'the marketing goal is kept');
check(($facts['short_ad'] ?? '') === 'اكتشف رتب للذكاء الاصطناعي الآن.', 'approved copy is available for style');
check(!isset($facts['headline']), 'a draft headline is never mixed into image facts');
check(!isset($facts['platforms']), 'draft social platforms are never mixed into image facts');
check(!isset($facts['creative_direction'], $facts['channels'], $facts['tone'], $facts['audience']), 'empty authoritative fields are dropped');
check(!isset($facts['positioning'], $facts['product_description'], $facts['ad_copy']), 'generated strategy and draft copy cannot add product claims');
check(!rateb_image_brief_sufficient($facts), 'campaign 80 is rejected because it never says what the platform does');
check(!rateb_image_brief_sufficient(rateb_image_brief(['product_name' => 'لم يُحدد', 'title' => '', 'description' => 'الكل'], [], [])), 'a placeholder-only brief is insufficient');
check(rateb_image_brand_words($facts) === ['rateb'], 'the Latin brand word is found so it is never drawn');

$groundedFacts = rateb_image_brief(
    ['product_name' => 'منصة RATEB AI', 'title' => 'RATEB AI'],
    ['product' => 'منصة RATEB AI', 'description' => 'منصة تنشئ إعلانات وصور وفيديوهات للحملات التسويقية'],
    []
);
check(rateb_image_brief_sufficient($groundedFacts), 'a digital-service brief with a concrete capability is sufficient');
$rawPlan = '```json {"sufficient":true,"evidence":"منصة تنشئ إعلانات وصور وفيديوهات للحملات التسويقية","category":"digital_service","subject":"RATEB laptop showing AI generated video ads with the text RATEB AI","visual_elements":["smartphone with vertical video ad","logo of the brand","منصة","grid of ad thumbnails"],"setting":"modern desk","mood":"innovative","avoid":["picture frame","landscape"]} ```';
$plan = rateb_image_plan_parse($rawPlan, ['rateb'], $groundedFacts);
check(is_array($plan) && $plan['sufficient'] === true, 'a fenced JSON plan is accepted');
check(is_array($plan) && stripos($plan['subject'], 'rateb') === false && stripos($plan['subject'], 'text') === false, 'brand words and text requests are removed from the subject');
check(is_array($plan) && $plan['elements'] === ['smartphone with vertical video ad', 'brand', 'grid of ad thumbnails'], 'arabic and logo requests are removed from elements');
$leftover = rateb_image_plan_parse('{"subject":"tablet showing an analytics dashboard","avoid":["or text","mobile phone","physical product or"]}');
check(is_array($leftover) && $leftover['avoid'] === ['mobile phone', 'physical product'], 'connector words left after removing text requests are dropped');
check(rateb_image_plan_parse('{"sufficient":false}') === ['sufficient' => false], 'the model can say the brief is insufficient');
check(rateb_image_plan_parse('no json here') === null, 'a non-JSON reply is unusable');
check(rateb_image_plan_parse('{"subject":"منتج"}') === null, 'an Arabic subject is unusable');
$wrongPlan = '```json {"sufficient":true,"evidence":"منصة تنشئ إعلانات وصور وفيديوهات للحملات التسويقية","category":"food","subject":"restaurant meal on a dining table","visual_elements":["plate of food"],"setting":"restaurant","mood":"warm","avoid":[]} ```';
check(rateb_image_plan_parse($wrongPlan, ['rateb'], $groundedFacts) === null, 'a prompt subject that conflicts with the campaign topic is rejected');
check(rateb_image_plan_parse(str_replace('منصة تنشئ إعلانات وصور وفيديوهات للحملات التسويقية', 'claim not in brief', $rawPlan), ['rateb'], $groundedFacts) === null, 'model evidence must be an exact authoritative campaign fact');
$quotedEvidence = str_replace('"evidence":"منصة تنشئ', '"evidence":"“منصة تنشئ', str_replace('التسويقية","category"', 'التسويقية”","category"', $rawPlan));
check(is_array(rateb_image_plan_parse($quotedEvidence, ['rateb'], $groundedFacts)), 'quotation marks around an otherwise exact evidence quote are normalized');
$genericMarketingPlan = [
    'sufficient' => true,
    'category' => 'digital_service',
    'subject' => 'AI-powered marketing platform dashboard displaying generated ad images',
    'elements' => ['dashboard screen', 'campaign planner interface', 'AI-generated ad image preview'],
    'setting' => 'business office workspace',
    'mood' => 'professional innovative',
    'avoid' => [],
    'evidence' => 'منصة تنشئ إعلانات وصور وفيديوهات للحملات التسويقية',
];
$groundedMarketingPlan = rateb_image_plan_ground_marketing_service($genericMarketingPlan, $groundedFacts);
check(!str_contains(strtolower($groundedMarketingPlan['subject']), 'dashboard'), 'a marketing platform cannot keep a generic dashboard as its visible subject');
check(str_contains(strtolower($groundedMarketingPlan['subject']), 'advertising posters') && str_contains(strtolower($groundedMarketingPlan['subject']), 'marketing flyers'), 'a marketing platform shows advertising posters and marketing flyers');
check(str_contains(strtolower($groundedMarketingPlan['subject']), 'flat lay') || str_contains(strtolower($groundedMarketingPlan['subject']), 'commercial'), 'a marketing platform uses a commercial flat-lay scene');
$groundedMarketingPrompt = rateb_image_prompt_compose($groundedMarketingPlan, $groundedFacts, '1:1', 0);
check(str_contains($groundedMarketingPrompt, 'analytics dashboard') && str_contains($groundedMarketingPrompt, 'computer monitor') && str_contains($groundedMarketingPrompt, 'neon orb'), 'the grounded marketing prompt excludes screens, neon orbs, and unrelated stock imagery');
$accountingPlan = rateb_image_plan_ground_marketing_service(
    ['category' => 'digital_service', 'subject' => 'accounting software dashboard', 'elements' => []],
    ['product' => 'برنامج محاسبة يدير الفواتير والمخزون']
);
check($accountingPlan['subject'] === 'accounting software dashboard', 'non-marketing digital services are not rewritten');
$recovered = rateb_image_plan_recover_marketing($groundedFacts);
check(is_array($recovered) && ($recovered['category'] ?? '') === 'digital_service', 'a sufficient marketing brief can recover when the art director reply is unusable');
check(rateb_image_plan_recover_marketing(['product' => 'برنامج محاسبة يدير الفواتير']) === null, 'non-marketing digital briefs do not use the marketing recovery path');
check(rateb_image_caption_hard_reject($groundedFacts, 'a neon orb surrounded by glowing particle networks') !== null, 'abstract tech art captions are hard-rejected');
check(rateb_image_caption_hard_reject($groundedFacts, 'white paper with writing on it next to a keyboard') !== null, 'a handwritten notepad caption is hard-rejected for marketing campaigns');
check(rateb_image_caption_hard_reject($groundedFacts, 'several colors of post-it notes are arranged in a square formation on a pink background') !== null, 'sticky notes alone are hard-rejected for marketing campaigns');
check(rateb_image_caption_hard_reject($groundedFacts, 'a wooden desk with three drawers and a chair') !== null, 'furniture-only captions are hard-rejected for marketing campaigns');
check(rateb_image_caption_hard_reject($groundedFacts, 'a photo collage featuring women photos on the wall') !== null, 'people-heavy collages are hard-rejected for marketing campaigns');
check(rateb_image_caption_hard_reject($groundedFacts, 'colorful advertising posters and printed marketing flyers on a wooden desk') === null, 'a relevant advertising-poster caption is not hard-rejected');
check(rateb_image_caption_hard_accept($groundedFacts, 'colorful advertising posters and printed marketing flyers on a wooden desk'), 'an advertising-poster flat-lay is hard-accepted without waiting on the language gate');
check(rateb_image_caption_has_creative_outputs('three colorful brochures next to each other on a table'), 'plural brochures are recognized as creative marketing outputs');
check(rateb_image_caption_has_creative_outputs('two posters sitting on top of a table next to a cup of coffee'), 'plural posters are recognized as creative marketing outputs');
check(!rateb_image_caption_has_creative_outputs('several magazine covers stacked on a wooden table'), 'unrelated magazine stacks alone are not treated as campaign creatives');
check(rateb_image_caption_hard_reject($groundedFacts, 'several pictures mounted on the wall of a living room') !== null, 'home-decor gallery walls are hard-rejected for marketing campaigns');
check(!rateb_image_caption_hard_accept($groundedFacts, 'several colors of post-it notes are arranged in a square formation on a pink background'), 'sticky notes alone are not hard-accepted for marketing campaigns');
check(rateb_image_select_models(['Missing', 'Realistic Vision', 'Deliberate'], ['Realistic Vision' => 2, 'Deliberate' => 4], 2) === ['Deliberate', 'Realistic Vision'], 'online preferred models prefer the busiest available workers');
check(str_starts_with(strtolower(rateb_image_model_preferences('digital_service')[0]), 'flux') || in_array(rateb_image_model_preferences('digital_service')[0], ['Deliberate', 'stable_diffusion', 'AbsoluteReality'], true), 'digital campaigns prefer Flux or a photoreal fallback');
$params = rateb_image_generation_params(512, 512);
check(($params['steps'] ?? 0) >= 20 && ($params['cfg_scale'] ?? 0) >= 7, 'generation params ask for prompt adherence without overloading the free queue');
$fluxParams = rateb_image_generation_params(512, 512, ['Flux.1-Schnell fp8 (Compact)']);
check(($fluxParams['steps'] ?? 0) <= 10 && ($fluxParams['cfg_scale'] ?? 99) <= 3.5, 'Flux uses fewer steps and a lower cfg');
check(rateb_image_is_marketing_brief($groundedFacts), 'a RATEB marketing platform brief is detected');
check(is_string(rateb_image_marketing_seed_base64()) && rateb_image_marketing_seed_base64() !== '', 'the marketing flat-lay seed image is available');

$fallback = rateb_image_fallback_plan($facts);
check(is_array($fallback), 'the old fallback remains testable but is not used by the endpoint');
check(rateb_image_fallback_plan(['product' => 'Dates gift box'])['category'] === 'food', 'an English dates product falls back to food');
check(rateb_image_fallback_plan(['product' => 'خدمات متنوعة']) === null, 'an unknown business is not guessed');

$prompt = rateb_image_prompt_compose($plan, $facts, '9:16', 1);
[$positive, $negative] = explode(' ### ', $prompt, 2) + ['', ''];
check(str_starts_with($positive, $plan['subject']), 'the prompt leads with the campaign subject');
check(str_contains($positive, 'plain uncluttered space at the top and bottom'), 'a story image keeps space for the Arabic text');
check(str_contains($positive, 'mood board photograph') || str_contains($positive, 'flat lay') || str_contains($positive, 'studio wall'), 'the variant picks the shot');
check(!str_contains($positive, 'ratio') && !str_contains($positive, ' not '), 'no ratio label or negation in the positive prompt');
check(preg_match('/\p{Arabic}/u', $prompt) === 0, 'the prompt never asks for Arabic script');
check(str_contains($negative, 'text') && str_contains($negative, 'arabic script') && str_contains($negative, 'logo') && str_contains($negative, 'landscape'), 'the negative prompt blocks text, logos, and the plan avoid list');
check(rateb_image_prompt_clip_list('alpha, beta gamma, delta', 15) === 'alpha', 'prompt clipping stops at a phrase boundary instead of cutting a word');
$repeat = ['subject' => 'computer screen displaying AI analytics dashboard', 'elements' => ['AI analytics dashboard', 'performance graphs'], 'category' => 'digital_service'];
check(substr_count(rateb_image_prompt_compose($repeat, $facts, '1:1', 0), 'AI analytics dashboard') === 1, 'an element that repeats the subject is dropped');
check(rateb_image_prompt_compose($plan, $facts, '1:1', 0) !== rateb_image_prompt_compose($plan, $facts, '1:1', 2), 'each new image gets its own shot');
check(in_array(rateb_image_model_preferences('food')[0], ['Deliberate', 'stable_diffusion', 'AbsoluteReality', 'Realistic Vision'], true), 'physical campaigns prefer a photorealistic model');
$relevant = rateb_image_relevance_parse('```json {"relevant":true,"reason":"The gift box and dates are visible."} ```');
check(is_array($relevant) && $relevant['relevant'] === true, 'a valid relevance verdict is parsed');
check(rateb_image_relevance_parse('{"relevant":"yes"}') === null, 'a non-boolean relevance verdict fails closed');

echo $failures === 0 ? 'RESULT=PASS' : 'RESULT=FAIL (' . $failures . ')', PHP_EOL;
exit($failures === 0 ? 0 : 1);
