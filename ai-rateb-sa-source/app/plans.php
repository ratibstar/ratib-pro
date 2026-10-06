<?php
declare(strict_types=1);

function rateb_language_values(): array
{
    return ['ar', 'en', 'bilingual', 'auto'];
}

function rateb_valid_language(string $value): string
{
    return in_array($value, rateb_language_values(), true) ? $value : 'auto';
}

function rateb_ui_error(string $code): string
{
    $arabic = ui_language() === 'ar';
    $messages = [
        'plan_inactive' => $arabic ? 'هذه الباقة غير متاحة.' : 'This plan is not available.',
        'plan_limit' => $arabic ? 'وصلت إلى حد باقتك لهذا الشهر.' : 'You have reached your plan limit for this month.',
        'video_too_long' => $arabic ? 'مدة الفيديو أطول من المسموح في باقتك.' : 'This video is longer than your plan allows.',
        'watermark' => $arabic ? 'تعذر إضافة علامة RATEB AI على الملف.' : 'The RATEB AI mark could not be applied to the file.',
        'ai_failed' => $arabic ? 'تعذر على RATEB إكمال الطلب. لم يُحفظ شيء.' : 'RATEB could not complete the request. Nothing was saved.',
        'ai_unconfigured' => $arabic ? 'توليد النص غير متاح حاليًا.' : 'Text generation is not available right now.',
        'strategy_locked' => $arabic ? 'الاستراتيجية معتمدة. أكّد إعادة التوليد قبل استبدالها.' : 'The strategy is approved. Confirm regeneration before replacing it.',
        'copy_locked' => $arabic ? 'اعتمد الاستراتيجية أولًا.' : 'Approve the strategy first.',
        'output_locked' => $arabic ? 'هذا النص معتمد. أكّد إعادة التوليد قبل استبداله.' : 'This copy is approved. Confirm regeneration before replacing it.',
        'invalid_output' => $arabic ? 'النتيجة غير صالحة، ولم تُحفظ.' : 'The result was not valid, so it was not saved.',
        'edit_unclear' => $arabic ? 'ما وضحت التعديل. اكتب التغيير الذي تبيه.' : 'The change was not clear. Write the change you want.',
        'edit_empty' => $arabic ? 'اكتب التعديل أولًا.' : 'Write the change first.',
        'brief_missing' => $arabic ? 'أكمل ملخص الحملة قبل البدء.' : 'Complete the campaign brief before starting.',
        'image_locked' => $arabic ? 'اعتمد النص أولًا، ثم ابدأ الصور.' : 'Approve the copy first, then start the images.',
        'image_provider' => $arabic ? 'تعذر توليد الصورة. حاول مرة أخرى.' : 'The image could not be generated. Try again.',
        'image_workers' => $arabic ? 'مزود الصور غير متاح الآن. حاول بعد قليل.' : 'The image provider is not available right now. Try again shortly.',
        'voice_locked' => $arabic ? 'اعتمد النص أولًا، ثم ابدأ الصوت.' : 'Approve the copy first, then start the voice.',
        'voice_provider' => $arabic ? 'تعذر توليد الصوت. حاول مرة أخرى.' : 'The voice could not be generated. Try again.',
        'voice_terms' => $arabic ? 'نموذج الصوت موجود، لكن شروط استخدامه غير مقبولة لهذا الحساب.' : 'The speech model exists, but its terms are not accepted for this account.',
    ];
    return $messages[$code] ?? ($arabic ? 'تعذر تنفيذ الطلب.' : 'The request could not be completed.');
}

function rateb_deny(string $code): void
{
    http_response_code(402);
    echo json_encode(['error' => rateb_ui_error($code), 'code' => $code], JSON_UNESCAPED_UNICODE);
    exit;
}

function rateb_lock_plan(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("SELECT s.id AS subscription_id, s.plan_id, p.code, p.name_ar, p.name_en, p.price_sar, p.video_limit, p.video_max_seconds, p.image_limit, p.watermark, p.is_active
        FROM subscriptions s
        INNER JOIN plans p ON p.id = s.plan_id
        WHERE s.user_id = ? AND s.status = 'active'
        ORDER BY s.id DESC
        LIMIT 1
        FOR UPDATE");
    $stmt->execute([$userId]);
    $plan = $stmt->fetch();
    if ($plan) {
        return $plan;
    }
    $freeId = (int) $pdo->query("SELECT id FROM plans WHERE code = 'free' LIMIT 1")->fetchColumn();
    if ($freeId < 1) {
        throw new RuntimeException('Missing free plan');
    }
    $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, status, period_start, period_end) VALUES (?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))")->execute([$userId, $freeId]);
    $stmt->execute([$userId]);
    $plan = $stmt->fetch();
    if (!$plan) {
        throw new RuntimeException('Missing subscription');
    }
    return $plan;
}

function rateb_user_plan(PDO $pdo, int $userId): array
{
    $owned = !$pdo->inTransaction();
    if ($owned) {
        $pdo->beginTransaction();
    }
    try {
        $plan = rateb_lock_plan($pdo, $userId);
        if ($owned) {
            $pdo->commit();
        }
        return $plan;
    } catch (Throwable $error) {
        if ($owned && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function rateb_usage_count(PDO $pdo, int $userId, string $operation): int
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(units), 0) FROM usage_events
        WHERE user_id = ? AND operation = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        AND (status = 'completed' OR (status = 'reserved' AND created_at >= DATE_SUB(NOW(), INTERVAL 20 MINUTE)))");
    $stmt->execute([$userId, $operation]);
    return (int) $stmt->fetchColumn();
}

function rateb_usage_begin(PDO $pdo, int $userId, ?int $campaignId, string $operation, ?int $seconds = null): array
{
    $allowed = ['image', 'video', 'campaign_text', 'voice', 'variation'];
    if (!in_array($operation, $allowed, true)) {
        return ['ok' => false, 'code' => 'plan_inactive', 'id' => 0];
    }
    $pdo->beginTransaction();
    try {
        $plan = rateb_lock_plan($pdo, $userId);
        if ((int) $plan['is_active'] !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'code' => 'plan_inactive', 'id' => 0];
        }
        if ($operation === 'video' && $seconds !== null && $seconds > (int) $plan['video_max_seconds']) {
            $pdo->rollBack();
            return ['ok' => false, 'code' => 'video_too_long', 'id' => 0];
        }
        $limit = null;
        if ($operation === 'image') {
            $limit = (int) $plan['image_limit'];
        } elseif ($operation === 'video') {
            $limit = (int) $plan['video_limit'];
        }
        if ($limit !== null && rateb_usage_count($pdo, $userId, $operation) >= $limit) {
            $pdo->rollBack();
            return ['ok' => false, 'code' => 'plan_limit', 'id' => 0];
        }
        $insert = $pdo->prepare('INSERT INTO usage_events (user_id, campaign_id, operation, units, seconds, status) VALUES (?, ?, ?, 1, ?, \'reserved\')');
        $insert->execute([$userId, $campaignId, $operation, $seconds]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return ['ok' => true, 'code' => '', 'id' => $id, 'watermark' => (int) $plan['watermark'] === 1];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function rateb_usage_finish(PDO $pdo, int $eventId, string $status): void
{
    if ($eventId < 1 || !in_array($status, ['completed', 'failed'], true)) {
        return;
    }
    $pdo->prepare('UPDATE usage_events SET status = ? WHERE id = ? AND status = \'reserved\'')->execute([$status, $eventId]);
}

function rateb_watermark_required(PDO $pdo, int $userId): bool
{
    return (int) rateb_user_plan($pdo, $userId)['watermark'] === 1;
}

function rateb_sync_brief(PDO $pdo, int $userId, int $campaignId, array $input): bool
{
    $owned = $pdo->prepare('SELECT product_name, description, target_customer, objective FROM campaigns WHERE id = ? AND user_id = ? LIMIT 1');
    $owned->execute([$campaignId, $userId]);
    $campaign = $owned->fetch();
    if (!$campaign) {
        return false;
    }
    $language = rateb_valid_language((string) ($input['campaign_language'] ?? 'auto'));
    $product = mb_substr(trim((string) ($input['product'] ?? $campaign['product_name'])), 0, 255);
    $description = trim((string) ($input['description'] ?? $campaign['description']));
    $audience = mb_substr(trim((string) ($input['audience'] ?? $campaign['target_customer'])), 0, 255);
    $objective = mb_substr(trim((string) ($input['objective'] ?? $campaign['objective'])), 0, 500);
    $current = $pdo->prepare('SELECT id, campaign_language FROM campaign_briefs WHERE campaign_id = ? AND user_id = ? LIMIT 1');
    $current->execute([$campaignId, $userId]);
    $brief = $current->fetch();
    if (!$brief) {
        $pdo->prepare('INSERT INTO campaign_briefs (campaign_id, user_id, product, description, audience, objective, campaign_language, resolved_language) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
            $campaignId,
            $userId,
            $product,
            $description,
            $audience,
            $objective,
            $language,
            $language === 'auto' ? null : ($language === 'bilingual' ? 'bilingual' : $language),
        ]);
        rateb_brief_extra($pdo, $userId, $campaignId, $input);
        return true;
    }
    $resolved = $language === 'auto' ? null : ($language === 'bilingual' ? 'bilingual' : $language);
    if ($language === 'auto') {
        $pdo->prepare('UPDATE campaign_briefs SET product = ?, description = ?, audience = ?, objective = ?, campaign_language = ?, resolved_language = NULL WHERE id = ? AND user_id = ?')->execute([
            $product, $description, $audience, $objective, $language, (int) $brief['id'], $userId,
        ]);
        rateb_brief_extra($pdo, $userId, $campaignId, $input);
        return true;
    }
    $pdo->prepare('UPDATE campaign_briefs SET product = ?, description = ?, audience = ?, objective = ?, campaign_language = ?, resolved_language = ? WHERE id = ? AND user_id = ?')->execute([
        $product, $description, $audience, $objective, $language, $resolved, (int) $brief['id'], $userId,
    ]);
    rateb_brief_extra($pdo, $userId, $campaignId, $input);
    return true;
}

function rateb_brief_extra(PDO $pdo, int $userId, int $campaignId, array $input): void
{
    $sets = [];
    $values = [];
    foreach (['location' => 160, 'channels' => 255, 'brand_tone' => 160] as $key => $limit) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $sets[] = $key . ' = ?';
        $values[] = mb_substr(trim((string) $input[$key]), 0, $limit);
    }
    foreach (['core_message', 'source_media', 'media_analysis'] as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $sets[] = $key . ' = ?';
        $values[] = $input[$key] === null ? null : trim((string) $input[$key]);
    }
    if ($sets === []) {
        return;
    }
    $values[] = $campaignId;
    $values[] = $userId;
    $pdo->prepare('UPDATE campaign_briefs SET ' . implode(', ', $sets) . ' WHERE campaign_id = ? AND user_id = ?')->execute($values);
}

function rateb_output_language(PDO $pdo, array $campaign): array
{
    $userId = (int) ($campaign['user_id'] ?? 0);
    $campaignId = (int) ($campaign['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM campaign_briefs WHERE campaign_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$campaignId, $userId]);
    if (!$stmt->fetch()) {
        rateb_sync_brief($pdo, $userId, $campaignId, [
            'product' => (string) ($campaign['product_name'] ?? ''),
            'description' => (string) ($campaign['description'] ?? ''),
            'audience' => (string) ($campaign['target_customer'] ?? ''),
            'objective' => (string) ($campaign['objective'] ?? ''),
            'campaign_language' => 'auto',
        ]);
    }
    $stmt = $pdo->prepare('SELECT * FROM campaign_briefs WHERE campaign_id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$campaignId, $userId]);
    $brief = $stmt->fetch() ?: [];
    $choice = rateb_valid_language((string) ($brief['campaign_language'] ?? 'auto'));
    $resolved = (string) ($brief['resolved_language'] ?? '');
    if ($choice === 'auto' && !in_array($resolved, ['ar', 'en'], true)) {
        $sample = trim((string) ($campaign['description'] ?? '') . ' ' . (string) ($campaign['title'] ?? '') . ' ' . (string) ($campaign['product_name'] ?? ''));
        $resolved = preg_match('/\p{Arabic}/u', $sample) === 1 ? 'ar' : 'en';
        $pdo->prepare('UPDATE campaign_briefs SET resolved_language = ? WHERE campaign_id = ? AND user_id = ?')->execute([$resolved, $campaignId, $userId]);
    }
    if ($choice === 'ar' || $choice === 'en') {
        $resolved = $choice;
    } elseif ($choice === 'bilingual') {
        $resolved = 'bilingual';
    }
    if ($resolved === 'ar') {
        $instruction = 'Write every JSON value in Arabic only. Do not mix in English words.';
        $speech = 'ar';
    } elseif ($resolved === 'en') {
        $instruction = 'Write every JSON value in English only. Do not mix in Arabic words.';
        $speech = 'en';
    } else {
        $instruction = 'For every JSON value, return an object with keys ar and en. The ar value is Arabic only. The en value is English only. Do not mix languages inside one value.';
        $sample = trim((string) ($campaign['description'] ?? '') . ' ' . (string) ($campaign['title'] ?? ''));
        $speech = preg_match('/\p{Arabic}/u', $sample) === 1 ? 'ar' : 'en';
    }
    return ['choice' => $choice, 'resolved' => $resolved, 'instruction' => $instruction, 'speech' => $speech];
}

function rateb_strategy_keys(): array
{
    return ['positioning', 'core_message', 'audience', 'channels', 'tone', 'creative_direction', 'call_to_action'];
}

function rateb_copy_types(): array
{
    return ['headline', 'ad_copy', 'short_ad', 'social_posts', 'whatsapp', 'call_to_action', 'product_description'];
}

function rateb_text_fits(string $text, string $resolved): bool
{
    $text = trim($text);
    if ($text === '') {
        return false;
    }
    $arabic = preg_match_all('/\p{Arabic}/u', $text) ?: 0;
    $latin = preg_match_all('/[A-Za-z]/', $text) ?: 0;
    $total = $arabic + $latin;
    if ($total < 1) {
        return false;
    }
    if ($resolved === 'ar') {
        return $arabic >= 4 && ($arabic / $total) >= 0.5;
    }
    if ($resolved === 'en') {
        return $latin >= 4 && ($arabic / $total) <= 0.35;
    }
    return false;
}

function rateb_coerce_output(mixed $value, string $resolved): mixed
{
    if ($resolved === 'bilingual') {
        return $value;
    }
    if (is_string($value)) {
        return trim($value);
    }
    if (is_array($value) && isset($value[$resolved]) && is_string($value[$resolved])) {
        return trim($value[$resolved]);
    }
    if (is_array($value) && array_is_list($value)) {
        $parts = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $parts[] = trim($item);
            }
        }
        if ($parts !== []) {
            return implode("\n\n", $parts);
        }
    }
    return $value;
}

function rateb_value_fits(mixed $value, string $resolved): bool
{
    if ($resolved === 'bilingual') {
        return is_array($value)
            && rateb_text_fits((string) ($value['ar'] ?? ''), 'ar')
            && rateb_text_fits((string) ($value['en'] ?? ''), 'en');
    }
    return is_string($value) && rateb_text_fits($value, $resolved);
}

function rateb_strategy_valid(array $data, string $resolved): bool
{
    foreach (rateb_strategy_keys() as $key) {
        if (!rateb_value_fits($data[$key] ?? null, $resolved)) {
            return false;
        }
    }
    return true;
}

function rateb_copy_valid(array $data, string $resolved, string $only = ''): bool
{
    $keys = $only !== '' ? [$only] : rateb_copy_types();
    foreach ($keys as $key) {
        if (!rateb_value_fits($data[$key] ?? null, $resolved)) {
            return false;
        }
    }
    return true;
}

function rateb_normalize_value(mixed $value, string $resolved): mixed
{
    if ($resolved === 'bilingual' && is_array($value)) {
        return [
            'ar' => trim((string) ($value['ar'] ?? '')),
            'en' => trim((string) ($value['en'] ?? '')),
        ];
    }
    return trim((string) $value);
}

function rateb_field_parts(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        }
    }
    if (is_array($value) && isset($value['ar'], $value['en']) && is_string($value['ar']) && is_string($value['en'])) {
        return [
            ['code' => 'ar', 'text' => $value['ar']],
            ['code' => 'en', 'text' => $value['en']],
        ];
    }
    if (is_string($value) && trim($value) !== '') {
        return [['code' => '', 'text' => $value]];
    }
    return [];
}

function rateb_strategy_document(string $content): ?array
{
    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
        return null;
    }
    foreach (rateb_strategy_keys() as $key) {
        if (!array_key_exists($key, $decoded) || rateb_field_parts($decoded[$key]) === []) {
            return null;
        }
    }
    return $decoded;
}

function rateb_workspace_facts(PDO $pdo, array $campaign, array $brief): string
{
    $lines = [];
    $map = [
        'Product' => (string) ($brief['product'] ?? $campaign['product_name'] ?? ''),
        'Idea' => (string) ($brief['description'] ?? $campaign['description'] ?? ''),
        'Objective' => (string) ($brief['objective'] ?? $campaign['objective'] ?? ''),
        'Audience' => (string) ($brief['audience'] ?? $campaign['target_customer'] ?? ''),
        'Location' => (string) ($brief['location'] ?? ''),
        'Channels' => (string) ($brief['channels'] ?? ''),
        'Tone' => (string) ($brief['brand_tone'] ?? ''),
    ];
    foreach ($map as $label => $value) {
        $value = trim($value);
        if ($value !== '') {
            $lines[] = $label . ': ' . $value;
        }
    }
    $brand = $pdo->prepare('SELECT brand_name, tone, contact FROM brand_kits WHERE user_id = ?');
    $brand->execute([(int) ($campaign['user_id'] ?? 0)]);
    $kit = $brand->fetch();
    if ($kit) {
        foreach (['brand_name' => 'Brand name', 'tone' => 'Brand tone', 'contact' => 'Contact'] as $field => $label) {
            $value = trim((string) ($kit[$field] ?? ''));
            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }
    }
    return $lines === [] ? 'No facts were provided.' : implode("\n", $lines);
}
