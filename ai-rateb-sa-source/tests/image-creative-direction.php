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

/**
 * @param array<string,string> $campaign
 * @param array<string,string> $brief
 * @return array<string,string>
 */
function fixture_facts(array $campaign, array $brief, array $outputs = []): array
{
    return rateb_image_brief($campaign, $brief, $outputs, [
        'brand_name' => 'DemoBrand',
        'tone' => 'professional',
        'primary_color' => 'teal',
        'secondary_color' => 'coral',
    ]);
}

$fixtures = [
    'restaurant' => [
        'campaign' => ['product_name' => 'مطعم المشوى', 'title' => 'عرض الغداء', 'description' => 'مطعم وجبات مشوية وبرجر'],
        'brief' => ['product' => 'مطعم المشوى', 'description' => 'وجبات مشوية وبرجر طازج للعائلات', 'audience' => 'عائلات', 'objective' => 'عملاء'],
        'must' => ['meal', 'restaurant', 'burger', 'dish', 'plate', 'food'],
        'forbid' => ['perfume', 'apartment', 'dental'],
    ],
    'real_estate' => [
        'campaign' => ['product_name' => 'شقق النخيل', 'title' => 'بيع شقق', 'description' => 'شقق سكنية وفلل للبيع'],
        'brief' => ['product' => 'شقق النخيل', 'description' => 'عقارات وشقق وفلل في حي راق', 'audience' => 'عائلات', 'objective' => 'مبيعات'],
        'must' => ['building', 'apartment', 'villa', 'living', 'property', 'residential'],
        'forbid' => ['burger', 'perfume', 'gym'],
    ],
    'saas' => [
        'campaign' => ['product_name' => 'LedgerPro', 'title' => 'برنامج محاسبة', 'description' => 'برنامج محاسبة يدير الفواتير والمخزون'],
        'brief' => ['product' => 'LedgerPro', 'description' => 'برنامج محاسبة يدير الفواتير والمخزون والرواتب', 'audience' => 'محاسبون', 'objective' => 'اشتراكات'],
        'must' => ['laptop', 'desk', 'invoice', 'accounting', 'chart', 'finance'],
        'forbid' => ['burger', 'villa', 'perfume'],
    ],
    'recruitment' => [
        'campaign' => ['product_name' => 'توظيف مهندسين', 'title' => 'وظائف', 'description' => 'توظيف مهندسين للشركة'],
        'brief' => ['product' => 'توظيف مهندسين', 'description' => 'حملة توظيف ووظائف هندسية', 'audience' => 'مهندسون', 'objective' => 'توظيف'],
        'must' => ['interview', 'resume', 'hiring', 'recruit', 'lobby', 'office'],
        'forbid' => ['burger', 'perfume', 'coffee cup'],
    ],
    'automotive' => [
        'campaign' => ['product_name' => 'تأجير سيارات النور', 'title' => 'سيارات', 'description' => 'تأجير سيارات حديثة'],
        'brief' => ['product' => 'تأجير سيارات النور', 'description' => 'خدمة تأجير سيارات يومية', 'audience' => 'مسافرون', 'objective' => 'حجوزات'],
        'must' => ['car', 'vehicle', 'wheel', 'automobile', 'road'],
        'forbid' => ['burger', 'dental', 'apartment'],
    ],
    'healthcare' => [
        'campaign' => ['product_name' => 'عيادة الأسنان', 'title' => 'أسنان', 'description' => 'عيادة أسنان حديثة'],
        'brief' => ['product' => 'عيادة الأسنان', 'description' => 'خدمات طب الأسنان والعلاج', 'audience' => 'عائلات', 'objective' => 'مواعيد'],
        'must' => ['dental', 'clinic', 'chair', 'treatment', 'medical'],
        'forbid' => ['burger', 'car', 'villa'],
    ],
    'retail' => [
        'campaign' => ['product_name' => 'متجر الأناقة', 'title' => 'ملابس', 'description' => 'متجر ملابس وأزياء'],
        'brief' => ['product' => 'متجر الأناقة', 'description' => 'تجزئة ملابس وأزياء رجالية ونسائية', 'audience' => 'شباب', 'objective' => 'مبيعات'],
        'must' => ['garment', 'clothing', 'fashion', 'fabric', 'boutique', 'shelf', 'retail', 'dress'],
        'forbid' => ['dental', 'car engine'],
    ],
    'professional' => [
        'campaign' => ['product_name' => 'مكتب استشارات قانونية', 'title' => 'استشارات', 'description' => 'محاماة واستشارات قانونية'],
        'brief' => ['product' => 'مكتب استشارات قانونية', 'description' => 'خدمات محاماة واستشارات للشركات', 'audience' => 'شركات', 'objective' => 'عملاء'],
        'must' => ['office', 'document', 'laptop', 'desk', 'consult', 'professional'],
        'forbid' => ['burger', 'gym', 'perfume'],
    ],
];

foreach ($fixtures as $name => $fx) {
    $facts = fixture_facts($fx['campaign'], $fx['brief']);
    check(rateb_image_brief_sufficient($facts), "{$name}: brief is sufficient");
    $dir = rateb_image_creative_direction($facts, 0);
    check(is_array($dir) && ($dir['sufficient'] ?? false) === true, "{$name}: creative direction exists");
    $subject = strtolower((string) ($dir['subject'] ?? ''));
    $hit = false;
    foreach ($fx['must'] as $word) {
        if (str_contains($subject, strtolower($word))) {
            $hit = true;
            break;
        }
    }
    check($hit, "{$name}: subject names the real industry (" . ($dir['subject'] ?? '') . ')');
    foreach ($fx['forbid'] as $word) {
        check(!str_contains($subject, strtolower($word)), "{$name}: subject excludes unrelated '{$word}'");
    }
    $dir1 = rateb_image_creative_direction($facts, 1);
    check(is_array($dir1) && ($dir1['subject'] ?? '') !== ($dir['subject'] ?? ''), "{$name}: variants differ by role");

    $fpA = rateb_image_campaign_fingerprint($fx['campaign'], $fx['brief'], [], []);
    if (!is_array($dir)) {
        continue;
    }
    $plan = rateb_image_plan_ground_marketing_service([
        'sufficient' => true,
        'category' => (string) ($dir['category'] ?? 'other'),
        'subject' => (string) $dir['subject'],
        'elements' => (array) ($dir['elements'] ?? []),
        'setting' => (string) ($dir['setting'] ?? ''),
        'mood' => (string) ($dir['mood'] ?? ''),
        'avoid' => [],
        'evidence' => mb_substr((string) ($fx['brief']['description'] ?? ''), 0, 80),
        'story' => (string) ($dir['story'] ?? ''),
    ], $facts, 0);
    $fpB = rateb_image_campaign_fingerprint($fx['campaign'], $fx['brief'], [], []);
    check($fpA === $fpB, "{$name}: grounding does not mutate campaign fingerprint");

    $prompt = rateb_image_prompt_compose($plan, $facts, '1:1', 0);
    $promptHit = false;
    foreach ($fx['must'] as $word) {
        if (str_contains(strtolower($prompt), strtolower($word))) {
            $promptHit = true;
            break;
        }
    }
    check($promptHit, "{$name}: final prompt keeps the campaign subject");
    check(rateb_image_prompt_integrity($facts, $prompt, $plan) === null || ($plan['category'] ?? '') !== 'digital_service' || rateb_image_is_marketing_brief($facts), "{$name}: prompt integrity evaluated");
}

// Cross-leak: restaurant prompt must not contain real-estate subject.
$rest = rateb_image_creative_direction(fixture_facts($fixtures['restaurant']['campaign'], $fixtures['restaurant']['brief']), 0);
$estate = rateb_image_creative_direction(fixture_facts($fixtures['real_estate']['campaign'], $fixtures['real_estate']['brief']), 0);
check(is_array($rest) && is_array($estate) && ($rest['subject'] ?? '') !== ($estate['subject'] ?? ''), 'restaurant and real-estate concepts are distinct');

// Unrelated caption cannot pass for a restaurant campaign.
$restFacts = fixture_facts($fixtures['restaurant']['campaign'], $fixtures['restaurant']['brief']);
check(rateb_image_caption_hard_reject($restFacts, 'colorful advertising posters and printed marketing flyers on a wooden desk') !== null, 'restaurant rejects generic marketing flat-lay caption');
check(rateb_image_caption_hard_reject($restFacts, 'appetizing burger meal on a restaurant plate with fries') === null, 'restaurant accepts a food caption');

// Soft-promotion regression: hard_accept alone is not enough for concrete product miss.
check(rateb_image_caption_hard_reject(
    fixture_facts($fixtures['automotive']['campaign'], $fixtures['automotive']['brief']),
    'pantone color swatches and blank colorful poster cards on a concrete studio desk'
) !== null, 'automotive rejects poster flat-lay that misses the car');

// Marketing SaaS still gets capability creatives, not perfume/furniture.
$mktFacts = fixture_facts(
    ['product_name' => 'RATEB AI', 'title' => 'RATEB AI', 'description' => 'منصة تنشئ إعلانات وصور وفيديوهات للحملات التسويقية'],
    ['product' => 'RATEB AI', 'description' => 'منصة سعودية تنشئ إعلانات وصور وفيديوهات للحملات التسويقية']
);
$mkt = rateb_image_creative_direction($mktFacts, 0);
check(is_array($mkt) && str_contains(strtolower((string) $mkt['subject']), 'campaign'), 'marketing SaaS creative mentions campaign outputs');
check(is_array($mkt) && !str_contains(strtolower((string) $mkt['subject']), 'perfume'), 'marketing SaaS is not perfume');
check(rateb_image_plan_recover_marketing($mktFacts) !== null, 'marketing recover still available from creative direction');

// Missing essential info: insufficient brief returns null direction.
check(rateb_image_creative_direction(['product' => 'خدمة', 'description' => ''], 0) === null, 'insufficient brief does not invent a concept');

echo $failures === 0 ? "RESULT=PASS\n" : "RESULT=FAIL ({$failures})\n";
exit($failures === 0 ? 0 : 1);
