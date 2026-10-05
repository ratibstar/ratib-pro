<?php
declare(strict_types=1);

function rateb_read_idea(string $idea): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $idea) ?? '');
    $found = [
        'product' => null,
        'audience' => null,
        'objective' => null,
        'location' => null,
        'channels' => [],
        'brand_tone' => null,
        'core_message' => $text !== '' ? $text : null,
    ];
    if ($text === '') {
        return $found;
    }
    if (preg_match('/(?:عندي|لدي|عندنا|معي)\s+(.+?)(?=\s+(?:وأبغى|وابغى|وأبي|وابي|وأبا|وابا|وأريد|واريد|ونبي|عشان|حتى|في|لل)\b|[.،]|$)/u', $text, $match)) {
        $found['product'] = rateb_idea_clean($match[1]);
    } elseif (preg_match('/\b(?:I have|we have|launching|my product is|my service is)\s+(.+?)(?=\s+(?:and|in|to|for)\b|[.]|$)/i', $text, $match)) {
        $found['product'] = rateb_idea_clean($match[1]);
    }
    $places = ['خميس مشيط', 'المدينة المنورة', 'مكة المكرمة', 'الرياض', 'جدة', 'مكة', 'المدينة', 'الدمام', 'الخبر', 'الظهران', 'الأحساء', 'الاحساء', 'الطائف', 'أبها', 'ابها', 'تبوك', 'حائل', 'بريدة', 'نجران', 'جازان', 'ينبع', 'الجبيل', 'Riyadh', 'Jeddah', 'Makkah', 'Mecca', 'Medina', 'Dammam', 'Khobar'];
    foreach ($places as $place) {
        if (preg_match('/(?:في|بـ|in)\s+' . preg_quote($place, '/') . '/ui', $text)) {
            $found['location'] = $place;
            break;
        }
    }
    $needles = ['واتساب', 'واتس اب', 'واتس', 'WhatsApp', 'whatsapp', 'انستقرام', 'انستغرام', 'Instagram', 'instagram', 'سناب شات', 'سناب', 'Snapchat', 'snapchat', 'تيك توك', 'TikTok', 'tiktok', 'تويتر', 'Twitter', 'twitter'];
    $spans = [];
    foreach ($needles as $needle) {
        $pos = mb_stripos($text, $needle);
        if ($pos === false) {
            continue;
        }
        $end = $pos + mb_strlen($needle);
        foreach ($spans as $span) {
            if ($pos < $span[1] && $end > $span[0]) {
                continue 2;
            }
        }
        $spans[] = [$pos, $end];
        $found['channels'][] = mb_substr($text, $pos, mb_strlen($needle));
    }
    if (preg_match('/(?:أزيد|ازيد)\s+مبيعات(?:\s+((?:ال)?[\p{Arabic}]{2,24}))?/u', $text, $match)) {
        $tail = rateb_idea_clean((string) ($match[1] ?? ''));
        $tail = preg_replace('/^ال/u', '', $tail) ?? $tail;
        $found['objective'] = rateb_idea_clean('زيادة مبيعات' . ($tail !== '' ? ' ' . $tail : ''));
    } elseif (preg_match('/\bincrease\s+[A-Za-z0-9 ]{0,40}?sales\b/i', $text, $match)) {
        $found['objective'] = rateb_idea_clean($match[0]);
    } elseif (preg_match('/(?:أطلق|اطلق|إطلاق|اطلاق)/u', $text)) {
        $found['objective'] = 'إطلاق';
    } elseif (preg_match('/\blaunch\b/i', $text)) {
        $found['objective'] = 'Launch';
    }
    $audiences = ['للنساء', 'للشباب', 'للعائلات', 'للأطفال', 'للاطفال', 'للتجار', 'للشركات'];
    foreach ($audiences as $audience) {
        if (mb_stripos($text, $audience) !== false) {
            $found['audience'] = $audience;
            break;
        }
    }
    if ($found['audience'] === null && preg_match('/\bfor\s+(women|men|families|students|companies)\b/i', $text, $match)) {
        $found['audience'] = rateb_idea_clean($match[0]);
    }
    $tones = ['فاخر', 'فخم', 'راقي', 'هادئ', 'جريء', 'luxury', 'premium', 'calm', 'bold'];
    foreach ($tones as $tone) {
        if (mb_stripos($text, $tone) !== false) {
            $found['brand_tone'] = $tone;
            break;
        }
    }
    return $found;
}

function rateb_idea_clean(string $value): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    $value = preg_replace('/^[\s.،,]+|[\s.،,]+$/u', '', $value) ?? $value;
    return mb_substr($value, 0, 240);
}

function rateb_video_seconds(string $bytes, string $mime): ?int
{
    if ($mime === 'video/mp4') {
        return rateb_mp4_seconds($bytes);
    }
    if ($mime === 'video/webm') {
        return rateb_webm_seconds($bytes);
    }
    return null;
}

function rateb_mp4_seconds(string $bytes): ?int
{
    $offset = 0;
    $length = strlen($bytes);
    $guard = 0;
    while ($offset + 8 <= $length && $guard < 4000) {
        $guard++;
        $size = unpack('N', substr($bytes, $offset, 4))[1];
        $type = substr($bytes, $offset + 4, 4);
        if ($size < 8) {
            return null;
        }
        if ($type === 'moov' || $type === 'trak' || $type === 'mdia' || $type === 'minf' || $type === 'stbl') {
            $offset += 8;
            continue;
        }
        if ($type === 'mvhd') {
            $version = ord($bytes[$offset + 8]);
            if ($version === 0 && $offset + 28 <= $length) {
                $scale = unpack('N', substr($bytes, $offset + 20, 4))[1];
                $duration = unpack('N', substr($bytes, $offset + 24, 4))[1];
                return $scale > 0 ? (int) ceil($duration / $scale) : null;
            }
            if ($version === 1 && $offset + 40 <= $length) {
                $scale = unpack('N', substr($bytes, $offset + 28, 4))[1];
                $high = unpack('N', substr($bytes, $offset + 32, 4))[1];
                $low = unpack('N', substr($bytes, $offset + 36, 4))[1];
                return $scale > 0 ? (int) ceil((($high * 4294967296) + $low) / $scale) : null;
            }
            return null;
        }
        if ($size === 1) {
            return null;
        }
        $offset += $size;
    }
    return null;
}

function rateb_webm_seconds(string $bytes): ?int
{
    $pos = strpos($bytes, 'Duration');
    if ($pos === false) {
        return null;
    }
    if (preg_match('/Duration[^0-9]{0,12}([0-9]+(?:\.[0-9]+)?)/', substr($bytes, $pos, 40), $match) === 1) {
        return (int) ceil((float) $match[1]);
    }
    return null;
}

function rateb_draft_root(int $userId): string
{
    return MEDIA_ROOT . '/drafts/' . $userId;
}

function rateb_draft_path(int $userId, string $stored): ?string
{
    if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif|mp4|webm)$/', $stored) !== 1) {
        return null;
    }
    $root = rateb_draft_root($userId);
    $realRoot = realpath($root);
    $real = realpath($root . '/' . $stored);
    if ($realRoot === false || $real === false || !is_file($real) || !str_starts_with($real, $realRoot . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $real;
}

function rateb_draft_clear(int $userId, array $keep = []): void
{
    $root = rateb_draft_root($userId);
    if (!is_dir($root)) {
        return;
    }
    $allowed = [];
    foreach ($keep as $name) {
        $allowed[(string) $name] = true;
    }
    foreach (scandir($root) ?: [] as $name) {
        if ($name === '.' || $name === '..' || isset($allowed[$name])) {
            continue;
        }
        $path = rateb_draft_path($userId, $name);
        if ($path !== null) {
            @unlink($path);
        }
    }
}

function rateb_idea_accept_files(int $userId, array $plan, array $existing): array
{
    $files = is_array($_FILES['files'] ?? null) ? $_FILES['files'] : null;
    $incoming = [];
    if ($files && isset($files['name']) && is_array($files['name'])) {
        foreach ($files['name'] as $index => $name) {
            $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $incoming[] = [
                'name' => (string) $name,
                'tmp' => (string) ($files['tmp_name'][$index] ?? ''),
                'error' => $error,
                'size' => (int) ($files['size'][$index] ?? 0),
            ];
        }
    }
    $images = 0;
    $videos = 0;
    foreach ($existing as $file) {
        if (($file['kind'] ?? '') === 'video') {
            $videos++;
        } else {
            $images++;
        }
    }
    $imageCap = max(0, (int) ($plan['image_limit'] ?? 0));
    $videoCap = min(1, max(0, (int) ($plan['video_limit'] ?? 0)));
    $maxSeconds = max(0, (int) ($plan['video_max_seconds'] ?? 0));
    $stored = $existing;
    $created = [];
    foreach ($incoming as $upload) {
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            return rateb_idea_reject_uploads($userId, $created, $upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE ? 'file_size' : 'file_type', $existing);
        }
        $tmp = $upload['tmp'];
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return rateb_idea_reject_uploads($userId, $created, 'file_type', $existing);
        }
        $bytes = file_get_contents($tmp);
        if ($bytes === false || $bytes === '') {
            return rateb_idea_reject_uploads($userId, $created, 'file_type', $existing);
        }
        $kind = null;
        $mime = media_detect_mime($bytes, 'image');
        if ($mime !== null) {
            $kind = 'image';
        } else {
            $mime = media_detect_mime($bytes, 'video');
            if ($mime !== null) {
                $kind = 'video';
            }
        }
        if ($kind === null || $mime === null || media_reject_active_content($bytes)) {
            return rateb_idea_reject_uploads($userId, $created, 'file_type', $existing);
        }
        if (strlen($bytes) > media_max_bytes($kind)) {
            return rateb_idea_reject_uploads($userId, $created, 'file_size', $existing);
        }
        if ($kind === 'image') {
            $images++;
            if ($images > $imageCap) {
                return rateb_idea_reject_uploads($userId, $created, 'plan_images', $existing);
            }
        } else {
            $videos++;
            if ($videos > $videoCap) {
                return rateb_idea_reject_uploads($userId, $created, $videoCap < 1 ? 'plan_videos' : 'video_one', $existing);
            }
            $seconds = rateb_video_seconds($bytes, $mime);
            if ($seconds === null) {
                return rateb_idea_reject_uploads($userId, $created, 'video_unknown', $existing);
            }
            if ($maxSeconds > 0 && $seconds > $maxSeconds) {
                return rateb_idea_reject_uploads($userId, $created, 'video_long', $existing);
            }
        }
        $extension = media_kind_mimes()[$kind][$mime];
        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $dir = rateb_draft_root($userId);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            return rateb_idea_reject_uploads($userId, $created, 'file_type', $existing);
        }
        $path = $dir . '/' . $name;
        if (file_put_contents($path, $bytes, LOCK_EX) === false) {
            return rateb_idea_reject_uploads($userId, $created, 'file_type', $existing);
        }
        $created[] = $name;
        chmod($path, 0600);
        $row = [
            'stored' => $name,
            'kind' => $kind,
            'name' => media_safe_download_name($upload['name'], $extension),
            'mime' => $mime,
            'bytes' => strlen($bytes),
            'analysis' => 'unavailable',
        ];
        if ($kind === 'image') {
            $vision = rateb_vision_analyze($bytes, $mime);
            if (is_array($vision) && trim((string) ($vision['summary'] ?? '')) !== '') {
                $row['analysis'] = 'ready';
                $row['summary'] = mb_substr(trim((string) $vision['summary']), 0, 500);
            }
        }
        $stored[] = $row;
    }
    return ['error' => '', 'files' => $stored];
}

function rateb_idea_reject_uploads(int $userId, array $created, string $error, array $existing): array
{
    foreach ($created as $name) {
        $path = rateb_draft_path($userId, $name);
        if ($path !== null) {
            @unlink($path);
        }
    }
    return ['error' => $error, 'files' => $existing];
}

function rateb_idea_commit(int $userId, array $draft): int
{
    $idea = trim((string) ($draft['idea'] ?? ''));
    $extract = is_array($draft['extract'] ?? null) ? $draft['extract'] : [];
    $product = rateb_idea_clean((string) ($extract['product'] ?? ''));
    if ($idea === '' || $product === '') {
        throw new RuntimeException('incomplete');
    }
    $files = is_array($draft['files'] ?? null) ? $draft['files'] : [];
    $pdo = db();
    $title = mb_substr($product !== '' ? $product : $idea, 0, 120);
    $pdo->beginTransaction();
    $campaignId = 0;
    try {
        $stmt = $pdo->prepare('INSERT INTO campaigns (user_id, title, product_name, description, price, target_customer, status, objective, start_date, end_date, budget, created_at, updated_at) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, NULL, NULL, NULL, NOW(), NOW())');
        $stmt->execute([
            $userId,
            $title,
            mb_substr($product, 0, 255),
            $idea,
            mb_substr(trim((string) ($extract['audience'] ?? '')), 0, 255),
            'draft',
            mb_substr(trim((string) ($extract['objective'] ?? '')), 0, 500),
        ]);
        $campaignId = (int) $pdo->lastInsertId();
        $campaign = ['id' => $campaignId, 'user_id' => $userId];
        $source = [];
        $analyzed = false;
        foreach ($files as $file) {
            $path = rateb_draft_path($userId, (string) ($file['stored'] ?? ''));
            if ($path === null) {
                throw new RuntimeException('file');
            }
            $bytes = file_get_contents($path);
            if ($bytes === false) {
                throw new RuntimeException('file');
            }
            media_store_bytes($campaign, (string) $file['kind'], 'upload', (string) $file['name'], (string) $file['mime'], $bytes);
            if ((string) ($file['analysis'] ?? '') === 'ready') {
                $analyzed = true;
            }
            $item = [
                'kind' => (string) $file['kind'],
                'name' => (string) $file['name'],
                'analysis' => (string) ($file['analysis'] ?? 'unavailable'),
            ];
            if ($item['analysis'] === 'ready' && trim((string) ($file['summary'] ?? '')) !== '') {
                $item['summary'] = (string) $file['summary'];
            }
            $source[] = $item;
        }
        $channels = [];
        foreach ((array) ($extract['channels'] ?? []) as $channel) {
            $channel = trim((string) $channel);
            if ($channel !== '') {
                $channels[] = $channel;
            }
        }
        $saved = rateb_sync_brief($pdo, $userId, $campaignId, [
            'product' => $product,
            'description' => $idea,
            'audience' => (string) ($extract['audience'] ?? ''),
            'objective' => (string) ($extract['objective'] ?? ''),
            'campaign_language' => (string) ($draft['language'] ?? 'auto'),
            'location' => (string) ($extract['location'] ?? ''),
            'channels' => implode(' · ', $channels),
            'brand_tone' => (string) ($extract['brand_tone'] ?? ''),
            'core_message' => $idea,
            'source_media' => $source === [] ? null : json_encode($source, JSON_UNESCAPED_UNICODE),
            'media_analysis' => $files === [] ? null : json_encode([
                'status' => $analyzed ? 'ready' : 'unavailable',
                'provider' => rateb_vision_provider(),
            ], JSON_UNESCAPED_UNICODE),
        ]);
        if (!$saved) {
            throw new RuntimeException('brief');
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($campaignId > 0) {
            $dir = MEDIA_ROOT . '/' . $campaignId;
            if (is_dir($dir)) {
                foreach (scandir($dir) ?: [] as $name) {
                    if ($name === '.' || $name === '..') {
                        continue;
                    }
                    $full = $dir . '/' . $name;
                    if (is_file($full)) {
                        @unlink($full);
                    }
                }
                @rmdir($dir);
            }
        }
        throw $error;
    }
    rateb_draft_clear($userId);
    unset($_SESSION['campaign_draft']);
    return $campaignId;
}

function rateb_next_gap(array $extract, array $answered): ?string
{
    if (trim((string) ($extract['product'] ?? '')) === '') {
        return 'product';
    }
    if (trim((string) ($extract['objective'] ?? '')) === '' && empty($answered['objective'])) {
        return 'objective';
    }
    if (trim((string) ($extract['audience'] ?? '')) === '' && empty($answered['audience'])) {
        return 'audience';
    }
    return null;
}

function rateb_size_label(int $bytes, bool $arabic): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ($arabic ? ' م.ب' : ' MB');
    }
    if ($bytes >= 1024) {
        return (string) round($bytes / 1024) . ($arabic ? ' ك.ب' : ' KB');
    }
    return (string) $bytes . ($arabic ? ' بايت' : ' B');
}

function rateb_edit_changes(string $note, array $brief): ?array
{
    $note = trim(preg_replace('/\s+/u', ' ', $note) ?? '');
    if ($note === '') {
        return [];
    }
    $changes = [];
    if (preg_match('/(?:غيّر|غير|حدّث|حدث|خلّ|خل)\s+الموقع\s+(?:إلى|الى)\s+(.+)/u', $note, $match) === 1) {
        $changes['location'] = rateb_idea_clean($match[1]);
    } elseif (preg_match('/\b(?:change|set|update)\s+the\s+location\s+to\s+(.+)/i', $note, $match) === 1) {
        $changes['location'] = rateb_idea_clean($match[1]);
    }
    if (preg_match('/(?:أبغى|ابغى|أبي|ابي|ودي|بغيت)?\s*(?:أستهدف|استهدف)\s+(.+)/u', $note, $match) === 1) {
        $extra = rateb_idea_clean((string) (preg_replace('/\s*(?:أيضاً|ايضاً|أيضا|ايضا)\s*$/u', '', $match[1]) ?? $match[1]));
        $current = trim((string) ($brief['audience'] ?? ''));
        if ($extra !== '') {
            $changes['audience'] = (preg_match('/أيضاً|ايضاً|أيضا|ايضا/u', $note) === 1 && $current !== '')
                ? mb_substr($current . '، ' . $extra, 0, 255)
                : $extra;
        }
    } elseif (preg_match('/\b(?:also\s+)?target\s+(.+?)(?:\s+as well)?\s*$/i', $note, $match) === 1) {
        $extra = rateb_idea_clean($match[1]);
        $current = trim((string) ($brief['audience'] ?? ''));
        if ($extra !== '') {
            $changes['audience'] = (preg_match('/\b(?:also|as well)\b/i', $note) === 1 && $current !== '')
                ? mb_substr($current . ', ' . $extra, 0, 255)
                : $extra;
        }
    }
    if (preg_match('/أكثر\s+حماس|اكثر\s+حماس/u', $note) === 1) {
        $changes['brand_tone'] = 'حماسي';
    } elseif (preg_match('/\bmore\s+enthusiastic\b/i', $note) === 1) {
        $changes['brand_tone'] = 'enthusiastic';
    }
    if (preg_match('/(?:سمِّ|سمّ|سمي)\s+(?:الحملة\s+)?(.+)/u', $note, $match) === 1) {
        $changes['title'] = mb_substr(rateb_idea_clean($match[1]), 0, 120);
    } elseif (preg_match('/\brename\s+the\s+campaign\s+to\s+(.+)/i', $note, $match) === 1) {
        $changes['title'] = mb_substr(rateb_idea_clean($match[1]), 0, 120);
    }
    return $changes === [] ? null : $changes;
}

function rateb_store_edit(int $userId, int $campaignId, array $brief, array $changes): void
{
    $pdo = db();
    $columns = [
        'title' => 120,
        'product' => 255,
        'description' => 4000,
        'audience' => 255,
        'objective' => 500,
        'location' => 160,
        'channels' => 255,
        'brand_tone' => 160,
    ];
    $clean = [];
    foreach ($changes as $key => $value) {
        if (!isset($columns[$key]) && $key !== 'campaign_language') {
            continue;
        }
        $text = trim((string) $value);
        if ($text === '') {
            continue;
        }
        $clean[$key] = $key === 'campaign_language'
            ? rateb_valid_language($text)
            : mb_substr($text, 0, $columns[$key]);
    }
    if ($clean === []) {
        return;
    }
    if (isset($clean['title'])) {
        $pdo->prepare('UPDATE campaigns SET title = ?, updated_at = NOW() WHERE id = ? AND user_id = ?')->execute([$clean['title'], $campaignId, $userId]);
    }
    $sets = [];
    $values = [];
    foreach (['product' => 'product_name', 'description' => 'description', 'audience' => 'target_customer', 'objective' => 'objective'] as $key => $column) {
        if (!isset($clean[$key])) {
            continue;
        }
        $sets[] = $column . ' = ?';
        $values[] = $clean[$key];
    }
    if ($sets !== []) {
        $values[] = $campaignId;
        $values[] = $userId;
        $pdo->prepare('UPDATE campaigns SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ? AND user_id = ?')->execute($values);
    }
    $input = ['campaign_language' => (string) ($brief['campaign_language'] ?? 'auto')];
    foreach (['product', 'description', 'audience', 'objective', 'location', 'channels', 'brand_tone', 'campaign_language'] as $key) {
        if (isset($clean[$key])) {
            $input[$key] = $clean[$key];
        }
    }
    if (isset($clean['description'])) {
        $input['core_message'] = $clean['description'];
    }
    rateb_sync_brief($pdo, $userId, $campaignId, $input);
}

function rateb_idea_message(string $key): string
{
    $arabic = ui_language() === 'ar';
    $messages = [
        'idea_required' => ['اكتب فكرتك أولًا.', 'Write your idea first.'],
        'idea_long' => ['الفكرة طويلة.', 'The idea is too long.'],
        'file_type' => ['نوع الملف غير مدعوم.', 'This file type is not supported.'],
        'file_size' => ['حجم الملف أكبر من الحد.', 'The file is larger than the limit.'],
        'video_one' => ['يمكن إرفاق فيديو واحد.', 'You can attach one video.'],
        'video_long' => ['مدة الفيديو أطول من حد خطتك.', 'The video is longer than your plan allows.'],
        'video_unknown' => ['تعذر التحقق من مدة الفيديو.', 'The video duration could not be checked.'],
        'plan_images' => ['عدد الصور يتجاوز حد خطتك.', 'The number of photos is above your plan limit.'],
        'plan_videos' => ['خطتك لا تسمح بفيديو.', 'Your plan does not include video.'],
        'ask_required' => ['اكتب المنتج أو الخدمة.', 'Write the product or service.'],
        'save_failed' => ['تعذر حفظ الحملة.', 'The campaign could not be saved.'],
    ];
    if (!isset($messages[$key])) {
        return '';
    }
    return $messages[$key][$arabic ? 0 : 1];
}
