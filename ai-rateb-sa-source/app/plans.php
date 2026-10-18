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
        'edit_unclear' => $arabic ? 'اكتب التغيير الذي تبيه بصراحة. مثال: غيّر الفكرة إلى ترويج لبرنامج رتب.' : 'Write the change you want. Example: change the idea to a RATEB promotion.',
        'edit_empty' => $arabic ? 'اكتب التعديل أولًا.' : 'Write the change first.',
        'brief_missing' => $arabic ? 'أكمل ملخص الحملة قبل البدء.' : 'Complete the campaign brief before starting.',
        'image_locked' => $arabic ? 'اعتمد النص أولًا، ثم ابدأ الصور.' : 'Approve the copy first, then start the images.',
        'image_provider' => $arabic ? 'تعذر توليد الصورة. حاول مرة أخرى.' : 'The image could not be generated. Try again.',
        'image_workers' => $arabic ? 'مزود الصور غير متاح الآن. حاول بعد قليل.' : 'The image provider is not available right now. Try again shortly.',
        'voice_locked' => $arabic ? 'اعتمد النص أولًا، ثم ابدأ الصوت.' : 'Approve the copy first, then start the voice.',
        'voice_provider' => $arabic ? 'تعذر توليد الصوت. حاول مرة أخرى.' : 'The voice could not be generated. Try again.',
        'voice_english' => $arabic ? 'توليد الكلام الإنجليزي غير متاح حاليًا. نموذج الكلام الحالي عربي فقط.' : 'English speech is currently unavailable. The current speech model is Arabic only.',
        'voice_terms' => $arabic ? 'نموذج الصوت موجود، لكن شروط استخدامه غير مقبولة لهذا الحساب.' : 'The speech model exists, but its terms are not accepted for this account.',
        'image_timeout' => $arabic ? 'انتهت مهلة توليد الصورة. لم تُحفظ صورة جديدة.' : 'Image generation timed out. No new image was saved.',
        'write_busy' => $arabic ? 'RATEB يكتب الآن.' : 'RATEB is already writing.',
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

function rateb_video_is_free(array $plan): bool
{
    return (string) ($plan['code'] ?? '') === 'free' || (float) ($plan['price_sar'] ?? 1) <= 0;
}

function rateb_video_duration_code(array $plan, int $seconds): string
{
    if (!in_array($seconds, [5, 10, 15, 20, 30], true)) {
        return 'invalid';
    }
    $max = rateb_video_is_free($plan) ? 10 : (int) ($plan['video_max_seconds'] ?? 0);
    if ($seconds <= $max) {
        return '';
    }
    return $seconds >= 15 ? 'video_paid' : 'video_too_long';
}

/** @return array<int,array{seconds:int,enabled:bool,paid:bool}> */
function rateb_video_duration_options(array $plan): array
{
    $options = [];
    foreach ([5, 10, 15, 20, 30] as $seconds) {
        $options[] = [
            'seconds' => $seconds,
            'enabled' => rateb_video_duration_code($plan, $seconds) === '',
            'paid' => $seconds >= 15,
        ];
    }
    return $options;
}

function rateb_get_feature_cost(string $type): int
{
    return match ($type) {
        'text_campaign' => 5,
        'ai_image' => 10,
        'saudi_voice' => 15,
        'video_storyboard' => 20,
        default => 0,
    };
}

function rateb_account_credits(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT package_type, credits_balance, subscription_expires_at FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch() ?: [];
    return [
        'package_type' => (string) ($row['package_type'] ?? 'free'),
        'credits_balance' => (int) ($row['credits_balance'] ?? 0),
        'subscription_expires_at' => $row['subscription_expires_at'] ?? null,
    ];
}

function rateb_check_and_deduct_credits(int $userId, string $featureType, ?int $campaignId = null): bool
{
    $cost = rateb_get_feature_cost($featureType);
    if ($userId < 1 || $cost < 1) {
        return false;
    }
    $package = rateb_account_credits(db(), $userId)['package_type'];
    if ($package === 'free' && $featureType === 'video_storyboard') {
        return false;
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE users SET credits_balance = credits_balance - ? WHERE id = ? AND credits_balance >= ?');
        $update->execute([$cost, $userId, $cost]);
        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }
        $pdo->prepare('INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted) VALUES (?, ?, ?, 1, \'completed\', ?, ?)')
            ->execute([$userId, $campaignId, $featureType, $featureType, $cost]);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function rateb_campaign_feature_paid(int $userId, string $featureType, int $campaignId): bool
{
    $stmt = db()->prepare("SELECT
        SUM(operation = ? AND credits_deducted > 0) AS charged,
        SUM(operation = ?) AS refunded
        FROM usage_events WHERE user_id = ? AND campaign_id = ?");
    $stmt->execute([$featureType, $featureType . '_refund', $userId, $campaignId]);
    $row = $stmt->fetch() ?: [];
    return ((int) ($row['charged'] ?? 0)) - ((int) ($row['refunded'] ?? 0)) > 0;
}

function rateb_refund_credits(int $userId, string $featureType, ?int $campaignId = null): void
{
    $cost = rateb_get_feature_cost($featureType);
    if ($userId < 1 || $cost < 1) {
        return;
    }
    $pdo = db();
    $pdo->prepare('UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?')->execute([$cost, $userId]);
    $pdo->prepare('INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted) VALUES (?, ?, ?, 1, \'completed\', ?, 0)')
        ->execute([$userId, $campaignId, $featureType . '_refund', $featureType . '_refund']);
}

function rateb_feature_locked(int $userId, string $featureType): bool
{
    return $featureType === 'video_storyboard'
        && rateb_account_credits(db(), $userId)['package_type'] === 'free';
}

function rateb_credit_stop(int $userId = 0, string $featureType = ''): void
{
    http_response_code(402);
    if ($userId > 0 && rateb_feature_locked($userId, $featureType)) {
        echo json_encode(['error' => 'هذه الميزة غير متاحة في الباقة المجانية، اضغط هنا لترقية الباقة', 'code' => 'package'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['error' => 'رصيد النقاط غير كافٍ، اضغط هنا لترقية الباقة', 'code' => 'credits'], JSON_UNESCAPED_UNICODE);
    exit;
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
        if ($operation === 'video' && $seconds !== null) {
            $durationCode = rateb_video_duration_code($plan, $seconds);
            if ($durationCode !== '') {
                $pdo->rollBack();
                return ['ok' => false, 'code' => $durationCode, 'id' => 0];
            }
        }
        $limit = null;
        if ($operation === 'image') {
            $limit = (int) $plan['image_limit'];
        } elseif ($operation === 'video') {
            $limit = rateb_video_is_free($plan) ? 2 : (int) $plan['video_limit'];
        }
        if ($limit !== null && rateb_usage_count($pdo, $userId, $operation) >= $limit) {
            $pdo->rollBack();
            return ['ok' => false, 'code' => ($operation === 'video' && rateb_video_is_free($plan)) ? 'video_free_used' : 'plan_limit', 'id' => 0];
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

function rateb_voice_choices(string $speech): array
{
    if ($speech === 'en') {
        return [
            ['id' => 'austin', 'label' => 'Austin', 'gender' => 'm'],
            ['id' => 'daniel', 'label' => 'Daniel', 'gender' => 'm'],
            ['id' => 'troy', 'label' => 'Troy', 'gender' => 'm'],
            ['id' => 'autumn', 'label' => 'Autumn', 'gender' => 'f'],
            ['id' => 'diana', 'label' => 'Diana', 'gender' => 'f'],
            ['id' => 'hannah', 'label' => 'Hannah', 'gender' => 'f'],
        ];
    }
    return [
        ['id' => 'abdullah', 'label' => 'عبدالله', 'gender' => 'm'],
        ['id' => 'fahad', 'label' => 'فهد', 'gender' => 'm'],
        ['id' => 'sultan', 'label' => 'سلطان', 'gender' => 'm'],
        ['id' => 'lulwa', 'label' => 'لولوة', 'gender' => 'f'],
        ['id' => 'noura', 'label' => 'نورة', 'gender' => 'f'],
        ['id' => 'aisha', 'label' => 'عائشة', 'gender' => 'f'],
    ];
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
        return $arabic >= 2 && ($arabic / $total) >= 0.25;
    }
    if ($resolved === 'en') {
        return $latin >= 2 && ($arabic / $total) <= 0.5;
    }
    return false;
}

function rateb_coerce_output(mixed $value, string $resolved): mixed
{
    $languageKeys = $resolved === 'bilingual' && is_array($value) && (
        array_key_exists('ar', $value) || array_key_exists('en', $value)
        || array_key_exists('arabic', $value) || array_key_exists('english', $value)
        || array_key_exists('Arabic', $value) || array_key_exists('English', $value)
    );
    if ($languageKeys) {
        $arabic = $value['ar'] ?? $value['arabic'] ?? $value['Arabic'] ?? '';
        $english = $value['en'] ?? $value['english'] ?? $value['English'] ?? '';
        return ['ar' => trim((string) $arabic), 'en' => trim((string) $english)];
    }
    if (is_string($value)) {
        return trim($value);
    }
    if (is_array($value) && $resolved !== 'bilingual' && isset($value[$resolved]) && is_string($value[$resolved])) {
        return trim($value[$resolved]);
    }
    if (is_array($value) && !array_is_list($value)) {
        foreach (['value', 'text', 'content'] as $inner) {
            if (isset($value[$inner]) && is_string($value[$inner]) && trim($value[$inner]) !== '') {
                return trim($value[$inner]);
            }
        }
    }
    if (is_array($value) && array_is_list($value)) {
        $parts = [];
        foreach ($value as $item) {
            $text = is_string($item) ? trim($item) : rateb_coerce_output($item, $resolved === 'bilingual' ? 'ar' : $resolved);
            if (is_string($text) && $text !== '') {
                $parts[] = $text;
            }
        }
        if ($parts !== []) {
            return implode("\n\n", $parts);
        }
    }
    return $value;
}

function rateb_output_schema(array $keys, string $resolved): array
{
    $value = $resolved === 'bilingual'
        ? [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['ar', 'en'],
            'properties' => [
                'ar' => ['type' => 'string'],
                'en' => ['type' => 'string'],
            ],
        ]
        : ['type' => 'string'];
    $properties = [];
    foreach ($keys as $key) {
        $properties[$key] = $value;
    }
    return [
        'name' => 'campaign_output',
        'strict' => true,
        'schema' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_values($keys),
            'properties' => $properties,
        ],
    ];
}

function rateb_json_object(string $content): ?array
{
    $content = trim($content);
    if ($content === '') {
        return null;
    }
    $result = json_decode($content, true);
    if (is_array($result)) {
        return $result;
    }
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $content, $fenced) === 1) {
        $result = json_decode($fenced[1], true);
        if (is_array($result)) {
            return $result;
        }
    }
    if (preg_match('/(\{.*\})/s', $content, $braced) === 1) {
        $result = json_decode($braced[1], true);
        if (is_array($result)) {
            return $result;
        }
    }
    return null;
}

function rateb_output_key(string $name, array $keys): ?string
{
    $known = array_fill_keys($keys, true);
    if (isset($known[$name])) {
        return $name;
    }
    $snake = strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', str_replace(['-', ' '], '_', trim($name))));
    if (isset($known[$snake])) {
        return $snake;
    }
    $compact = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', $name));
    foreach ($keys as $key) {
        if (str_replace('_', '', $key) === $compact) {
            return $key;
        }
    }
    return null;
}

function rateb_prepare_output(mixed $result, string $resolved, array $keys): mixed
{
    if (!is_array($result)) {
        return $result;
    }
    $aliases = [
        'positioning' => ['التموضع', 'position'],
        'core_message' => ['الرسالة', 'message'],
        'audience' => ['الجمهور'],
        'channels' => ['القنوات'],
        'tone' => ['النبرة'],
        'creative_direction' => ['الاتجاه', 'direction'],
        'call_to_action' => ['الدعوة', 'cta'],
        'headline' => ['العنوان'],
        'ad_copy' => ['النص'],
        'short_ad' => ['الإعلان'],
        'social_posts' => ['المنشورات'],
        'whatsapp' => ['واتساب'],
        'product_description' => ['الوصف'],
    ];
    $canon = static function (array $input) use ($keys, $aliases): array {
        $copy = $input;
        foreach ($input as $name => $value) {
            $key = rateb_output_key((string) $name, $keys);
            if ($key !== null && !array_key_exists($key, $copy)) {
                $copy[$key] = $value;
            }
        }
        foreach ($keys as $key) {
            if (array_key_exists($key, $copy)) {
                continue;
            }
            foreach ($aliases[$key] ?? [] as $alias) {
                if (array_key_exists($alias, $copy)) {
                    $copy[$key] = $copy[$alias];
                    break;
                }
            }
        }
        return $copy;
    };
    $result = $canon($result);
    $present = 0;
    foreach ($keys as $key) {
        if (array_key_exists($key, $result)) {
            $present++;
        }
    }
    if ($present < count($keys)) {
        foreach ($result as $value) {
            if (!is_array($value) || array_is_list($value)) {
                continue;
            }
            $inner = $canon($value);
            foreach ($keys as $key) {
                if (!array_key_exists($key, $result) && array_key_exists($key, $inner)) {
                    $result[$key] = $inner[$key];
                    $present++;
                }
            }
        }
    }
    foreach ($keys as $key) {
        if (array_key_exists($key, $result)) {
            $result[$key] = rateb_coerce_output($result[$key], $resolved);
        }
    }
    return $result;
}

function rateb_text_ready(string $text, string $resolved): bool
{
    $text = trim($text);
    if ($text === '' || mb_strlen($text) > 600) {
        return false;
    }
    $arabic = preg_match_all('/\p{Arabic}/u', $text) ?: 0;
    $latin = preg_match_all('/[A-Za-z]/', $text) ?: 0;
    if ($resolved === 'ar') {
        return $arabic >= 2;
    }
    if ($resolved === 'en') {
        $total = $arabic + $latin;
        return $latin >= 2 && $total > 0 && ($arabic / $total) <= 0.5;
    }
    return false;
}

function rateb_value_ready(mixed $value, string $resolved): bool
{
    if ($resolved === 'bilingual') {
        return rateb_value_fits($value, $resolved);
    }
    return is_string($value) && rateb_text_ready($value, $resolved);
}

function rateb_output_ready(array $data, string $resolved, array $keys): bool
{
    foreach ($keys as $key) {
        if (!rateb_value_ready($data[$key] ?? null, $resolved)) {
            return false;
        }
    }
    return true;
}

function rateb_value_fits(mixed $value, string $resolved): bool
{
    if ($resolved === 'bilingual') {
        if (is_string($value)) {
            return rateb_text_fits($value, 'ar') || rateb_text_fits($value, 'en');
        }
        if (!is_array($value)) {
            return false;
        }
        $arabic = trim((string) ($value['ar'] ?? ''));
        $english = trim((string) ($value['en'] ?? ''));
        $arabicOk = $arabic === '' || rateb_text_fits($arabic, 'ar');
        $englishOk = $english === '' || rateb_text_fits($english, 'en');
        return $arabicOk && $englishOk && ($arabic !== '' || $english !== '');
    }
    return is_string($value) && rateb_text_fits($value, $resolved);
}

function rateb_output_valid(array $data, string $resolved, array $keys): bool
{
    foreach ($keys as $key) {
        if (!rateb_value_fits($data[$key] ?? null, $resolved)) {
            return false;
        }
    }
    return true;
}

function rateb_strategy_valid(array $data, string $resolved): bool
{
    return rateb_output_valid($data, $resolved, rateb_strategy_keys());
}

function rateb_copy_valid(array $data, string $resolved, string $only = ''): bool
{
    $keys = $only !== '' ? [$only] : rateb_copy_types();
    return rateb_output_valid($data, $resolved, $keys);
}

function rateb_normalize_value(mixed $value, string $resolved): mixed
{
    if ($resolved === 'bilingual') {
        if (is_string($value)) {
            $text = trim($value);
            return [
                'ar' => rateb_text_fits($text, 'ar') ? $text : '',
                'en' => rateb_text_fits($text, 'en') ? $text : '',
            ];
        }
        if (is_array($value)) {
            return [
                'ar' => trim((string) ($value['ar'] ?? '')),
                'en' => trim((string) ($value['en'] ?? '')),
            ];
        }
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

function rateb_image_job_ttl(): int
{
    return 900;
}

function rateb_image_job_active(int $createdAt, int $now): bool
{
    return $createdAt > 0 && $now >= $createdAt && ($now - $createdAt) <= rateb_image_job_ttl();
}

function rateb_video_stage_complete(bool $approvedGenerated, bool $uploaded): bool
{
    return $approvedGenerated || $uploaded;
}

function rateb_prompt_plain(mixed $value, string $prefer = 'ar'): string
{
    if (is_array($value)) {
        $prefer = $prefer === 'en' ? 'en' : 'ar';
        $primary = trim((string) ($value[$prefer] ?? ''));
        if ($primary !== '') {
            return $primary;
        }
        return trim((string) ($value[$prefer === 'ar' ? 'en' : 'ar'] ?? ''));
    }
    $text = trim((string) $value);
    if ($text === '') {
        return '';
    }
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return rateb_prompt_plain($decoded, $prefer);
    }
    return $text;
}

function rateb_brand_visual_lines(array $brand): array
{
    if ($brand === []) {
        return [];
    }
    $lines = [];
    $name = trim((string) ($brand['brand_name'] ?? ''));
    if ($name !== '') {
        $lines[] = 'Brand: ' . $name;
    }
    $tone = trim((string) ($brand['tone'] ?? ''));
    if ($tone !== '') {
        $lines[] = 'Tone: ' . $tone;
    }
    $primary = strtolower(trim((string) ($brand['primary_color'] ?? '')));
    $secondary = strtolower(trim((string) ($brand['secondary_color'] ?? '')));
    if (preg_match('/^#[0-9a-f]{6}$/', $primary) === 1 && preg_match('/^#[0-9a-f]{6}$/', $secondary) === 1) {
        $lines[] = 'Palette: ' . $primary . ' ' . $secondary;
    }
    return $lines;
}

function rateb_clip_prompt_line(string $line, int $limit = 90): string
{
    $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');
    if (mb_strlen($line) <= $limit) {
        return $line;
    }
    return rtrim(mb_substr($line, 0, $limit));
}

function rateb_visual_lines(array $campaign, array $strategy, string $copyLine, array $brand, string $prefer): array
{
    $lines = [];
    $product = trim((string) ($campaign['product_name'] ?? ''));
    if ($product !== '') {
        $lines[] = rateb_clip_prompt_line('Product: ' . $product);
    }
    $direction = rateb_prompt_plain($strategy['creative_direction'] ?? '', $prefer);
    if ($direction !== '') {
        $lines[] = rateb_clip_prompt_line('Creative direction: ' . $direction);
    }
    $message = rateb_prompt_plain($strategy['core_message'] ?? '', $prefer);
    if ($message !== '') {
        $lines[] = rateb_clip_prompt_line('Message: ' . $message);
    }
    $copyLine = trim($copyLine);
    if ($copyLine !== '') {
        $lines[] = rateb_clip_prompt_line('Copy: ' . $copyLine);
    }
    foreach (rateb_brand_visual_lines($brand) as $line) {
        $lines[] = rateb_clip_prompt_line($line);
    }
    return $lines;
}

function rateb_visual_prompt(array $lines, string $constraint, int $limit = 400): string
{
    $constraint = trim(preg_replace('/\s+/u', ' ', $constraint) ?? '');
    $kept = [];
    $used = $constraint === '' ? 0 : mb_strlen($constraint);
    foreach ($lines as $line) {
        $line = trim(preg_replace('/\s+/u', ' ', (string) $line) ?? '');
        if ($line === '') {
            continue;
        }
        $extra = mb_strlen($line) + (($kept === [] && $constraint === '') ? 0 : 2);
        if ($used + $extra > $limit) {
            break;
        }
        $kept[] = $line;
        $used += $extra;
    }
    $text = implode('. ', $kept);
    if ($constraint !== '') {
        $text = $text === '' ? $constraint : $text . '. ' . $constraint;
    }
    if (mb_strlen($text) > $limit) {
        $text = rtrim(mb_substr($text, 0, $limit));
    }
    return trim($text);
}

function rateb_voice_text_is_arabic(string $script): bool
{
    return rateb_text_fits($script, 'ar');
}

function rateb_voice_plain(string $content, string $speech): string
{
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        $content = (string) ($decoded[$speech] ?? $decoded['ar'] ?? $decoded['en'] ?? '');
    }
    return trim(preg_replace('/\s+/u', ' ', $content) ?? '');
}

function rateb_voice_script_from_rows(array $rows, string $speech): string
{
    $pieces = [];
    foreach ($rows as $row) {
        if (!is_array($row) || (string) ($row['approval_status'] ?? '') !== 'approved') {
            continue;
        }
        $type = (string) ($row['output_type'] ?? '');
        if (!isset($pieces[$type])) {
            $pieces[$type] = (string) ($row['content'] ?? '');
        }
    }
    $script = '';
    $types = [];
    foreach (array_merge(['headline', 'call_to_action', 'short_ad', 'ad_copy'], rateb_copy_types()) as $type) {
        $types[$type] = true;
    }
    foreach (array_keys($types) as $type) {
        if (empty($pieces[$type])) {
            continue;
        }
        $line = rateb_voice_plain($pieces[$type], $speech);
        if ($line === '') {
            continue;
        }
        $script = $script === '' ? $line : $script . ' ' . $line;
        if (mb_strlen($script) >= 500) {
            break;
        }
    }
    return trim(mb_substr($script, 0, 600));
}

function rateb_voice_script(PDO $pdo, int $campaignId, string $speech): string
{
    $stmt = $pdo->prepare('SELECT output_type, content, approval_status FROM campaign_outputs WHERE campaign_id = ? AND approval_status = ? ORDER BY id DESC');
    $stmt->execute([$campaignId, 'approved']);
    return rateb_voice_script_from_rows($stmt->fetchAll(), $speech);
}

function rateb_image_job_submit_grace(): int
{
    return 60;
}

function rateb_image_job_directory(): string
{
    $override = getenv('RATEB_IMAGE_JOB_DIR');
    if (is_string($override) && $override !== '') {
        return $override;
    }
    if (defined('MEDIA_ROOT') && (string) MEDIA_ROOT !== '') {
        return rtrim((string) MEDIA_ROOT, '/\\') . DIRECTORY_SEPARATOR . 'image-jobs';
    }
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rateb-image-jobs';
}

/** @param array<string,mixed> $job */
function rateb_image_job_normalize(array $job): array
{
    $phase = (string) ($job['phase'] ?? 'pending');
    return [
        'job_id' => strtolower(trim((string) ($job['job_id'] ?? ''))),
        'user_id' => (int) ($job['user_id'] ?? 0),
        'campaign_id' => (int) ($job['campaign_id'] ?? 0),
        'usage_id' => (int) ($job['usage_id'] ?? 0),
        'created' => (int) ($job['created'] ?? 0),
        'phase' => $phase === 'submitting' ? 'submitting' : 'pending',
    ];
}

/** @param array<string,mixed> $job */
function rateb_image_job_key(array $job): string
{
    $jobId = (string) ($job['job_id'] ?? '');
    if (preg_match('/^[a-f0-9-]{36}$/', $jobId) === 1) {
        return $jobId;
    }
    return 'usage-' . (int) ($job['usage_id'] ?? 0);
}

function rateb_image_job_record(string $jobId, int $userId, int $campaignId, int $usageId, int $created, string $phase = 'pending'): array
{
    return rateb_image_job_normalize([
        'job_id' => $jobId,
        'user_id' => $userId,
        'campaign_id' => $campaignId,
        'usage_id' => $usageId,
        'created' => $created,
        'phase' => $phase,
    ]);
}

/** @param array<string,array<string,mixed>> $jobs */
function rateb_image_jobs_encode(array $jobs): string
{
    $list = [];
    foreach ($jobs as $job) {
        if (is_array($job)) {
            $list[] = rateb_image_job_normalize($job);
        }
    }
    $encoded = json_encode(['jobs' => $list], JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        throw new RuntimeException('image_job_store');
    }
    return $encoded;
}

/** @return array<string,array<string,mixed>> */
function rateb_image_jobs_decode(string $raw): array
{
    if (trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded) || !isset($decoded['jobs']) || !is_array($decoded['jobs'])) {
        throw new RuntimeException('image_job_store');
    }
    $jobs = [];
    foreach ($decoded['jobs'] as $job) {
        if (!is_array($job)) {
            continue;
        }
        $job = rateb_image_job_normalize($job);
        if ($job['usage_id'] < 1 && $job['job_id'] === '') {
            continue;
        }
        $jobs[rateb_image_job_key($job)] = $job;
    }
    return $jobs;
}

/** @param array<string,array<string,mixed>> $jobs */
function rateb_image_jobs_attach(array $jobs, array $job): array
{
    $job = rateb_image_job_normalize($job);
    if ($job['phase'] === 'pending' && $job['job_id'] !== '') {
        foreach ($jobs as $key => $existing) {
            if (!is_array($existing)) {
                unset($jobs[$key]);
                continue;
            }
            $existing = rateb_image_job_normalize($existing);
            if ($existing['phase'] === 'submitting' && $existing['campaign_id'] === $job['campaign_id'] && $existing['user_id'] === $job['user_id']) {
                unset($jobs[$key]);
            }
        }
    }
    $jobs[rateb_image_job_key($job)] = $job;
    return $jobs;
}

/** @param array<string,array<string,mixed>> $jobs */
function rateb_image_jobs_remove(array $jobs, string $jobId, int $usageId): array
{
    $jobId = strtolower(trim($jobId));
    foreach ($jobs as $key => $job) {
        if (!is_array($job)) {
            unset($jobs[$key]);
            continue;
        }
        $job = rateb_image_job_normalize($job);
        $sameJob = $jobId !== '' && $job['job_id'] === $jobId;
        $sameUsage = $usageId > 0 && $job['usage_id'] === $usageId && ($jobId === '' || $job['job_id'] === $jobId);
        if ($sameJob || $sameUsage) {
            unset($jobs[$key]);
        }
    }
    return $jobs;
}

/** @param array<string,array<string,mixed>> $jobs */
function rateb_image_session_trim(array $jobs, int $limit = 3): array
{
    if ($limit < 1 || count($jobs) <= $limit) {
        return $jobs;
    }
    uasort($jobs, static function (array $left, array $right): int {
        return ((int) ($left['created'] ?? 0)) <=> ((int) ($right['created'] ?? 0));
    });
    return array_slice($jobs, -$limit, null, true);
}

/**
 * @param array<string,array<string,mixed>> $jobs
 * @return array{action:string,job:?array,finish:array<int,int>,jobs:array<string,array<string,mixed>>,code:string,usage_id:int}
 */
function rateb_image_jobs_decide(array $jobs, int $userId, int $campaignId, string $postedJobId, int $now): array
{
    $postedJobId = strtolower(trim($postedJobId));
    $kept = [];
    $finish = [];
    $active = [];
    $claim = null;
    $matched = null;
    foreach ($jobs as $job) {
        if (!is_array($job)) {
            continue;
        }
        $job = rateb_image_job_normalize($job);
        if ($job['user_id'] !== $userId) {
            $kept[rateb_image_job_key($job)] = $job;
            continue;
        }
        if ($postedJobId !== '' && $job['job_id'] === $postedJobId && $job['campaign_id'] === $campaignId) {
            $matched = $job;
        }
        $expired = !rateb_image_job_active($job['created'], $now);
        $submitStale = $job['phase'] === 'submitting' && ($now - $job['created']) > rateb_image_job_submit_grace();
        if ($expired || $submitStale) {
            if ($job['usage_id'] > 0) {
                $finish[] = $job['usage_id'];
            }
            continue;
        }
        $kept[rateb_image_job_key($job)] = $job;
        if ($job['campaign_id'] !== $campaignId) {
            continue;
        }
        if ($job['phase'] === 'submitting' || $job['job_id'] === '') {
            if ($claim === null || $job['created'] >= $claim['created']) {
                $claim = $job;
            }
            continue;
        }
        $active[] = $job;
    }
    $finish = array_values(array_unique($finish));
    $newest = null;
    foreach ($active as $job) {
        if ($newest === null || $job['created'] >= $newest['created']) {
            $newest = $job;
        }
    }
    $result = [
        'action' => 'begin',
        'job' => null,
        'finish' => $finish,
        'jobs' => $kept,
        'code' => '',
        'usage_id' => 0,
    ];
    if ($postedJobId !== '') {
        if (preg_match('/^[a-f0-9-]{36}$/', $postedJobId) !== 1) {
            $result['action'] = 'reject';
            $result['code'] = 'image_provider';
            return $result;
        }
        if ($matched !== null && isset($kept[rateb_image_job_key($matched)]) && $matched['job_id'] !== '') {
            $result['action'] = 'resume';
            $result['job'] = $kept[rateb_image_job_key($matched)];
            $result['usage_id'] = (int) $result['job']['usage_id'];
            return $result;
        }
        if ($matched !== null) {
            $result['action'] = 'expire';
            $result['code'] = 'image_timeout';
            return $result;
        }
    }
    if ($newest !== null) {
        $result['action'] = 'resume';
        $result['job'] = $newest;
        $result['usage_id'] = (int) $newest['usage_id'];
        return $result;
    }
    if ($claim !== null) {
        $result['action'] = 'wait';
        $result['job'] = $claim;
        $result['usage_id'] = (int) $claim['usage_id'];
        return $result;
    }
    if ($postedJobId !== '') {
        $result['action'] = 'reject';
        $result['code'] = 'image_provider';
        return $result;
    }
    return $result;
}

/** @param array{action?:string} $decision */
function rateb_image_job_allows_new_reservation(array $decision): bool
{
    return (string) ($decision['action'] ?? '') === 'begin';
}

/**
 * Attach a reservation only when decide() still allows a new one.
 *
 * @param array<string,array<string,mixed>> $jobs
 * @return array{action:string,job:?array,finish:array<int,int>,jobs:array<string,array<string,mixed>>,code:string,usage_id:int}
 */
function rateb_image_jobs_claim(array $jobs, int $userId, int $campaignId, string $postedJobId, int $now, int $usageId): array
{
    $decision = rateb_image_jobs_decide($jobs, $userId, $campaignId, $postedJobId, $now);
    if ($decision['action'] !== 'begin' || $usageId < 1) {
        return $decision;
    }
    $decision['usage_id'] = $usageId;
    $decision['jobs'] = rateb_image_jobs_attach(
        $decision['jobs'],
        rateb_image_job_record('', $userId, $campaignId, $usageId, $now, 'submitting')
    );
    return $decision;
}

function rateb_image_jobs_transaction(int $userId, callable $change): mixed
{
    if ($userId < 1) {
        throw new RuntimeException('image_job_store');
    }
    $dir = rateb_image_job_directory();
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('image_job_store');
    }
    $path = $dir . DIRECTORY_SEPARATOR . 'user-' . $userId . '.json';
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('image_job_store');
    }
    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('image_job_store');
        }
        @chmod($path, 0600);
        rewind($handle);
        $raw = stream_get_contents($handle);
        if ($raw === false) {
            throw new RuntimeException('image_job_store');
        }
        $jobs = rateb_image_jobs_decode($raw);
        $packed = $change($jobs);
        if (!is_array($packed) || !isset($packed['jobs']) || !is_array($packed['jobs'])) {
            throw new RuntimeException('image_job_store');
        }
        $encoded = rateb_image_jobs_encode($packed['jobs']);
        rewind($handle);
        if (!ftruncate($handle, 0)) {
            throw new RuntimeException('image_job_store');
        }
        $written = fwrite($handle, $encoded);
        if ($written === false || $written !== strlen($encoded)) {
            throw new RuntimeException('image_job_store');
        }
        fflush($handle);
        return $packed['result'] ?? null;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
