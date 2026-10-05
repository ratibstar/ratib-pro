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
        return true;
    }
    $resolved = $language === 'auto' ? null : ($language === 'bilingual' ? 'bilingual' : $language);
    if ($language === 'auto') {
        $pdo->prepare('UPDATE campaign_briefs SET product = ?, description = ?, audience = ?, objective = ?, campaign_language = ?, resolved_language = NULL WHERE id = ? AND user_id = ?')->execute([
            $product, $description, $audience, $objective, $language, (int) $brief['id'], $userId,
        ]);
        return true;
    }
    $pdo->prepare('UPDATE campaign_briefs SET product = ?, description = ?, audience = ?, objective = ?, campaign_language = ?, resolved_language = ? WHERE id = ? AND user_id = ?')->execute([
        $product, $description, $audience, $objective, $language, $resolved, (int) $brief['id'], $userId,
    ]);
    return true;
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
