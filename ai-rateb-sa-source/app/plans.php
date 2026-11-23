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
        'credits' => $arabic ? 'رصيد النقاط غير كافٍ. رقِّ الباقة أو اشترِ نقاطًا ثم أعد المحاولة.' : 'Not enough credits. Upgrade or buy credits, then try again.',
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
        'image_pending' => $arabic ? 'اعتمد الصورة الظاهرة أو احذفها قبل توليد صورة جديدة.' : 'Approve the image on screen, or delete it, before generating another.',
        'image_locked' => $arabic ? 'اعتمد النص أولًا، ثم ابدأ الصور.' : 'Approve the copy first, then start the images.',
        'image_provider' => $arabic ? 'تعذر توليد الصورة. حاول مرة أخرى.' : 'The image could not be generated. Try again.',
        'image_workers' => $arabic ? 'مزود الصور غير متاح الآن. حاول بعد قليل.' : 'The image provider is not available right now. Try again shortly.',
        'image_irrelevant' => $arabic ? 'الصورة المولّدة لم تطابق موضوع حملتك. أُعيدت النقاط — حاول مرة أخرى أو ارفع صورة من جهازك.' : 'The generated image did not match your campaign. Credits were returned — try again, or upload an image from your device.',
        'voice_locked' => $arabic ? 'اعتمد النص أولًا، ثم ابدأ الصوت.' : 'Approve the copy first, then start the voice.',
        'voice_provider' => $arabic ? 'تعذر توليد الصوت. حاول مرة أخرى.' : 'The voice could not be generated. Try again.',
        'voice_english' => $arabic ? 'توليد الكلام الإنجليزي غير متاح حاليًا. نموذج الكلام الحالي عربي فقط.' : 'English speech is currently unavailable. The current speech model is Arabic only.',
        'voice_terms' => $arabic ? 'نموذج الصوت موجود، لكن شروط استخدامه غير مقبولة لهذا الحساب.' : 'The speech model exists, but its terms are not accepted for this account.',
        'image_timeout' => $arabic ? 'انتهت مهلة توليد الصورة. لم تُحفظ صورة جديدة.' : 'Image generation timed out. No new image was saved.',
        'image_queued' => $arabic ? 'مزود الصور مزدحم حالياً. أُعيدت النقاط — حاول بعد دقيقة أو ارفع صورة من جهازك.' : 'The image provider is busy right now. Credits were returned — try again in a minute, or upload an image from your device.',
        'image_storage' => $arabic ? 'تعذر حفظ الصورة، وأُعيدت النقاط. حاول مرة أخرى.' : 'The image could not be saved and the credits were returned. Try again.',
        'write_busy' => $arabic ? 'RATEB يكتب الآن.' : 'RATEB is already writing.',
        'feature_disabled' => $arabic ? 'هذه الميزة معطّلة من الإدارة.' : 'This feature is disabled by the administrator.',
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
    $stmt = $pdo->prepare("SELECT s.id AS subscription_id, s.plan_id, s.period_end, p.code, p.name_ar, p.name_en, p.price_sar, p.video_limit, p.video_max_seconds, p.image_limit, p.watermark, p.is_active
        FROM subscriptions s
        INNER JOIN plans p ON p.id = s.plan_id
        WHERE s.user_id = ? AND s.status = 'active'
        ORDER BY s.id DESC
        LIMIT 1
        FOR UPDATE");
    $stmt->execute([$userId]);
    $plan = $stmt->fetch();
    if ($plan && rateb_subscription_must_downgrade($plan)) {
        $formerCode = (string) $plan['code'];
        $formerEnd = (string) $plan['period_end'];
        rateb_subscription_apply_free_entitlement($pdo, $userId, (int) $plan['subscription_id'], $formerCode, $formerEnd);
        $stmt->execute([$userId]);
        $plan = $stmt->fetch();
    }
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

/** An active paid row whose period_end is still ahead keeps its own limits. A missing end is not treated as expired. */
function rateb_subscription_current(array $plan, int $now): bool
{
    if ((int) ($plan['is_active'] ?? 0) !== 1) {
        return false;
    }
    $end = strtotime((string) ($plan['period_end'] ?? ''));
    if ($end === false) {
        return true;
    }
    return $end >= $now;
}

function rateb_subscription_must_downgrade(array $plan, ?int $now = null): bool
{
    $code = (string) ($plan['code'] ?? 'free');
    if ($code === 'free' || rateb_video_is_free($plan)) {
        return false;
    }
    return !rateb_subscription_current($plan, $now ?? time());
}

/** Downgrade expired paid entitlement to free limits; credits_balance is untouched. */
function rateb_subscription_apply_free_entitlement(PDO $pdo, int $userId, int $subscriptionId, string $formerCode, string $formerPeriodEnd, bool $notify = true): void
{
    if ($userId < 1 || $subscriptionId < 1) {
        return;
    }
    $freeId = (int) $pdo->query("SELECT id FROM plans WHERE code = 'free' LIMIT 1")->fetchColumn();
    if ($freeId < 1) {
        throw new RuntimeException('Missing free plan');
    }
    $start = date('Y-m-d H:i:s');
    $end = date('Y-m-d H:i:s', strtotime('+1 month'));
    $pdo->prepare('UPDATE subscriptions SET plan_id = ?, status = ?, period_start = ?, period_end = ? WHERE id = ? AND user_id = ?')
        ->execute([$freeId, 'active', $start, $end, $subscriptionId, $userId]);
    $pdo->prepare("UPDATE users SET package_type = 'free', subscription_expires_at = ? WHERE id = ?")
        ->execute([$end, $userId]);
    try {
        $pdo->prepare("INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted, idempotency_key) VALUES (?, NULL, 'subscription_expired', 1, 'completed', 'subscription_expired', 0, ?)")
            ->execute([$userId, 'sub_exp:' . $subscriptionId . ':' . $formerPeriodEnd]);
    } catch (Throwable) {
        // audit row is best-effort
    }
    if ($notify && $formerCode !== '' && $formerCode !== 'free') {
        $notifyFile = __DIR__ . '/notify/events.php';
        if (is_file($notifyFile)) {
            require_once $notifyFile;
            rateb_notify_subscription_expired($pdo, $userId, $formerCode, $formerPeriodEnd);
        }
    }
}

/** @return array{scanned:int,downgraded:int} */
function rateb_subscription_expire_due_batch(PDO $pdo, int $limit = 80): array
{
    $limit = max(1, min(500, $limit));
    $rows = $pdo->query("SELECT s.id AS subscription_id, s.user_id, s.period_end, p.code
        FROM subscriptions s
        INNER JOIN plans p ON p.id = s.plan_id
        WHERE s.status = 'active' AND p.code <> 'free' AND p.price_sar > 0 AND s.period_end < NOW()
        ORDER BY s.period_end ASC
        LIMIT {$limit}")->fetchAll();
    $downgraded = 0;
    foreach ($rows as $row) {
        $userId = (int) $row['user_id'];
        $owned = !$pdo->inTransaction();
        if ($owned) {
            $pdo->beginTransaction();
        }
        try {
            rateb_lock_plan($pdo, $userId);
            if ($owned) {
                $pdo->commit();
            }
            $downgraded++;
        } catch (Throwable) {
            if ($owned && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
    return ['scanned' => count($rows), 'downgraded' => $downgraded];
}

/** @return array<string,mixed> */
function rateb_free_plan_row(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT 0 AS subscription_id, id AS plan_id, NULL AS period_end, code, name_ar, name_en, price_sar, video_limit, video_max_seconds, image_limit, watermark, is_active FROM plans WHERE code = 'free' LIMIT 1");
    $plan = $stmt->fetch();
    if (!$plan) {
        throw new RuntimeException('Missing free plan');
    }
    return $plan;
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

/**
 * How many image/video/voice runs the current point balance can still buy.
 *
 * @return array{balance:int,image_cost:int,video_cost:int,voice_cost:int,images:int,videos:int,voices:int}
 */
function rateb_credits_spend_summary(int $balance): array
{
    $imageCost = rateb_get_feature_cost('ai_image');
    $videoCost = rateb_get_feature_cost('video_storyboard');
    $voiceCost = rateb_get_feature_cost('saudi_voice');
    $balance = max(0, $balance);
    return [
        'balance' => $balance,
        'image_cost' => $imageCost,
        'video_cost' => $videoCost,
        'voice_cost' => $voiceCost,
        'images' => $imageCost > 0 ? intdiv($balance, $imageCost) : 0,
        'videos' => $videoCost > 0 ? intdiv($balance, $videoCost) : 0,
        'voices' => $voiceCost > 0 ? intdiv($balance, $voiceCost) : 0,
    ];
}

function rateb_check_and_deduct_credits(int $userId, string $featureType, ?int $campaignId = null): bool
{
    return rateb_credit_charge(db(), $userId, $featureType, $campaignId) > 0;
}

function rateb_credit_charge(PDO $pdo, int $userId, string $featureType, ?int $campaignId = null): int
{
    $cost = rateb_get_feature_cost($featureType);
    if ($userId < 1 || $cost < 1) {
        return 0;
    }
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare('UPDATE users SET credits_balance = credits_balance - ? WHERE id = ? AND credits_balance >= ?');
        $update->execute([$cost, $userId, $cost]);
        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            return 0;
        }
        $pdo->prepare('INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted) VALUES (?, ?, ?, 1, \'completed\', ?, ?)')
            ->execute([$userId, $campaignId, $featureType, $featureType, $cost]);
        $chargeId = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $chargeId;
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

function rateb_refund_credits(int $userId, string $featureType, ?int $campaignId = null): bool
{
    if ($userId < 1 || rateb_get_feature_cost($featureType) < 1) {
        return false;
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $charges = $pdo->prepare('SELECT id, credits_deducted FROM usage_events WHERE user_id = ? AND operation = ? AND status = \'completed\' AND credits_deducted > 0 AND ((? IS NULL AND campaign_id IS NULL) OR campaign_id = ?) ORDER BY id DESC');
        $charges->execute([$userId, $featureType, $campaignId, $campaignId]);
        $charge = null;
        $chargeId = 0;
        $amount = 0;
        $key = '';
        foreach ($charges as $candidate) {
            $candidateId = (int) $candidate['id'];
            $candidateKey = $featureType . '_charge:' . $candidateId . ':refund';
            $taken = $pdo->prepare('SELECT id FROM usage_events WHERE idempotency_key = ?');
            $taken->execute([$candidateKey]);
            if ($taken->fetchColumn() !== false) {
                continue;
            }
            $charge = $candidate;
            $chargeId = $candidateId;
            $amount = (int) $candidate['credits_deducted'];
            $key = $candidateKey;
            break;
        }
        if (!$charge) {
            $pdo->commit();
            return false;
        }
        try {
            $pdo->prepare('INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted, idempotency_key) VALUES (?, ?, ?, 1, \'completed\', ?, 0, ?)')->execute([
                $userId,
                $campaignId,
                $featureType . '_refund',
                $featureType . '_refund',
                $key,
            ]);
        } catch (Throwable $duplicate) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = $duplicate->getMessage();
            if (str_contains($message, 'UNIQUE') || str_contains($message, 'Duplicate') || str_contains($message, 'constraint')) {
                return false;
            }
            throw $duplicate;
        }
        $pdo->prepare('UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?')->execute([$amount, $userId]);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function rateb_feature_locked(int $userId, string $featureType): bool
{
    if ($userId < 1 || !function_exists('db')) {
        return false;
    }
    try {
        $plan = rateb_user_plan(db(), $userId);
    } catch (Throwable) {
        return false;
    }
    return rateb_plan_feature_locked($plan, $featureType);
}

/**
 * A feature is locked only when this plan turns it off. Free video stays available
 * through the existing monthly cap. Text and voice have no zero-limit column.
 */
function rateb_plan_feature_locked(array $plan, string $featureType): bool
{
    if ((int) ($plan['is_active'] ?? 1) !== 1) {
        return true;
    }
    if ($featureType === 'ai_image' || $featureType === 'image') {
        return (int) ($plan['image_limit'] ?? 0) < 1;
    }
    if ($featureType === 'video_storyboard' || $featureType === 'video') {
        if (rateb_video_is_free($plan)) {
            return false;
        }
        return (int) ($plan['video_limit'] ?? 0) < 1;
    }
    return false;
}

function rateb_checkout_credits_for_plan(string $code): int
{
    return match ($code) {
        'free' => 50,
        'starter' => 500,
        'pro' => 2500,
        default => 0,
    };
}

/** Checkout-enabled paid codes shown on the public site (Moyasar). */
function rateb_checkout_plan_codes(): array
{
    return ['starter', 'pro'];
}

/**
 * @return array{amount:int,currency:string,credits:int,plan_code:string,price_sar:float,name_ar:string,name_en:string}|null
 */
/** @return array{amount:int,currency:string,credits:int,plan_code:string,price_sar:float,name_ar:string,name_en:string}|null */
function rateb_paid_package_catalog_fallback(string $code): ?array
{
    $legacySar = ['starter' => 99.0, 'pro' => 249.0];
    if (!isset($legacySar[$code])) {
        return null;
    }
    $priceSar = $legacySar[$code];
    return [
        'amount' => (int) round($priceSar * 100),
        'currency' => 'SAR',
        'credits' => rateb_checkout_credits_for_plan($code),
        'plan_code' => $code,
        'price_sar' => $priceSar,
        'name_ar' => $code === 'starter' ? 'أساسية' : 'احترافية',
        'name_en' => $code === 'starter' ? 'Starter' : 'Pro',
    ];
}

function rateb_paid_package(string $code, ?PDO $pdo = null): ?array
{
    if (!in_array($code, rateb_checkout_plan_codes(), true)) {
        return null;
    }
    if ($pdo === null && function_exists('db')) {
        try {
            $pdo = db();
        } catch (Throwable) {
            $pdo = null;
        }
    }
    if (!$pdo instanceof PDO) {
        return rateb_paid_package_catalog_fallback($code);
    }
    $stmt = $pdo->prepare('SELECT code, price_sar, is_active, name_ar, name_en FROM plans WHERE code = ? LIMIT 1');
    $stmt->execute([$code]);
    $row = $stmt->fetch();
    if (!$row || (int) ($row['is_active'] ?? 0) !== 1) {
        return null;
    }
    $priceSar = (float) ($row['price_sar'] ?? 0);
    if ($priceSar <= 0) {
        return null;
    }
    $amount = max(100, (int) round($priceSar * 100));
    return [
        'amount' => $amount,
        'currency' => 'SAR',
        'credits' => rateb_checkout_credits_for_plan($code),
        'plan_code' => $code,
        'price_sar' => $priceSar,
        'name_ar' => (string) ($row['name_ar'] ?? $code),
        'name_en' => (string) ($row['name_en'] ?? $code),
    ];
}

/**
 * Pricing cards on the homepage, dashboard, and credits page.
 *
 * @return list<array{code:string,name_ar:string,name_en:string,price_sar:float,credits:int,is_active:bool}>
 */
function rateb_public_pricing_plans(PDO $pdo): array
{
    $codes = ['free', 'starter', 'pro'];
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $stmt = $pdo->prepare("SELECT code, price_sar, is_active, name_ar, name_en FROM plans WHERE code IN ($placeholders)");
    $stmt->execute($codes);
    $byCode = [];
    foreach ($stmt->fetchAll() as $row) {
        $byCode[(string) $row['code']] = $row;
    }
    $out = [];
    foreach ($codes as $code) {
        if (!isset($byCode[$code])) {
            continue;
        }
        $row = $byCode[$code];
        $out[] = [
            'code' => $code,
            'name_ar' => (string) ($row['name_ar'] ?? $code),
            'name_en' => (string) ($row['name_en'] ?? $code),
            'price_sar' => (float) ($row['price_sar'] ?? 0),
            'credits' => rateb_checkout_credits_for_plan($code),
            'is_active' => (int) ($row['is_active'] ?? 0) === 1,
        ];
    }
    return $out;
}

function rateb_format_sar_price(float $sar): string
{
    if (abs($sar - round($sar)) < 0.001) {
        return number_format($sar, 0, '.', '');
    }
    return number_format($sar, 2, '.', '');
}

function rateb_moyasar_configured(array $config): bool
{
    return trim((string) ($config['moyasar_secret_key'] ?? '')) !== '';
}

function rateb_credit_halala(int $credits, ?PDO $pdo = null): int
{
    if ($credits < 1) {
        return 0;
    }
    $starter = rateb_paid_package('starter', $pdo);
    $per500 = $starter !== null ? $starter['amount'] : 9900;
    return max(100, (int) round($credits * $per500 / 500));
}

/** @return array{credits:int,amount:int} */
function rateb_campaign_credit_bill(PDO $pdo, int $userId, int $campaignId): array
{
    if ($userId < 1 || $campaignId < 1) {
        return ['credits' => 0, 'amount' => 0];
    }
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(credits_deducted), 0) FROM usage_events WHERE user_id = ? AND campaign_id = ? AND status = 'completed' AND operation IN ('text_campaign', 'ai_image', 'saudi_voice', 'video_storyboard')");
    $stmt->execute([$userId, $campaignId]);
    $credits = (int) $stmt->fetchColumn();
    return ['credits' => $credits, 'amount' => rateb_credit_halala($credits, $pdo)];
}

/** @param array<string,mixed> $payment Moyasar payment object, already fetched from Moyasar */
function rateb_payment_accept(array $payment): string
{
    $pdo = function_exists('db') ? db() : null;
    if ((string) ($payment['status'] ?? '') !== 'paid') {
        return 'unpaid';
    }
    if (strtoupper((string) ($payment['currency'] ?? '')) !== 'SAR') {
        return 'currency';
    }
    $meta = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
    $userId = (int) ($meta['user_id'] ?? 0);
    if ((string) ($meta['package'] ?? '') === 'usage') {
        $credits = (int) ($meta['credits'] ?? 0);
        if ($userId < 1 || $credits < 1 || (int) ($payment['amount'] ?? 0) !== rateb_credit_halala($credits, $pdo instanceof PDO ? $pdo : null)) {
            return 'amount';
        }
        if ((int) ($meta['order_id'] ?? 0) < 1) {
            return 'order';
        }
        return '';
    }
    $package = rateb_paid_package((string) ($meta['package'] ?? ''), $pdo);
    if ($userId < 1 || $package === null) {
        return 'package';
    }
    if ((int) ($payment['amount'] ?? 0) !== $package['amount']) {
        return 'amount';
    }
    if ((int) ($meta['order_id'] ?? 0) < 1) {
        return 'order';
    }
    return '';
}

function rateb_moyasar_webhook_authorized(string $configured, string $provided): bool
{
    if ($configured === '' || $provided === '') {
        return false;
    }
    return hash_equals($configured, $provided);
}

/** @param array<string,mixed> $payment @return string ok | unpaid | currency | package | amount | owner | duplicate */
function rateb_payment_record(PDO $pdo, array $payment): string
{
    $decision = rateb_payment_accept($payment);
    if ($decision !== '') {
        return $decision;
    }
    $meta = is_array($payment['metadata'] ?? null) ? $payment['metadata'] : [];
    $userId = (int) $meta['user_id'];
    $code = (string) $meta['package'];
    $package = $code === 'usage'
        ? ['amount' => rateb_credit_halala((int) ($meta['credits'] ?? 0), $pdo), 'credits' => (int) ($meta['credits'] ?? 0), 'plan_code' => '']
        : rateb_paid_package($code, $pdo);
    $paymentId = (string) ($payment['id'] ?? '');
    if ($package === null || preg_match('/^[a-f0-9-]{36}$/', $paymentId) !== 1) {
        return 'package';
    }
    $pdo->beginTransaction();
    try {
        $user = $pdo->prepare('SELECT id FROM users WHERE id = ?');
        $user->execute([$userId]);
        if ($user->fetchColumn() === false) {
            $pdo->rollBack();
            return 'owner';
        }
        $orderId = (int) $meta['order_id'];
        $order = $pdo->prepare('SELECT id, user_id, package_code, amount, credits, currency, status FROM payment_orders WHERE id = ? FOR UPDATE');
        $order->execute([$orderId]);
        $row = $order->fetch();
        if (!$row || (int) $row['user_id'] !== $userId) {
            $pdo->rollBack();
            return 'order';
        }
        if ((string) $row['status'] === 'paid') {
            $pdo->commit();
            return 'duplicate';
        }
        if ((string) $row['status'] !== 'pending' || (string) $row['package_code'] !== $code || (int) $row['amount'] !== $package['amount'] || (int) $row['credits'] !== $package['credits'] || strtoupper((string) $row['currency']) !== 'SAR') {
            $pdo->rollBack();
            return 'amount';
        }
        $known = $pdo->prepare('SELECT id FROM payment_receipts WHERE moyasar_id = ?');
        $known->execute([$paymentId]);
        if ($known->fetchColumn() !== false) {
            $pdo->commit();
            return 'duplicate';
        }
        try {
            $pdo->prepare('INSERT INTO payment_receipts (moyasar_id, user_id, package_type, amount) VALUES (?, ?, ?, ?)')
                ->execute([$paymentId, $userId, $code === 'usage' ? 'free' : $code, $package['amount']]);
        } catch (Throwable $duplicate) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = $duplicate->getMessage();
            if (str_contains($message, 'UNIQUE') || str_contains($message, 'Duplicate') || str_contains($message, 'constraint')) {
                return 'duplicate';
            }
            throw $duplicate;
        }
        if ($code === 'usage') {
            $pdo->prepare('UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?')->execute([$package['credits'], $userId]);
        } else {
            $planId = $pdo->prepare('SELECT id FROM plans WHERE code = ? AND is_active = 1');
            $planId->execute([$package['plan_code']]);
            $selectedPlan = (int) ($planId->fetchColumn() ?: 0);
            $pdo->prepare('UPDATE users SET package_type = ?, credits_balance = credits_balance + ? WHERE id = ?')
                ->execute([$code, $package['credits'], $userId]);
            if ($selectedPlan > 0) {
                rateb_subscription_apply_paid_plan($pdo, $userId, $selectedPlan);
            }
        }
        $pdo->prepare('INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted, idempotency_key) VALUES (?, NULL, ?, 1, \'completed\', ?, 0, ?)')
            ->execute([$userId, 'payment_' . $code, 'payment_' . $code, 'payment:' . $paymentId]);
        $pdo->prepare("UPDATE payment_orders SET status = 'paid' WHERE id = ? AND status = 'pending'")->execute([$orderId]);
        $pdo->commit();
        return 'ok';
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

/** Extend subscription by one month from current period_end when still active; otherwise start a fresh period. */
function rateb_subscription_apply_paid_plan(PDO $pdo, int $userId, int $planId): void
{
    if ($userId < 1 || $planId < 1) {
        return;
    }
    $sub = $pdo->prepare("SELECT id, period_start, period_end FROM subscriptions WHERE user_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
    $sub->execute([$userId]);
    $row = $sub->fetch();
    $now = time();
    if ($row) {
        $endTs = strtotime((string) $row['period_end']);
        if ($endTs !== false && $endTs > $now) {
            $newEnd = date('Y-m-d H:i:s', strtotime('+1 month', $endTs));
            $pdo->prepare('UPDATE subscriptions SET plan_id = ?, status = ?, period_end = ? WHERE id = ?')
                ->execute([$planId, 'active', $newEnd, (int) $row['id']]);
            $pdo->prepare('UPDATE users SET subscription_expires_at = ? WHERE id = ?')->execute([$newEnd, $userId]);
            return;
        }
    }
    $start = date('Y-m-d H:i:s', $now);
    $end = date('Y-m-d H:i:s', strtotime('+1 month', $now));
    if ($row) {
        $pdo->prepare('UPDATE subscriptions SET plan_id = ?, status = ?, period_start = ?, period_end = ? WHERE id = ?')
            ->execute([$planId, 'active', $start, $end, (int) $row['id']]);
        $pdo->prepare('UPDATE users SET subscription_expires_at = ? WHERE id = ?')->execute([$end, $userId]);
        return;
    }
    $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, status, period_start, period_end) VALUES (?, ?, 'active', ?, ?)")
        ->execute([$userId, $planId, $start, $end]);
    $pdo->prepare('UPDATE users SET subscription_expires_at = ? WHERE id = ?')->execute([$end, $userId]);
}

function rateb_payment_mark_failed(PDO $pdo, int $orderId, int $userId): void
{
    if ($orderId < 1 || $userId < 1) {
        return;
    }
    $pdo->prepare("UPDATE payment_orders SET status = 'failed' WHERE id = ? AND user_id = ? AND status = 'pending'")->execute([$orderId, $userId]);
}

function rateb_credit_stop(int $userId = 0, string $featureType = ''): void
{
    http_response_code(402);
    $arabic = !function_exists('ui_language') || ui_language() === 'ar';
    if ($userId > 0 && rateb_feature_locked($userId, $featureType)) {
        echo json_encode(['error' => $arabic ? 'هذه الميزة غير متاحة في الباقة المجانية، اضغط هنا لترقية الباقة' : 'This feature is not in the free package. Click here to upgrade.', 'code' => 'package'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['error' => $arabic ? 'رصيد النقاط غير كافٍ، اضغط هنا لترقية الباقة' : 'Not enough credits. Click here to upgrade.', 'code' => 'credits'], JSON_UNESCAPED_UNICODE);
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

/** Map a usage reservation operation to the credit feature that pays for it. */
function rateb_usage_credit_feature(string $operation): string
{
    return match ($operation) {
        'image' => 'ai_image',
        'voice' => 'saudi_voice',
        'video' => 'video_storyboard',
        default => '',
    };
}

/**
 * Open a usage reservation. Image/voice/video are gated by credits (not a separate
 * monthly unit cap), so the wallet balance the customer sees matches what they can spend.
 */
function rateb_usage_begin(PDO $pdo, int $userId, ?int $campaignId, string $operation, ?int $seconds = null): array
{
    $allowed = ['image', 'video', 'campaign_text', 'voice', 'variation'];
    if (!in_array($operation, $allowed, true)) {
        return ['ok' => false, 'code' => 'plan_inactive', 'id' => 0];
    }
    $feature = rateb_usage_credit_feature($operation);
    $cost = $feature !== '' ? rateb_get_feature_cost($feature) : 0;
    $watermark = false;
    $id = 0;
    $pdo->beginTransaction();
    try {
        $plan = rateb_lock_plan($pdo, $userId);
        if ((int) $plan['is_active'] !== 1 && (string) ($plan['code'] ?? '') === 'free') {
            $pdo->prepare("UPDATE plans SET is_active = 1 WHERE id = ?")->execute([(int) $plan['plan_id']]);
            $plan['is_active'] = 1;
        }
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
        if ($cost > 0) {
            $balance = $pdo->prepare('SELECT credits_balance FROM users WHERE id = ? FOR UPDATE');
            $balance->execute([$userId]);
            if ((int) $balance->fetchColumn() < $cost) {
                $pdo->rollBack();
                return ['ok' => false, 'code' => 'credits', 'id' => 0];
            }
        }
        $insert = $pdo->prepare('INSERT INTO usage_events (user_id, campaign_id, operation, units, seconds, status) VALUES (?, ?, ?, 1, ?, \'reserved\')');
        $insert->execute([$userId, $campaignId, $operation, $seconds]);
        $id = (int) $pdo->lastInsertId();
        $watermark = (int) $plan['watermark'] === 1;
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
    if ($feature !== '' && $cost > 0 && $id > 0) {
        $charged = rateb_reservation_charge($pdo, $id, $userId, $feature, $campaignId);
        if ($charged !== 'ok') {
            rateb_usage_finish($pdo, $id, 'failed');
            return ['ok' => false, 'code' => $charged === 'credits' ? 'credits' : 'plan_inactive', 'id' => 0];
        }
    }
    return ['ok' => true, 'code' => '', 'id' => $id, 'watermark' => $watermark];
}

function rateb_usage_finish(PDO $pdo, int $eventId, string $status): void
{
    if ($eventId < 1 || !in_array($status, ['completed', 'failed'], true)) {
        return;
    }
    $pdo->prepare('UPDATE usage_events SET status = ? WHERE id = ? AND status = \'reserved\'')->execute([$status, $eventId]);
}

/**
 * Charge a feature against one open usage reservation. The charge is recorded on the
 * reservation row itself, so a reservation can be charged once and refunded once.
 *
 * @return string ok | credits | reservation
 */
function rateb_reservation_charge(PDO $pdo, int $usageId, int $userId, string $featureType, ?int $campaignId): string
{
    $cost = rateb_get_feature_cost($featureType);
    if ($usageId < 1 || $userId < 1 || $cost < 1) {
        return 'reservation';
    }
    $pdo->beginTransaction();
    try {
        $mark = $pdo->prepare("UPDATE usage_events SET credits_deducted = ? WHERE id = ? AND user_id = ? AND status = 'reserved' AND credits_deducted = 0");
        $mark->execute([$cost, $usageId, $userId]);
        if ($mark->rowCount() !== 1) {
            $pdo->rollBack();
            return 'reservation';
        }
        $debit = $pdo->prepare('UPDATE users SET credits_balance = credits_balance - ? WHERE id = ? AND credits_balance >= ?');
        $debit->execute([$cost, $userId, $cost]);
        if ($debit->rowCount() !== 1) {
            $pdo->rollBack();
            return 'credits';
        }
        $pdo->prepare("INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted) VALUES (?, ?, ?, 1, 'completed', ?, ?)")
            ->execute([$userId, $campaignId, $featureType, $featureType, $cost]);
        $pdo->commit();
        return 'ok';
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

/**
 * Close a usage reservation. Only the call that moves it out of 'reserved' may refund,
 * and it refunds exactly the credits recorded on that reservation.
 *
 * @return bool true when this call refunded credits
 */
function rateb_reservation_settle(PDO $pdo, int $usageId, int $userId, string $status, string $featureType): bool
{
    if ($usageId < 1 || $userId < 1 || !in_array($status, ['completed', 'failed'], true)) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        $close = $pdo->prepare("UPDATE usage_events SET status = ? WHERE id = ? AND user_id = ? AND status = 'reserved'");
        $close->execute([$status, $usageId, $userId]);
        if ($close->rowCount() !== 1 || $status !== 'failed') {
            $pdo->commit();
            return false;
        }
        $row = $pdo->prepare('SELECT credits_deducted, campaign_id FROM usage_events WHERE id = ? AND user_id = ?');
        $row->execute([$usageId, $userId]);
        $charge = $row->fetch() ?: [];
        $amount = (int) ($charge['credits_deducted'] ?? 0);
        if ($amount < 1) {
            $pdo->commit();
            return false;
        }
        $campaignId = isset($charge['campaign_id']) && $charge['campaign_id'] !== null ? (int) $charge['campaign_id'] : null;
        $pdo->prepare('UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?')->execute([$amount, $userId]);
        $pdo->prepare("INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted) VALUES (?, ?, ?, 1, 'completed', ?, 0)")
            ->execute([$userId, $campaignId, $featureType . '_refund', $featureType . '_refund']);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

/**
 * Refund charged reservations that can no longer finish, for example when the request
 * that held them died before it could record success or failure.
 *
 * @return int number of reservations refunded
 */
function rateb_reservation_sweep(PDO $pdo, int $userId, string $operation, string $featureType, int $maxAgeSeconds): int
{
    if ($userId < 1 || $maxAgeSeconds < 1) {
        return 0;
    }
    $stale = $pdo->prepare("SELECT id FROM usage_events WHERE user_id = ? AND operation = ? AND status = 'reserved' AND credits_deducted > 0 AND created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");
    $stale->execute([$userId, $operation, $maxAgeSeconds]);
    $refunded = 0;
    foreach ($stale->fetchAll() as $row) {
        if (rateb_reservation_settle($pdo, (int) $row['id'], $userId, 'failed', $featureType)) {
            $refunded++;
        }
    }
    return $refunded;
}

function rateb_image_reservation_max_age(): int
{
    return rateb_image_job_ttl() + 120;
}

function rateb_voice_reservation_max_age(): int
{
    return 600;
}

function rateb_campaign_requirements_met(bool $idea, bool $strategy, bool $copy, bool $image, bool $audio, bool $video): bool
{
    return $idea && $strategy && $copy && $image && $audio && $video;
}

/**
 * @return array{status:string,invalidate_ready:bool}
 */
function rateb_edited_output_state(string $oldContent, string $newContent, string $oldStatus): array
{
    $old = json_decode($oldContent, true);
    $new = json_decode($newContent, true);
    $same = is_array($old) && is_array($new) ? $old == $new : $oldContent === $newContent;
    if ($same) {
        return ['status' => $oldStatus, 'invalidate_ready' => false];
    }
    return ['status' => 'draft', 'invalidate_ready' => $oldStatus === 'approved'];
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
    $brand = $pdo->prepare('SELECT brand_name, tone, contact, audience, offer_line, forbidden, dialect, primary_color, secondary_color FROM brand_kits WHERE user_id = ?');
    $brand->execute([(int) ($campaign['user_id'] ?? 0)]);
    $kit = $brand->fetch();
    if ($kit) {
        foreach ([
            'brand_name' => 'Brand name',
            'tone' => 'Brand tone',
            'audience' => 'Brand audience',
            'offer_line' => 'Current offer',
            'forbidden' => 'Never mention',
            'dialect' => 'Dialect',
            'contact' => 'Contact',
        ] as $field => $label) {
            $value = trim((string) ($kit[$field] ?? ''));
            if ($value !== '') {
                $lines[] = $label . ': ' . $value;
            }
        }
    }
    return $lines === [] ? 'No facts were provided.' : implode("\n", $lines);
}

function rateb_image_fact(mixed $value): string
{
    $text = trim((string) preg_replace('/\s+/u', ' ', rateb_prompt_plain($value, 'ar')));
    $bare = (string) preg_replace('/^[\s.،,:;!؟?_\-]+|[\s.،,:;!؟?_\-]+$/u', '', (string) preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $text));
    if ($bare === '' || preg_match('/^(لم\s*يحدد|غير\s*محدد|لا\s*يوجد|لا\s*شيء|الكل|الجميع|كل\s*الناس|n\/?a|none|not\s*specified|unspecified|all|everyone|-+)$/iu', $bare) === 1) {
        return '';
    }
    return mb_substr($text, 0, 400);
}

/**
 * Real campaign facts for image prompting. Campaign/campaign_briefs are authoritative.
 * Generated outputs may supply approved copy, but never new product facts; drafts are ignored.
 *
 * @param array<int,array<string,mixed>> $outputs campaign_outputs rows, newest first
 * @param array<string,mixed> $brand
 * @return array<string,string>
 */
function rateb_image_brief(array $campaign, array $brief, array $outputs, array $brand = []): array
{
    $copy = [];
    $platforms = [];
    foreach ($outputs as $row) {
        $type = (string) ($row['output_type'] ?? '');
        $status = (string) ($row['approval_status'] ?? '');
        if ($status !== 'approved') {
            continue;
        }
        if ($type === 'strategy') {
            continue;
        }
        if ($type === 'social_posts') {
            $posts = json_decode((string) ($row['content'] ?? ''), true);
            foreach (is_array($posts) ? array_keys($posts) : [] as $platform) {
                if (is_string($platform) && preg_match('/^[a-z_]{1,20}$/', $platform) === 1) {
                    $platforms[$platform] = true;
                }
            }
            continue;
        }
        if (!in_array($type, ['headline', 'short_ad', 'ad_copy', 'product_description'], true)) {
            continue;
        }
        if (isset($copy[$type])) {
            continue;
        }
        $text = rateb_image_fact($row['content'] ?? '');
        if ($text !== '') {
            $copy[$type] = $text;
        }
    }
    $facts = [
        'product' => rateb_image_fact($brief['product'] ?? '') ?: rateb_image_fact($campaign['product_name'] ?? ''),
        'title' => rateb_image_fact($campaign['title'] ?? ''),
        'description' => rateb_image_fact($brief['description'] ?? '') ?: rateb_image_fact($campaign['description'] ?? ''),
        'price' => rateb_image_fact($campaign['price'] ?? ''),
        'objective' => rateb_image_fact($brief['objective'] ?? '') ?: rateb_image_fact($campaign['objective'] ?? ''),
        'audience' => rateb_image_fact($brief['audience'] ?? '') ?: rateb_image_fact($campaign['target_customer'] ?? ''),
        'location' => rateb_image_fact($brief['location'] ?? ''),
        'core_message' => rateb_image_fact($brief['core_message'] ?? ''),
        'creative_direction' => rateb_image_fact($brief['creative_direction'] ?? ''),
        'tone' => rateb_image_fact($brief['brand_tone'] ?? '') ?: rateb_image_fact($brand['tone'] ?? ''),
        'channels' => rateb_image_fact($brief['channels'] ?? ''),
        'platforms' => implode(', ', array_keys($platforms)),
        'headline' => $copy['headline'] ?? '',
        'short_ad' => $copy['short_ad'] ?? '',
        'product_description' => $copy['product_description'] ?? '',
        'ad_copy' => $copy['ad_copy'] ?? '',
        'brand_name' => rateb_image_fact($brand['brand_name'] ?? ''),
        'offer' => rateb_image_fact($brand['offer_line'] ?? ''),
        'forbidden' => rateb_image_fact($brand['forbidden'] ?? ''),
    ];
    $seen = [];
    foreach ($facts as $key => $value) {
        $norm = mb_strtolower($value);
        if ($value === '' || isset($seen[$norm])) {
            unset($facts[$key]);
            continue;
        }
        $seen[$norm] = true;
    }
    return $facts;
}

/** @param array<string,string> $facts */
function rateb_image_brief_sufficient(array $facts): bool
{
    $product = trim((string) ($facts['product'] ?? $facts['title'] ?? ''));
    if (mb_strlen((string) preg_replace('/[^\p{L}]+/u', '', $product)) < 4) {
        return false;
    }
    $authoritative = rateb_image_subject_facts($facts);
    $authoritative = mb_strtolower($authoritative);
    $concrete = '/قهو|تمر|تمور|عطر|بخور|عود|حلويات|كيك|شوكولات|مطعم|وجب|برجر|بيتزا|شاورما|عباي|ملابس|أزياء|فستان|ثوب|سيار|عقار|شقة|فيلا|أسنان|عياد|تجميل|صالون|سبا|دور(?:ة|ات)|تدريب|تعليم|نادي|رياض|لياق|coffee|dates|perfume|restaurant|burger|pizza|fashion|clothing|\\bcars?\\b|real estate|apartment|villa|dental|clinic|salon|course|training|education|gym|fitness/u';
    if (preg_match($concrete, $authoritative) === 1) {
        return true;
    }
    $digital = preg_match('/منص|تطبيق|برنامج|برمج|موقع|نظام|ذكاء|اصطناع|رقمي|platform|software|\\bapp\\b|saas|\\bai\\b/u', $authoritative) === 1;
    if ($digital) {
        return preg_match('/ينشئ|ينتج|يولد|يصمم|يدير|يحلل|يحجز|يبيع|يربط|يرسل|يترجم|يكتب|إنشاء|انشاء|توليد|تصميم|إدارة|ادارة|تحليل|حجز|متجر|محاسب|رواتب|موارد بشرية|مخزون|فواتير|إعلانات|اعلانات|فيديوهات|صور|محتوى|creates?|generates?|designs?|manages?|analy[sz]es?|books?|sells?|connects?|accounting|payroll|inventory|invoices?|human resources|advertis|videos?|images?|content/u', $authoritative) === 1;
    }
    $generic = '/^(خدمة|خدمات|منتج|منتجات|شركة|مؤسسة|متجر|مشروع|business|company|service|services|product|products|store|project)(\s+(جديد|جديدة|متنوع|متنوعة|new|various))?$/u';
    return preg_match($generic, mb_strtolower($product)) !== 1;
}

/** Authoritative subject facts with "make me an ad" request language removed. */
function rateb_image_subject_facts(array $facts): string
{
    $text = implode(' ', array_intersect_key($facts, array_flip([
        'product', 'title', 'description', 'objective', 'audience', 'location',
        'core_message', 'creative_direction', 'channels', 'offer',
    ])));
    $text = (string) preg_replace('/(?:^|\s)و?(?:أبغى|ابغى|أبي|ابي|أريد|اريد|نريد|احتاج|أحتاج)\s*(?:لها|له|عنها|عنه)?\s*(?:إعلان|اعلان|حملة)(?=\s|$)/u', ' ', $text);
    $text = (string) preg_replace('/\b(?:make|create|need|want)\s+(?:me\s+)?(?:an?\s+)?(?:ad|advertisement|campaign)\s+(?:for|about)\b/i', ' ', $text);
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

/** @param array<string,string> $facts */
function rateb_image_brief_text(array $facts): string
{
    $labels = [
        'product' => 'Product or service',
        'title' => 'Campaign name',
        'description' => 'Campaign description',
        'price' => 'Price',
        'objective' => 'Marketing goal',
        'audience' => 'Target audience',
        'location' => 'Location',
        'core_message' => 'Core message',
        'creative_direction' => 'Creative direction',
        'tone' => 'Tone',
        'channels' => 'Channels',
        'platforms' => 'Ad platforms',
        'headline' => 'Approved headline (style only, not product facts)',
        'short_ad' => 'Approved short ad (style only, not product facts)',
        'product_description' => 'Approved product copy (style only, not product facts)',
        'ad_copy' => 'Approved ad copy (style only, not product facts)',
        'brand_name' => 'Brand name',
        'offer' => 'Current offer',
        'forbidden' => 'Never show',
    ];
    $lines = [];
    foreach ($labels as $key => $label) {
        if (($facts[$key] ?? '') !== '') {
            $lines[] = $label . ': ' . $facts[$key];
        }
    }
    return implode("\n", $lines);
}

/** Latin brand words (e.g. RATEB) that must not be rendered as letters in the image. */
function rateb_image_brand_words(array $facts): array
{
    $words = [];
    foreach (['brand_name', 'product', 'title'] as $key) {
        preg_match_all('/\b[A-Z][A-Z0-9]{2,}\b/', (string) ($facts[$key] ?? ''), $match);
        foreach ($match[0] as $word) {
            $words[strtolower($word)] = true;
        }
    }
    if (preg_match_all('/[A-Za-z][A-Za-z0-9]{2,}/', (string) ($facts['brand_name'] ?? ''), $match) > 0) {
        foreach ($match[0] as $word) {
            $words[strtolower($word)] = true;
        }
    }
    return array_keys($words);
}

function rateb_image_phrase(mixed $value, int $maxWords, array $drop = []): string
{
    if (!is_string($value) || preg_match('/\p{Arabic}/u', $value) === 1) {
        return '';
    }
    $text = (string) preg_replace('/[^A-Za-z0-9 \-\']+/', ' ', $value);
    $text = (string) preg_replace('/\b(with|showing|reading|saying|featuring)?\s*(the\s+)?(text|texts|words?|letters?|logos?|captions?|slogans?|headlines?|typography|watermarks?|signage|labels?|brand\s+names?)\b/i', ' ', $text);
    foreach ($drop as $word) {
        $text = (string) preg_replace('/\b' . preg_quote($word, '/') . '\b/i', ' ', $text);
    }
    $words = array_values(array_filter(preg_split('/\s+/', trim($text)) ?: [], static fn(string $w): bool => $w !== ''));
    $joiners = ['a', 'an', 'and', 'or', 'of', 'the', 'with', 'no', 'not', 'any', 'for', 'in', 'on'];
    while ($words !== [] && in_array(strtolower($words[0]), $joiners, true)) {
        array_shift($words);
    }
    while ($words !== [] && in_array(strtolower($words[count($words) - 1]), $joiners, true)) {
        array_pop($words);
    }
    return trim(implode(' ', array_slice($words, 0, $maxWords)));
}

/**
 * Validates the art-direction JSON returned by the language model.
 *
 * @return array<string,mixed>|null null when unusable, ['sufficient' => false] when the brief does not say what is sold
 */
function rateb_image_plan_parse(string $content, array $brandWords = [], array $facts = []): ?array
{
    if (preg_match('/\{.*\}/s', $content, $match) !== 1) {
        return null;
    }
    $data = json_decode($match[0], true);
    if (!is_array($data)) {
        return null;
    }
    if (($data['sufficient'] ?? true) === false) {
        return ['sufficient' => false];
    }
    $evidence = trim((string) ($data['evidence'] ?? ''));
    $evidence = trim($evidence, " \t\n\r\0\x0B\"'“”‘’");
    // Exact quote may come from any campaign fact already shown to the model, including
    // approved copy that restates the same capability. Subject invention is still blocked
    // by rateb_image_plan_compatible() and the art-director instructions.
    $factText = implode("\n", array_values(array_filter(array_map('strval', $facts), static fn(string $v): bool => $v !== '')));
    if ($facts !== [] && (mb_strlen($evidence) < 4 || !str_contains($factText, $evidence))) {
        return null;
    }
    $subject = rateb_image_phrase($data['subject'] ?? '', 22, $brandWords);
    if (mb_strlen($subject) < 8) {
        return null;
    }
    $list = static function (mixed $items, int $count, int $words) use ($brandWords): array {
        $out = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $phrase = rateb_image_phrase($item, $words, $brandWords);
            if ($phrase !== '' && !in_array(strtolower($phrase), array_map('strtolower', $out), true)) {
                $out[] = $phrase;
            }
            if (count($out) >= $count) {
                break;
            }
        }
        return $out;
    };
    $category = (string) ($data['category'] ?? '');
    $plan = [
        'sufficient' => true,
        'category' => in_array($category, ['physical_product', 'food', 'digital_service', 'local_service', 'place', 'other'], true) ? $category : 'other',
        'subject' => $subject,
        'elements' => $list($data['visual_elements'] ?? [], 5, 7),
        'setting' => rateb_image_phrase($data['setting'] ?? '', 12, $brandWords),
        'mood' => rateb_image_phrase($data['mood'] ?? '', 8, $brandWords),
        'avoid' => $list($data['avoid'] ?? [], 8, 4),
        'evidence' => $evidence,
    ];
    return $facts === [] || rateb_image_plan_compatible($plan, $facts) ? $plan : null;
}

/** The visible subject must preserve every concrete topic explicitly stated by the user. */
function rateb_image_plan_compatible(array $plan, array $facts): bool
{
    $source = mb_strtolower(rateb_image_subject_facts($facts));
    $visual = strtolower(implode(' ', [
        (string) ($plan['subject'] ?? ''),
        implode(' ', (array) ($plan['elements'] ?? [])),
        (string) ($plan['setting'] ?? ''),
    ]));
    $topics = [
        ['/إعلان|اعلان|تسويق|حملات|محتوى|فيديو|تصميم|advert|marketing|campaign|content|video|design/u', '/ad|advert|marketing|campaign|content|video|design/'],
        ['/قهو|كوفي|coffee|espresso/u', '/coffee|espresso|cup|beans/'],
        ['/تمر|تمور|dates/u', '/date|dates|fruit|gift box/'],
        ['/عطر|عطور|بخور|عود|perfume|oud/u', '/perfume|fragrance|oud|bottle/'],
        ['/مطعم|وجب|برجر|بيتزا|شاورما|طعام|restaurant|burger|pizza|meal/u', '/food|meal|dish|restaurant|burger|pizza|shawarma/'],
        ['/ملابس|أزياء|عباي|فستان|ثوب|fashion|clothing|dress/u', '/fashion|clothing|garment|dress|abaya|fabric/'],
        ['/سيار|\\bcars?\\b/u', '/car|vehicle|automobile/'],
        ['/عقار|شقة|فيلا|real estate|apartment|villa/u', '/building|home|house|apartment|villa|property|interior/'],
        ['/أسنان|اسنان|dental|dentist/u', '/dental|dentist|teeth|clinic/'],
        ['/تدريب|تعليم|دورة|course|training|education/u', '/course|training|lesson|education|learning/'],
        ['/رياض|لياق|نادي|gym|fitness/u', '/gym|fitness|training|exercise|dumbbell/'],
    ];
    $matched = false;
    foreach ($topics as [$sourcePattern, $visualPattern]) {
        if (preg_match($sourcePattern, $source) !== 1) {
            continue;
        }
        $matched = true;
        if (preg_match($visualPattern, $visual) !== 1) {
            return false;
        }
    }
    return $matched || trim((string) ($plan['evidence'] ?? '')) !== '';
}

/**
 * When the art director reply is unusable but the brief already proves a marketing
 * platform, recover with the grounded photoreal desk scene instead of blocking the customer.
 *
 * @param array<string,string> $facts
 * @return array<string,mixed>|null
 */
function rateb_image_plan_recover_marketing(array $facts): ?array
{
    if (!rateb_image_brief_sufficient($facts)) {
        return null;
    }
    $source = mb_strtolower(rateb_image_subject_facts($facts));
    $isMarketing = preg_match('/تسويق|حملات|إعلان|اعلان|marketing|campaign|advert/u', $source) === 1;
    $hasCreativeOutputs = preg_match('/محتوى|صور|تصميم|content|images?|visuals?|design/u', $source) === 1;
    $digital = preg_match('/منص|تطبيق|برنامج|برمج|موقع|نظام|ذكاء|اصطناع|رقمي|platform|software|\\bapp\\b|saas|\\bai\\b/u', $source) === 1;
    if (!$digital || !$isMarketing || !$hasCreativeOutputs) {
        return null;
    }
    $evidence = (string) ($facts['description'] ?? $facts['product'] ?? $facts['title'] ?? '');
    if (mb_strlen($evidence) < 4) {
        return null;
    }
    return rateb_image_plan_ground_marketing_service([
        'sufficient' => true,
        'category' => 'digital_service',
        'subject' => 'marketing studio desk',
        'elements' => ['blank planning cards'],
        'setting' => 'studio desk',
        'mood' => 'professional',
        'avoid' => [],
        'evidence' => mb_substr($evidence, 0, 120),
    ], $facts);
}

/**
 * Stable Diffusion invents abstract neon spheres when asked for "AI platforms".
 * When the brief is a marketing/campaign service, force a photoreal commercial
 * desk scene that shows social ad mockups, storyboard cards, and advertising flyers.
 *
 * @param array<string,mixed> $plan
 * @param array<string,string> $facts
 * @return array<string,mixed>
 */
function rateb_image_plan_ground_marketing_service(array $plan, array $facts): array
{
    if (($plan['category'] ?? '') !== 'digital_service') {
        return $plan;
    }
    $source = mb_strtolower(rateb_image_subject_facts($facts));
    $isMarketing = preg_match('/تسويق|حملات|إعلان|اعلان|marketing|campaign|advert/u', $source) === 1;
    $hasCreativeOutputs = preg_match('/محتوى|صور|تصميم|content|images?|visuals?|design/u', $source) === 1;
    if (!$isMarketing || !$hasCreativeOutputs) {
        return $plan;
    }
    // Short photographic prompt Flux/SD can follow — never abstract UI mock art.
    $plan['subject'] = 'professional commercial photograph of colorful printed advertising posters and marketing flyers neatly arranged on an oak desk';
    $plan['elements'] = [
        'printed advertising posters',
        'marketing flyers',
        'soft natural window light',
    ];
    $plan['setting'] = 'clean creative studio desk';
    $plan['mood'] = 'premium commercial photography';
    $plan['avoid'] = array_values(array_unique(array_merge([
        'gallery wall',
        'home decor',
        'portrait',
        'people',
        'human faces',
        'empty desk',
        'laptop',
        'keyboard',
        'computer',
        'only sticky notes',
        'only blank paper',
        'empty stationery',
        'random magazines',
        'unrelated magazine covers',
        'architecture magazine',
        'handwritten text',
        'written words',
        'letters on paper',
        'readable text',
        'neon orb',
        'glowing sphere',
        'particle network',
        'abstract fractal',
        'sci-fi hud',
        'analytics dashboard',
        'computer dashboard',
        'computer monitor',
        'device screen',
        'financial charts',
        'performance graphs',
        'trading software',
        'stock market charts',
        'coding interface',
        'generic software screen',
        'office worker',
        'person',
        'face',
    ], (array) ($plan['avoid'] ?? []))));
    return $plan;
}

/**
 * Deterministic accept for marketing-desk captions so a correct flat-lay is not
 * rejected by an over-strict language-model gate asking for brand UI.
 *
 * @param array<string,string> $facts
 */
function rateb_image_caption_hard_accept(array $facts, string $caption): bool
{
    $caption = mb_strtolower(trim($caption));
    if ($caption === '' || rateb_image_caption_hard_reject($facts, $caption) !== null) {
        return false;
    }
    $source = mb_strtolower(rateb_image_subject_facts($facts));
    $isMarketing = preg_match('/تسويق|حملات|إعلان|اعلان|marketing|campaign|advert/u', $source) === 1;
    if (!$isMarketing) {
        return false;
    }
    // Sticky notes alone are not enough; require visible advertising creative outputs.
    return rateb_image_caption_has_creative_outputs($caption);
}

function rateb_image_caption_has_creative_outputs(string $caption): bool
{
    $caption = mb_strtolower(trim($caption));
    if ($caption === '') {
        return false;
    }
    if (preg_match('/\b(brochures?|flyers?|leaflets?|pamphlets?|handouts?|mood board|advertisement|ad mockups?|mockups?|printouts?|storyboards?|posters?|printed ads?|social media ads?|campaign cards?|printed cards?|collage|marketing materials?)\b/u', $caption) === 1) {
        return true;
    }
    // Magazine stacks alone drift to unrelated covers; require an advertising cue.
    if (preg_match('/\bmagazine covers?\b/u', $caption) === 1
        && preg_match('/\b(advert|ad |ads|marketing|campaign|flyer|brochure|mockup)\b/u', $caption) === 1) {
        return true;
    }
    return preg_match('/\b(photos?|photographs?|pictures?)\b/u', $caption) === 1
        && preg_match('/\b(board|collage|brochures?|flyers?|leaflets?|poster|marketing|storyboard|mockup)\b/u', $caption) === 1;
}

/** Free Horde captions often say "folders/cards on a wooden table" for poster flat-lays. */
function rateb_image_caption_has_printed_desk_materials(string $caption): bool
{
    $caption = mb_strtolower(trim($caption));
    if ($caption === '') {
        return false;
    }
    $onDesk = preg_match('/\b(desk|table|wooden|wood surface|wooden surface)\b/u', $caption) === 1;
    $pluralPrints = preg_match('/\b(folders|cards|papers|sheets|prints|documents|booklets)\b/u', $caption) === 1;
    $printed = preg_match('/\b(folders?|cards?|papers?|sheets?|prints?|documents?|booklets?)\b/u', $caption) === 1;
    $manyOrColor = preg_match('/\b(color|colour|colored|coloured|colorful|colourful|several|bunch|stack|stacked|many|printed|paper)\b/u', $caption) === 1;
    if ($onDesk && ($pluralPrints || ($printed && $manyOrColor))) {
        return true;
    }
    // Interrogators sometimes drop the desk and just say "several colorful papers".
    return $pluralPrints && $manyOrColor;
}

/**
 * Deterministic reject for captions that are clearly generic tech art, before the
 * language-model gate. Returns a short reason, or null when the caption may pass.
 *
 * @param array<string,string> $facts
 */
function rateb_image_caption_hard_reject(array $facts, string $caption): ?string
{
    $caption = mb_strtolower(trim($caption));
    if ($caption === '') {
        return 'empty caption';
    }
    $source = mb_strtolower(rateb_image_subject_facts($facts));
    $abstract = preg_match('/\b(neon|orb|glowing sphere|particle|fractal|holograph|sci-?fi|cyberpunk|wireframe|abstract (circles?|lights?|network|geometry|shapes?|art|blocks?)|luminous (sphere|network)|energy field|3d illustration|calligraphy|icon|geometric blocks?|color blocks?)\b/u', $caption) === 1;
    $trading = preg_match('/\b(stock (market|chart)|candlestick|trading (desk|screen|chart)|forex|crypto chart)\b/u', $caption) === 1;
    $dashboardOnly = preg_match('/\b(analytics dashboard|kpi dashboard|generic dashboard|data dashboard)\b/u', $caption) === 1
        && preg_match('/\b(ad|advert|campaign|mockup|print|storyboard|calendar|planning card)\b/u', $caption) !== 1;
    $isMarketing = preg_match('/تسويق|حملات|إعلان|اعلان|marketing|campaign|advert/u', $source) === 1;
    $writtenPaper = preg_match('/\b(handwrit|writing on|written (on|text)|letters on|words on|notebook with text|notepad with text)\b/u', $caption) === 1;
    $creativeOutputs = rateb_image_caption_has_creative_outputs($caption);
    $peopleHeavy = preg_match('/\b(women|woman|men|man|girl|boy|portrait|faces?|people)\b/u', $caption) === 1
        && preg_match('/\b(magazine covers?|product (photos?|photographs?)|advertisement|mockup|printout|brochure|flyer)\b/u', $caption) !== 1;
    $homeDecor = preg_match('/\b(living room|home decor|wicker basket|throw blanket|gallery wall)\b/u', $caption) === 1;
    $missingCreative = $isMarketing && !$creativeOutputs;
    if ($abstract || $trading || $dashboardOnly || ($isMarketing && $writtenPaper) || $missingCreative || ($isMarketing && $peopleHeavy) || ($isMarketing && $homeDecor)) {
        return 'caption shows generic stationery instead of marketing creative outputs';
    }
    $topics = [
        ['/قهو|كوفي|coffee|espresso/u', '/coffee|espresso|cup|beans|cafe/'],
        ['/تمر|تمور|dates/u', '/date|dates|fruit|gift box/'],
        ['/عطر|عطور|بخور|عود|perfume|oud/u', '/perfume|fragrance|oud|bottle/'],
        ['/مطعم|وجب|برجر|بيتزا|شاورما|طعام|restaurant|burger|pizza|meal/u', '/food|meal|dish|restaurant|burger|pizza|shawarma|plate/'],
    ];
    foreach ($topics as [$sourcePattern, $captionPattern]) {
        if (preg_match($sourcePattern, $source) === 1 && preg_match($captionPattern, $caption) !== 1) {
            return 'caption misses the concrete campaign product';
        }
    }
    return null;
}

/**
 * Keyword plan used only when the language model is unavailable. Returns null instead of
 * guessing when the brief matches no known category.
 *
 * @param array<string,string> $facts
 * @return array<string,mixed>|null
 */
function rateb_image_fallback_plan(array $facts): ?array
{
    $text = mb_strtolower(implode(' ', array_intersect_key($facts, array_flip(['product', 'title', 'description', 'positioning', 'product_description', 'headline', 'short_ad']))));
    $digital = preg_match('/منص|تطبيق|برنامج|برمج|موقع|نظام|ذكاء|اصطناع|رقمي|platform|software|\bapp\b|saas|\bai\b/u', $text) === 1;
    $marketing = preg_match('/إعلان|اعلان|تسويق|حمل|محتوى|فيديو|تصميم|advert|\bads?\b|marketing|campaign|video/u', $text) === 1;
    $catalog = [
        ['/قهو|كوفي|coffee|espresso/u', 'food', 'freshly brewed specialty coffee cup with roasted coffee beans', ['ceramic coffee cup', 'roasted coffee beans', 'rising steam'], 'cafe counter with warm wood'],
        ['/تمر|تمور|dates/u', 'food', 'premium Saudi dates arranged in an elegant gift box', ['glossy dates', 'elegant open gift box', 'small brass dish'], 'clean marble table'],
        ['/عطر|عطور|بخور|عود|perfume|oud/u', 'physical_product', 'luxury perfume bottle with oud wood pieces', ['glass perfume bottle', 'oud wood chips', 'soft fabric drape'], 'dark elegant studio surface'],
        ['/حلويات|حلى|كيك|شوكولات|dessert|cake|chocolate/u', 'food', 'fresh desserts and cakes beautifully plated', ['plated dessert', 'cake slice', 'fresh berries'], 'bright bakery counter'],
        ['/مطعم|وجب|برجر|بيتزا|شاورما|طعام|أكل|restaurant|burger|pizza|meal/u', 'food', 'appetizing freshly served restaurant meal', ['main dish on a plate', 'fresh side ingredients', 'cold drink'], 'restaurant table'],
        ['/عباي|ملابس|أزياء|فستان|ثوب|abaya|fashion|clothing|dress/u', 'physical_product', 'elegant clothing collection displayed on a garment rack', ['folded fabric', 'garment rack', 'fabric texture detail'], 'bright boutique interior'],
        ['/سيار|car rental|\bcars?\b/u', 'physical_product', 'clean modern car parked in bright daylight', ['glossy car body', 'alloy wheel', 'clean road'], 'modern city street'],
        ['/عقار|شقة|شقق|فيلا|أرض|real estate|apartment|villa/u', 'place', 'modern residential building and bright furnished living space', ['building facade', 'furnished living room', 'large windows'], 'sunny residential neighborhood'],
        ['/أسنان|اسنان|dental|dentist/u', 'local_service', 'modern dental clinic treatment room', ['dental chair', 'clean instruments tray', 'bright clinic lighting'], 'clean clinic interior'],
        ['/عياد|تجميل|بشر|صالون|سبا|clinic|beauty|skin|salon|spa/u', 'local_service', 'calm modern beauty clinic with skincare products', ['skincare bottles', 'soft towels', 'treatment bed'], 'clean bright clinic interior'],
        ['/دور|تدريب|تعليم|كورس|أكاديمي|course|training|academy|education/u', 'digital_service', 'laptop showing an online course lesson with notebook and pen', ['laptop with video lesson', 'open notebook', 'coffee mug'], 'tidy study desk'],
        ['/نادي|رياض|لياق|جيم|gym|fitness/u', 'local_service', 'modern gym with dumbbells and training equipment', ['dumbbells', 'training bench', 'water bottle'], 'bright modern gym'],
    ];
    foreach ($catalog as [$pattern, $category, $subject, $elements, $setting]) {
        if (preg_match($pattern, $text) === 1) {
            return ['sufficient' => true, 'category' => $category, 'subject' => $subject, 'elements' => $elements, 'setting' => $setting, 'mood' => 'bright, clean, trustworthy', 'avoid' => []];
        }
    }
    if ($digital && $marketing) {
        return [
            'sufficient' => true,
            'category' => 'digital_service',
            'subject' => 'top-down commercial photo of colorful advertising posters and printed marketing flyers spread across a wooden desk',
            'elements' => ['colorful advertising posters', 'printed marketing flyers', 'stacked campaign printouts'],
            'setting' => 'bright marketing studio desk',
            'mood' => 'professional, clean, commercial',
            'avoid' => ['gallery wall', 'home decor', 'portrait', 'empty desk', 'laptop', 'random magazines', 'neon orb', 'analytics dashboard'],
        ];
    }
    if ($digital) {
        return [
            'sufficient' => true,
            'category' => 'digital_service',
            'subject' => 'photorealistic desk with a tablet showing a clean modern app interface made of abstract colorful shapes',
            'elements' => ['tablet with app interface', 'notebook', 'soft desk lamp'],
            'setting' => 'modern minimalist work desk',
            'mood' => 'innovative, bright, professional',
            'avoid' => ['neon orb', 'glowing sphere', 'picture frame', 'empty room', 'landscape'],
        ];
    }
    return null;
}

/**
 * Final Stable Diffusion prompt ("positive ### negative") for one image. The variant picks a
 * different shot per image so repeated images of the same campaign are not identical.
 *
 * @param array<string,mixed> $plan
 * @param array<string,string> $facts
 */
function rateb_image_prompt_clip_list(string $text, int $limit): string
{
    $kept = [];
    foreach (array_filter(array_map('trim', explode(',', $text))) as $part) {
        $candidate = $kept === [] ? $part : implode(', ', $kept) . ', ' . $part;
        if (mb_strlen($candidate) > $limit) {
            break;
        }
        $kept[] = $part;
    }
    return implode(', ', $kept);
}

function rateb_image_prompt_compose(array $plan, array $facts, string $ratio, int $variant): string
{
    $category = (string) ($plan['category'] ?? 'other');
    $shots = match ($category) {
        'digital_service' => [
            'overhead flat lay commercial photograph',
            'front-facing mood board photograph',
            'slightly angled creative wall photograph',
            'clean studio wall composition',
        ],
        default => [
            'hero shot with the subject centered at eye level',
            'three-quarter angle close-up with shallow depth of field',
            'in-use scene showing hands interacting with the subject',
            'top-down flat lay composition',
        ],
    };
    $layout = match ($ratio) {
        '9:16' => 'vertical mobile story layout, subject in the middle third, plain uncluttered space at the top and bottom',
        '4:5' => 'portrait feed layout, subject in the lower two thirds, plain empty space at the top',
        default => 'square layout, subject in the lower two thirds, plain empty space at the top',
    };
    $positive = [(string) $plan['subject']];
    foreach ((array) ($plan['elements'] ?? []) as $element) {
        if (stripos((string) $plan['subject'], (string) $element) === false) {
            $positive[] = (string) $element;
        }
    }
    $positive[] = (string) ($plan['setting'] ?? '');
    $positive[] = (string) ($plan['mood'] ?? '');
    $positive[] = $shots[abs($variant) % count($shots)];
    $positive[] = $layout;
    $positive[] = 'photorealistic commercial advertising photograph, natural soft studio lighting, sharp focus, high detail, no abstract art';
    $positive = implode(', ', array_values(array_filter(array_map('trim', $positive), static fn(string $part): bool => $part !== '')));
    $negative = [];
    foreach ((array) ($plan['avoid'] ?? []) as $avoid) {
        $negative[] = (string) $avoid;
    }
    $negative = array_merge($negative, [
        'neon orb', 'glowing sphere', 'particle network', 'abstract fractal', 'sci-fi hud', 'anime',
        'text', 'words', 'letters', 'typography', 'arabic script', 'caption', 'watermark', 'signature', 'logo', 'brand name',
        'blurry', 'lowres', 'jpeg artifacts', 'deformed', 'distorted', 'extra fingers', 'bad hands', 'cropped', 'out of frame',
        'collage', 'split screen', 'cartoon', 'illustration', 'generic stock photo', 'unrelated objects', 'empty room', 'wall art', 'picture frame',
    ]);
    if ($category !== 'local_service') {
        $negative[] = 'face';
    }
    $negative = implode(', ', array_values(array_unique(array_filter(array_map(static fn(string $part): string => strtolower(trim($part)), $negative)))));
    return rateb_image_prompt_clip_list($positive, 520) . ' ### ' . rateb_image_prompt_clip_list($negative, 480);
}

/** Prefer photorealistic SD1.5 models that follow commercial product prompts at 512–576px. */
function rateb_image_model_preferences(string $category): array
{
    $photo = [
        'Deliberate',
        'stable_diffusion',
        'ICBINP - I Can\'t Believe It\'s Not Photography',
        'ICBINP',
        'AbsoluteReality',
        'Realistic Vision',
        'Edge Of Realism',
        'majicMIX realistic',
        'Analog Madness',
        'Dreamshaper',
        'Deliberate 3.0',
    ];
    $digital = [
        'Flux.1-Schnell fp8 (Compact)',
        'Flux.1-Schnell',
        'Deliberate',
        'stable_diffusion',
        'AbsoluteReality',
        'Realistic Vision',
        'Edge Of Realism',
        'majicMIX realistic',
        'Dreamshaper',
        'Deliberate 3.0',
    ];
    return match ($category) {
        'digital_service' => $digital,
        'physical_product', 'food', 'local_service', 'place' => $photo,
        default => $photo,
    };
}

/**
 * Pick up to three online preferred models so Horde can fall back if one worker pool empties.
 *
 * @param array<string,int> $counts
 * @return list<string>
 */
function rateb_image_select_models(array $preferred, array $counts, int $max = 3): array
{
    $available = [];
    foreach ($preferred as $name) {
        $n = (int) ($counts[$name] ?? 0);
        if ($n < 1) {
            continue;
        }
        $available[] = ['name' => $name, 'count' => $n];
    }
    // Prefer prompt-faithful Flux workers first when any are online, then busiest photoreal fallbacks.
    usort($available, static function (array $a, array $b): int {
        $aFlux = stripos($a['name'], 'flux') !== false ? 1 : 0;
        $bFlux = stripos($b['name'], 'flux') !== false ? 1 : 0;
        if ($aFlux !== $bFlux) {
            return $bFlux <=> $aFlux;
        }
        return $b['count'] <=> $a['count'];
    });
    $chosen = [];
    foreach ($available as $row) {
        $chosen[] = $row['name'];
        if (count($chosen) >= $max) {
            break;
        }
    }
    return $chosen;
}

/**
 * @param list<string> $models
 * @return array<string,mixed>
 */
function rateb_image_generation_params(int $width, int $height, array $models = []): array
{
    $flux = false;
    foreach ($models as $name) {
        if (stripos((string) $name, 'flux') !== false) {
            $flux = true;
            break;
        }
    }
    if ($flux) {
        return [
            'width' => $width,
            'height' => $height,
            'steps' => 8,
            'cfg_scale' => 2.5,
            'sampler_name' => 'k_euler',
            'karras' => false,
            'clip_skip' => 1,
            'n' => 1,
        ];
    }
    return [
        'width' => $width,
        'height' => $height,
        'steps' => 25,
        'cfg_scale' => 8.5,
        'sampler_name' => 'k_euler_a',
        'karras' => true,
        'clip_skip' => 1,
        'n' => 1,
    ];
}

/** True when the campaign brief is a marketing/ads platform that needs creative flat-lays. */
function rateb_image_is_marketing_brief(array $facts): bool
{
    $source = mb_strtolower(rateb_image_subject_facts($facts));
    return preg_match('/تسويق|حملات|إعلان|اعلان|marketing|campaign|advert/u', $source) === 1
        && preg_match('/محتوى|صور|تصميم|content|images?|visuals?|design|منص|platform|ذكاء|ai/u', $source) === 1;
}

/**
 * Base64 seed used for marketing img2img so free workers stay near posters/flyers.
 */
function rateb_image_marketing_seed_base64(): ?string
{
    $path = dirname(__DIR__) . '/public/assets/image-seeds/marketing-flatlay.png';
    if (!is_file($path)) {
        return null;
    }
    $bytes = (string) file_get_contents($path);
    if ($bytes === '' || strlen($bytes) < 32 || strlen($bytes) > 900000) {
        return null;
    }
    return base64_encode($bytes);
}

/** @return array{relevant:bool,reason:string}|null */
function rateb_image_relevance_parse(string $content): ?array
{
    if (preg_match('/\{.*\}/s', $content, $match) !== 1) {
        return null;
    }
    $data = json_decode($match[0], true);
    if (!is_array($data) || !is_bool($data['relevant'] ?? null)) {
        return null;
    }
    return [
        'relevant' => $data['relevant'],
        'reason' => mb_substr(trim((string) ($data['reason'] ?? '')), 0, 500),
    ];
}

function rateb_image_prompt_log(array $entry): void
{
    $dir = rateb_image_job_directory();
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return;
    }
    $file = $dir . DIRECTORY_SEPARATOR . 'prompts.log';
    if (is_file($file) && (int) @filesize($file) > 1048576) {
        @rename($file, $file . '.1');
    }
    @file_put_contents($file, json_encode(['at' => date('c')] + $entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
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

function rateb_dialect_line(string $code, string $resolved): string
{
    if ($resolved === 'en' || !in_array($code, ['najdi', 'hijazi', 'khaleeji', 'fusha'], true)) {
        return '';
    }
    return match ($code) {
        'najdi' => 'اكتب العربية بنبرة نجدية إعلانية طبيعية.',
        'hijazi' => 'اكتب العربية بنبرة حجازية إعلانية طبيعية.',
        'khaleeji' => 'اكتب العربية بنبرة خليجية إعلانية طبيعية.',
        default => 'اكتب العربية بفصحى إعلانية واضحة.',
    };
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
    $avoid = trim((string) ($brand['forbidden'] ?? ''));
    if ($avoid !== '') {
        $lines[] = 'Avoid: ' . $avoid;
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
        $content = is_string($decoded[$speech] ?? null) ? $decoded[$speech] : '';
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
    $validationId = strtolower(trim((string) ($job['validation_id'] ?? '')));
    return [
        'job_id' => strtolower(trim((string) ($job['job_id'] ?? ''))),
        'user_id' => (int) ($job['user_id'] ?? 0),
        'campaign_id' => (int) ($job['campaign_id'] ?? 0),
        'usage_id' => (int) ($job['usage_id'] ?? 0),
        'created' => (int) ($job['created'] ?? 0),
        'phase' => $phase === 'submitting' ? 'submitting' : 'pending',
        'validation_id' => preg_match('/^[a-f0-9-]{36}$/', $validationId) === 1 ? $validationId : '',
        'retry_used' => !empty($job['retry_used']) ? 1 : 0,
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
    return rateb_image_jobs_take($jobs, $jobId, $usageId)['jobs'];
}

/**
 * Remove matching jobs and report which ones were removed, so a caller holding the
 * ledger lock can settle each removed reservation exactly once.
 *
 * @param array<string,array<string,mixed>> $jobs
 * @return array{jobs:array<string,array<string,mixed>>,removed:array<int,array<string,mixed>>}
 */
function rateb_image_jobs_take(array $jobs, string $jobId, int $usageId): array
{
    $jobId = strtolower(trim($jobId));
    $removed = [];
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
            $removed[] = $job;
        }
    }
    return ['jobs' => $jobs, 'removed' => $removed];
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
