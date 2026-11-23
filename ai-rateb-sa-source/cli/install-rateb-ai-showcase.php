<?php
declare(strict_types=1);

/**
 * Install a professional RATEB AI showcase campaign (idea/strategy/copy),
 * remove low-quality draft AI images, and restore the wasted image credit.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/plans.php';

$campaignId = (int) ($argv[1] ?? 80);
$userId = (int) ($argv[2] ?? 66);
$pdo = db();

$campaign = $pdo->prepare('SELECT * FROM campaigns WHERE id = ? AND user_id = ?');
$campaign->execute([$campaignId, $userId]);
$row = $campaign->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    fwrite(STDERR, "campaign_missing\n");
    exit(1);
}

$title = 'RATEB AI — حملة إطلاق احترافية';
$product = 'RATEB AI';
$description = 'RATEB AI منصة سعودية لإنشاء وإدارة الحملات التسويقية بالذكاء الاصطناعي: فكرة الحملة، الاستراتيجية، النصوص الإعلانية، الصور، الصوت، والفيديو — من مكان واحد لأصحاب المشاريع والشركات والمسوقين.';
$audience = 'أصحاب المشاريع الصغيرة والمتوسطة، فرق التسويق، والوكالات في السعودية والخليج';
$objective = 'زيادة التسجيلات التجريبية والوعي بالمنصة كاستوديو حملات متكامل';
$channels = 'إنستغرام، سناب شات، لينكدإن، إعلانات جوجل، واتساب';
$tone = 'احترافي، واضح، سعودي معاصر، واثق بدون مبالغة';

$pdo->prepare('UPDATE campaigns SET title = ?, product_name = ?, description = ?, target_customer = ?, objective = ?, status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?')
    ->execute([$title, $product, $description, $audience, $objective, 'in_progress', $campaignId, $userId]);

$briefExists = $pdo->prepare('SELECT id FROM campaign_briefs WHERE campaign_id = ? AND user_id = ?');
$briefExists->execute([$campaignId, $userId]);
if ($briefExists->fetch()) {
    $pdo->prepare('UPDATE campaign_briefs SET product = ?, description = ?, audience = ?, objective = ?, location = ?, brand_tone = ?, channels = ?, core_message = ?, creative_direction = ?, campaign_language = ?, resolved_language = ?, updated_at = NOW() WHERE campaign_id = ? AND user_id = ?')
        ->execute([
            $product,
            $description,
            $audience,
            $objective,
            'السعودية والخليج',
            $tone,
            $channels,
            'حملتك التسويقية كاملة بالذكاء الاصطناعي — من الفكرة إلى النشر.',
            'مشاهد مكتبية احترافية لمواد إعلانية مطبوعة وملفات حملة جاهزة، أسلوب تصوير تجاري نظيف، ألوان RATEB بنفسجي/أزرق، بدون واجهات تطبيقات مجردة.',
            'ar',
            'ar',
            $campaignId,
            $userId,
        ]);
} else {
    $pdo->prepare('INSERT INTO campaign_briefs (campaign_id, user_id, product, description, audience, objective, location, brand_tone, channels, core_message, creative_direction, campaign_language, resolved_language) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $campaignId,
            $userId,
            $product,
            $description,
            $audience,
            $objective,
            'السعودية والخليج',
            $tone,
            $channels,
            'حملتك التسويقية كاملة بالذكاء الاصطناعي — من الفكرة إلى النشر.',
            'مشاهد مكتبية احترافية لمواد إعلانية مطبوعة وملفات حملة جاهزة، أسلوب تصوير تجاري نظيف، ألوان RATEB بنفسجي/أزرق، بدون واجهات تطبيقات مجردة.',
            'ar',
            'ar',
        ]);
}

$strategy = [
    'positioning' => 'RATEB AI هو استوديو الحملات التسويقية بالذكاء الاصطناعي للشركات السعودية: من الفكرة إلى المحتوى والصورة والصوت والفيديو في منصة واحدة.',
    'core_message' => 'أنشئ حملتك التسويقية كاملة خلال دقائق — استراتيجية، نصوص، صور، صوت، وفيديو — بدل التنقل بين أدوات متعددة.',
    'audience' => $audience,
    'channels' => $channels,
    'tone' => $tone,
    'creative_direction' => 'صور فوتوغرافية تجارية لمواد إعلانية مطبوعة (بوسترات ومنشورات) على مكتب خشبي بإضاءة طبيعية، ألوان بنفسجي وأزرق هادئة، بدون نصوص عربية داخل الصورة وبدون لوحات معلومات رقمية مجردة.',
    'call_to_action' => 'ابدأ حملتك على RATEB AI الآن',
];

$copy = [
    'headline' => 'حملتك التسويقية كاملة… بالذكاء الاصطناعي',
    'short_ad' => 'من الفكرة إلى الفيديو — RATEB AI ينجز حملتك في منصة واحدة.',
    'ad_copy' => "RATEB AI يساعد أصحاب المشاريع وفرق التسويق على بناء حملات متكاملة بسرعة: استراتيجية واضحة، نصوص إعلانية جاهزة، صور بالذكاء الاصطناعي، صوت عربي، وفيديو قصير.\nوفّر الوقت، وحافظ على جودة احترافية من مكان واحد.\nابدأ حملتك الآن على ai.rateb.sa",
    'call_to_action' => 'ابدأ حملتك مجانًا',
    'whatsapp' => 'مرحبًا، حاب أبدأ حملة تسويقية على RATEB AI — كيف أسجّل؟',
    'product_description' => 'منصة سعودية لإنشاء وإدارة الحملات التسويقية بالذكاء الاصطناعي: الفكرة، الاستراتيجية، النصوص، الصور، الصوت، والفيديو.',
    'social_posts' => json_encode([
        'snapchat' => 'حملتك من الفكرة للفيديو… في منصة واحدة. RATEB AI',
        'instagram' => "أنشئ حملتك التسويقية كاملة بالذكاء الاصطناعي مع RATEB AI.\nاستراتيجية + نصوص + صور + صوت + فيديو.\nابدأ الآن على ai.rateb.sa\n#RATEBAI #تسويق #ذكاء_اصطناعي #السعودية",
        'x' => 'RATEB AI: استوديو حملات بالذكاء الاصطناعي — من الفكرة إلى الفيديو في منصة واحدة. ai.rateb.sa',
        'google_ads' => [
            'headlines' => ['حملتك بالذكاء الاصطناعي', 'RATEB AI للحملات', 'من الفكرة إلى الفيديو'],
            'descriptions' => [
                'أنشئ استراتيجية ونصوص وصور وفيديو لحملتك من منصة واحدة.',
                'منصة سعودية لأصحاب المشاريع وفرق التسويق. ابدأ الآن.',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE),
];

$upsert = static function (PDO $pdo, int $campaignId, string $type, string $content, string $status = 'approved') {
    $pdo->prepare('DELETE FROM campaign_outputs WHERE campaign_id = ? AND output_type = ?')->execute([$campaignId, $type]);
    $pdo->prepare("INSERT INTO campaign_outputs (campaign_id, output_type, content, approval_status) VALUES (?, ?, ?, ?)")
        ->execute([$campaignId, $type, $content, $status]);
};

$upsert($pdo, $campaignId, 'strategy', json_encode($strategy, JSON_UNESCAPED_UNICODE), 'approved');
foreach ($copy as $type => $content) {
    $upsert($pdo, $campaignId, $type, (string) $content, 'approved');
}

$deleted = 0;
$media = $pdo->prepare("SELECT id, stored_name FROM campaign_media WHERE campaign_id = ? AND kind = 'image' AND source = 'ai' AND approval_status = 'draft'");
$media->execute([$campaignId]);
foreach ($media->fetchAll(PDO::FETCH_ASSOC) as $item) {
    $name = (string) ($item['stored_name'] ?? '');
    if ($name !== '' && defined('MEDIA_ROOT')) {
        $path = rtrim((string) MEDIA_ROOT, '/\\') . '/' . $campaignId . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
    $pdo->prepare('DELETE FROM campaign_media WHERE id = ? AND campaign_id = ?')->execute([(int) $item['id'], $campaignId]);
    $deleted++;
}

$key = 'restore_bad_seed_image:campaign:' . $campaignId . ':20261123';
$seen = $pdo->prepare('SELECT id FROM usage_events WHERE idempotency_key = ? LIMIT 1');
$seen->execute([$key]);
$restored = 0;
if (!$seen->fetch()) {
    $pdo->prepare('UPDATE users SET credits_balance = credits_balance + 10 WHERE id = ?')->execute([$userId]);
    $pdo->prepare("INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted, idempotency_key) VALUES (?, ?, 'credit_restore', 1, 'completed', 'credit_restore', 0, ?)")
        ->execute([$userId, $campaignId, $key]);
    $restored = 10;
}

$balance = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = ' . $userId)->fetchColumn();
echo json_encode([
    'ok' => true,
    'campaign_id' => $campaignId,
    'title' => $title,
    'deleted_draft_ai_images' => $deleted,
    'credits_restored' => $restored,
    'credits_balance' => $balance,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
