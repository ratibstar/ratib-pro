<?php
declare(strict_types=1);

function ensure_campaign_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $existing = [];
    foreach ($pdo->query('SHOW COLUMNS FROM campaigns') as $row) {
        $existing[(string) $row['Field']] = (string) $row['Type'];
    }
    $add = [
        'objective' => 'ALTER TABLE campaigns ADD COLUMN objective VARCHAR(500) NULL',
        'start_date' => 'ALTER TABLE campaigns ADD COLUMN start_date DATE NULL',
        'end_date' => 'ALTER TABLE campaigns ADD COLUMN end_date DATE NULL',
        'budget' => 'ALTER TABLE campaigns ADD COLUMN budget DECIMAL(12,2) NULL',
    ];
    foreach ($add as $name => $sql) {
        if (!isset($existing[$name])) {
            $pdo->exec($sql);
        }
    }
    if (isset($existing['status']) && str_starts_with(strtolower($existing['status']), 'enum(') && !str_contains(strtolower($existing['status']), 'in_progress')) {
        $pdo->exec("ALTER TABLE campaigns MODIFY status VARCHAR(32) NOT NULL DEFAULT 'draft'");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS campaign_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        title VARCHAR(255) NOT NULL,
        body MEDIUMTEXT NOT NULL,
        item_date DATE NULL,
        planner_status VARCHAR(16) NOT NULL DEFAULT 'draft',
        approval_status VARCHAR(16) NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY campaign_id (campaign_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS campaign_variations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        label VARCHAR(120) NOT NULL,
        output_type VARCHAR(64) NOT NULL,
        content MEDIUMTEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY campaign_id (campaign_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS brand_kits (
        user_id BIGINT UNSIGNED NOT NULL,
        brand_name VARCHAR(160) NOT NULL DEFAULT '',
        logo_stored VARCHAR(64) NULL,
        logo_mime VARCHAR(64) NULL,
        primary_color CHAR(7) NOT NULL DEFAULT '#0f766e',
        secondary_color CHAR(7) NOT NULL DEFAULT '#111827',
        preferred_language CHAR(2) NOT NULL DEFAULT 'en',
        tone VARCHAR(160) NOT NULL DEFAULT '',
        contact VARCHAR(255) NOT NULL DEFAULT '',
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY token_hash (token_hash),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $userColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM users') as $row) {
        $userColumns[(string) $row['Field']] = true;
    }
    if (!isset($userColumns['role'])) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'user'");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS plans (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(32) NOT NULL,
        name_ar VARCHAR(80) NOT NULL,
        name_en VARCHAR(80) NOT NULL,
        price_sar DECIMAL(10,2) NOT NULL DEFAULT 0,
        video_limit INT UNSIGNED NOT NULL DEFAULT 0,
        video_max_seconds INT UNSIGNED NOT NULL DEFAULT 0,
        image_limit INT UNSIGNED NOT NULL DEFAULT 0,
        watermark TINYINT(1) NOT NULL DEFAULT 1,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS subscriptions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        plan_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'active',
        period_start DATETIME NOT NULL,
        period_end DATETIME NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_status (user_id, status),
        KEY plan_id (plan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS usage_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        campaign_id BIGINT UNSIGNED NULL,
        operation VARCHAR(32) NOT NULL,
        units INT UNSIGNED NOT NULL DEFAULT 1,
        seconds INT UNSIGNED NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'reserved',
        cost_sar DECIMAL(10,4) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_operation (user_id, operation, status, created_at),
        KEY campaign_id (campaign_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS campaign_briefs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        product VARCHAR(255) NOT NULL DEFAULT '',
        description MEDIUMTEXT NULL,
        audience VARCHAR(255) NOT NULL DEFAULT '',
        objective VARCHAR(500) NOT NULL DEFAULT '',
        location VARCHAR(160) NOT NULL DEFAULT '',
        campaign_language VARCHAR(16) NOT NULL DEFAULT 'auto',
        resolved_language VARCHAR(16) NULL,
        brand_tone VARCHAR(160) NOT NULL DEFAULT '',
        source_media MEDIUMTEXT NULL,
        media_analysis MEDIUMTEXT NULL,
        core_message MEDIUMTEXT NULL,
        channels VARCHAR(255) NOT NULL DEFAULT '',
        creative_direction MEDIUMTEXT NULL,
        campaign_score TINYINT UNSIGNED NULL,
        recommendation MEDIUMTEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY campaign_id (campaign_id),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS campaign_outputs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        output_type VARCHAR(64) NOT NULL,
        content MEDIUMTEXT NOT NULL,
        approval_status VARCHAR(16) NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY campaign_id (campaign_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $outputColumns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM campaign_outputs') as $row) {
        $outputColumns[(string) $row['Field']] = true;
    }
    if (!isset($outputColumns['approval_status'])) {
        $pdo->exec("ALTER TABLE campaign_outputs ADD COLUMN approval_status VARCHAR(16) NOT NULL DEFAULT 'draft'");
    }

    $catalog = [
        ['free', 'مجانية', 'Free', '0.00', 3, 8, 15, 1, 1],
        ['starter', 'أساسية', 'Starter', '79.00', 10, 10, 100, 0, 2],
        ['growth', 'نمو', 'Growth', '199.00', 30, 15, 300, 0, 3],
        ['pro', 'احترافية', 'Pro', '399.00', 80, 20, 1000, 0, 4],
        ['business', 'أعمال', 'Business', '799.00', 200, 30, 3000, 0, 5],
    ];
    $planExists = $pdo->prepare('SELECT id FROM plans WHERE code = ? LIMIT 1');
    $planInsert = $pdo->prepare('INSERT INTO plans (code, name_ar, name_en, price_sar, video_limit, video_max_seconds, image_limit, watermark, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)');
    foreach ($catalog as $plan) {
        $planExists->execute([$plan[0]]);
        if (!$planExists->fetch()) {
            $planInsert->execute($plan);
        }
    }

    $freeId = (int) $pdo->query("SELECT id FROM plans WHERE code = 'free' LIMIT 1")->fetchColumn();
    if ($freeId > 0) {
        $pdo->prepare("INSERT INTO subscriptions (user_id, plan_id, status, period_start, period_end)
            SELECT u.id, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH)
            FROM users u
            WHERE NOT EXISTS (
                SELECT 1 FROM subscriptions s WHERE s.user_id = u.id AND s.status = 'active'
            )")->execute([$freeId]);
    }

    $ownerEmail = strtolower(trim((string) getenv('RATEB_AI_OWNER_EMAIL')));
    if (filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        $pdo->prepare("UPDATE users SET role = 'owner' WHERE email = ? AND role <> 'owner'")->execute([$ownerEmail]);
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS video_jobs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        campaign_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(16) NOT NULL,
        provider VARCHAR(32) NOT NULL,
        storyboard MEDIUMTEXT NOT NULL,
        output_media_id BIGINT UNSIGNED NULL,
        usage_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        error_code VARCHAR(64) NOT NULL DEFAULT '',
        watermark TINYINT(1) NOT NULL DEFAULT 0,
        approval_status VARCHAR(16) NOT NULL DEFAULT 'draft',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY campaign_user (campaign_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function campaign_status_values(): array
{
    return ['draft', 'in_progress', 'ready', 'completed'];
}

function planner_status_values(): array
{
    return ['draft', 'review', 'approved', 'scheduled', 'published'];
}

function approval_status_values(): array
{
    return ['draft', 'approved', 'rejected'];
}

function campaign_one_of(string $value, array $allowed, string $fallback): string
{
    return in_array($value, $allowed, true) ? $value : $fallback;
}

function campaign_date_or_null(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function campaign_money_or_null(string $value): ?string
{
    $value = trim(str_replace(',', '', $value));
    if ($value === '' || preg_match('/\d+(?:\.\d+)?/', $value, $match) !== 1) {
        return null;
    }
    return $match[0];
}

function campaign_hex_color(string $value, string $fallback): string
{
    $value = strtolower(trim($value));
    return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : $fallback;
}

function campaign_brand_context(PDO $pdo, int $userId, array $campaign): string
{
    $parts = [];
    foreach (['objective' => 'Objective', 'budget' => 'Budget', 'start_date' => 'Start date', 'end_date' => 'End date'] as $field => $label) {
        $value = trim((string) ($campaign[$field] ?? ''));
        if ($value !== '') {
            $parts[] = $label . ': ' . $value;
        }
    }
    $stmt = $pdo->prepare('SELECT brand_name, tone, preferred_language, contact FROM brand_kits WHERE user_id = ?');
    $stmt->execute([$userId]);
    $brand = $stmt->fetch();
    if ($brand) {
        foreach (['brand_name' => 'Brand name', 'tone' => 'Tone of voice', 'preferred_language' => 'Preferred language', 'contact' => 'Contact'] as $field => $label) {
            $value = trim((string) ($brand[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $label . ': ' . $value;
            }
        }
    }
    return $parts === [] ? '' : ' Additional context: ' . implode('. ', $parts) . '.';
}
